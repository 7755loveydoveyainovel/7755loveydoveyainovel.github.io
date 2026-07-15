<?php
/**
 * install.php — 홈페이지 키트 설치 마법사
 *
 * 3 단계:
 *  ① DB 정보 + 테이블 prefix → 스키마 생성 + 시드 데이터 삽입
 *  ② 관리자 계정 + 사이트 이름 + 기본 테마
 *  ③ 완료 화면 (홈/관리자 페이지로 가는 링크 + 삭제 안내)
 *
 * 설치 후 보안:
 *  - 이미 설치된 상태면 새 요청을 받지 않음 (state='locked')
 *  - data/.htaccess 가 자동 생성되어 시크릿 파일 직접 접근 차단
 *  - 사용자에게 install.php / install/ 폴더 삭제를 권고
 */

require_once __DIR__ . '/config.php';
// 설치 전: config.php 는 early-return → HP_PATH/HP_BASE 만 정의됨
// 설치 후: config.php 가 fully load → HP_PREFIX 정의 + lib/* 로드 + DB 연결

// ═══════════════════════════════════════════════════════════════
//  상태 감지
// ═══════════════════════════════════════════════════════════════
$state = 'step1';

// install.lock 마커가 있으면 DB 상태와 무관하게 영구 잠금.
// 점-파일이라 data/.htaccess 의 ^\. 규칙에 의해 외부 접근도 차단됨.
$_install_lock = HP_PATH . '/data/.install.lock';
if (file_exists($_install_lock)) {
    $state = 'locked';
} elseif (defined('HP_PREFIX')) {
    // .db_secret.php 존재 → 스키마/관리자 상태 확인
    try {
        $admin_count = (int)qVal("SELECT COUNT(*) FROM {admin}");
        $state = ($admin_count > 0) ? 'locked' : 'step2';
    } catch (Throwable $e) {
        // 시크릿은 있으나 테이블 없음 → 스키마 재실행이 필요
        $state = 'step1';
    }
}

$error = null;

// ═══════════════════════════════════════════════════════════════
//  POST 처리
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $state !== 'locked') {
    $action = $_POST['action'] ?? '';

    if ($action === 'step1' && $state === 'step1') {
        $error = handle_step1();
        if (!$error) {
            // 새 시크릿 반영을 위해 리다이렉트 (다음 요청에서 config.php 가 fully load)
            header('Location: install.php');
            exit;
        }
    } elseif ($action === 'step2' && $state === 'step2') {
        $error = handle_step2();
        if (!$error) {
            $state = 'done';
        }
    }
}

// ═══════════════════════════════════════════════════════════════
//  Step 1 처리: DB 연결 → schema → seed → .db_secret.php 생성
// ═══════════════════════════════════════════════════════════════
function handle_step1() {
    $host   = trim($_POST['host']   ?? 'localhost');
    $name   = trim($_POST['name']   ?? '');
    $user   = trim($_POST['user']   ?? '');
    $pass   = $_POST['pass']        ?? '';
    $prefix = trim($_POST['prefix'] ?? '');

    // 입력 검증
    if ($host === '' || $name === '' || $user === '' || $prefix === '') {
        return '필수 항목을 모두 입력해주세요. (DB 비밀번호는 비워둘 수 있어요)';
    }
    if (!preg_match('/^[a-z][a-z0-9_]{1,18}_$/', $prefix)) {
        return '접두사는 영문 소문자로 시작하고 _ 로 끝나야 합니다. (예: milk_, myroom_, 2~20자)';
    }

    // DB 연결 테스트
    try {
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        return 'DB 연결 실패: ' . $e->getMessage();
    }

    // schema.sql 읽기 + prefix 치환 + 한 문장씩 실행
    $schema_path = __DIR__ . '/install/schema.sql';
    if (!file_exists($schema_path)) {
        return 'install/schema.sql 파일을 찾을 수 없습니다.';
    }
    $sql = file_get_contents($schema_path);
    $sql = preg_replace_callback('/\{([a-z_][a-z0-9_]*)\}/i', function ($m) use ($prefix) {
        return $prefix . $m[1];
    }, $sql);

    try {
        // 주석 제거 (단순 -- 라인 주석)
        $sql = preg_replace('/^--.*$/m', '', $sql);
        // 세미콜론으로 분할
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function ($s) { return $s !== ''; }
        );
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
    } catch (PDOException $e) {
        return '스키마 생성 실패: ' . $e->getMessage();
    }

    // data/ 폴더 + 하위 폴더 + 시크릿 파일 생성
    $data_dir = HP_PATH . '/data';
    if (!is_dir($data_dir)) {
        if (!@mkdir($data_dir, 0755, true)) {
            return 'data/ 폴더를 만들 수 없습니다. 호스팅의 쓰기 권한을 확인해주세요.';
        }
    }
    // 하위 폴더 미리 생성 — 일부 호스팅(ivyro 등)에서 PHP mkdir 이 나중에 실패할 수 있어 설치 시점에
    foreach (['posts', 'uploads', 'banners'] as $_sub) {
        $sd = $data_dir . '/' . $_sub;
        if (!is_dir($sd)) @mkdir($sd, 0755, true);
    }

    // data/.htaccess (외부 직접 접근 차단 — 단, 이미지/CSS 는 허용)
    $htaccess = $data_dir . '/.htaccess';
    $htaccess_content = "# 점으로 시작하는 파일 (.db_secret.php, .htaccess 등) 차단\n"
        . "<FilesMatch \"^\\.\">\n"
        . "    Require all denied\n"
        . "    Order allow,deny\n"
        . "    Deny from all\n"
        . "</FilesMatch>\n"
        . "\n"
        . "# 실행 가능한 파일 차단 (.php, .phtml, .inc, .sql, .log 등)\n"
        . "<FilesMatch \"\\.(php|phtml|phps|php5|inc|sql|log)$\">\n"
        . "    Require all denied\n"
        . "    Order allow,deny\n"
        . "    Deny from all\n"
        . "</FilesMatch>\n"
        . "\n"
        . "# PHP 엔진 자체를 끈다 (이중 안전장치)\n"
        . "<IfModule mod_php.c>\n"
        . "    php_flag engine off\n"
        . "</IfModule>\n";
    @file_put_contents($htaccess, $htaccess_content);

    // .db_secret.php 작성
    $secret = [
        'host'   => $host,
        'name'   => $name,
        'user'   => $user,
        'pass'   => $pass,
        'prefix' => $prefix,
    ];
    $content = "<?php\n// 자동 생성됨 — 직접 수정하지 마세요\nreturn " . var_export($secret, true) . ";\n";

    $secret_path = $data_dir . '/.db_secret.php';
    if (@file_put_contents($secret_path, $content) === false) {
        return '.db_secret.php 파일을 작성할 수 없습니다. data/ 폴더 쓰기 권한을 확인해주세요.';
    }
    @chmod($secret_path, 0600);

    // ─── 시드 데이터 삽입 ───
    // lib/db.php 를 수동으로 로드하고 HP_PREFIX 정의 후 seed.php 실행
    if (!defined('HP_PREFIX')) {
        define('HP_PREFIX', $prefix);
    }
    require_once HP_PATH . '/lib/db.php';
    db_connect($host, $name, $user, $pass);

    try {
        require HP_PATH . '/install/seed.php';
    } catch (Throwable $e) {
        return '시드 데이터 삽입 실패: ' . $e->getMessage();
    }

    return null;  // success
}

// ═══════════════════════════════════════════════════════════════
//  Step 2 처리: 관리자 계정 + 사이트 정보
// ═══════════════════════════════════════════════════════════════
function handle_step2() {
    $pw       = $_POST['pw']            ?? '';
    $pw2      = $_POST['pw2']           ?? '';
    $sitename = trim($_POST['sitename'] ?? '');
    $theme    = $_POST['theme']         ?? 'paper';

    if ($pw === '' || $sitename === '') {
        return '모든 필드를 입력해주세요.';
    }
    if (strlen($pw) < 8) {
        return '비밀번호는 8자 이상이어야 합니다.';
    }
    if ($pw !== $pw2) {
        return '비밀번호 확인이 일치하지 않습니다.';
    }
    if (!in_array($theme, ['paper', 'linen', 'ink', 'midnight'], true)) {
        return '잘못된 테마 선택입니다.';
    }

    // 관리자 계정 생성 (단일 관리자 — ad_login 은 'admin' 고정)
    qInsert(
        "INSERT INTO {admin} (ad_login, ad_pw_hash, ad_created_at) VALUES (?, ?, NOW())",
        ['admin', password_hash($pw, PASSWORD_DEFAULT)]
    );

    // 사이트 이름 + 테마 저장 (UPSERT)
    // {config} 에 site_name/theme_preset 행이 시드되지 않으므로 UPDATE 는 0행 매칭되어
    // 조용히 사라진다. INSERT ... ON DUPLICATE KEY UPDATE 로 항상 기록되게 한다.
    qExec(
        "INSERT INTO {config} (cfg_key, cfg_value) VALUES ('site_name', ?)
         ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value)",
        [$sitename]
    );
    qExec(
        "INSERT INTO {config} (cfg_key, cfg_value) VALUES ('theme_preset', ?)
         ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value)",
        [$theme]
    );

    // 영구 잠금 마커 생성 — 이후 install.php 는 어떤 상태든 step1/step2 로 진입할 수 없음
    @file_put_contents(
        HP_PATH . '/data/.install.lock',
        "installed_at=" . date('c') . "\n"
    );

    return null;
}

// install context 전용 escape (config.php 의 h() 가 아직 로드되지 않았을 수 있음)
function _h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>홈페이지 키트 설치</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght@20,400">
<style>
:root {
  --bg: #f5f1e8;
  --paper: #fffdf6;
  --paper-2: #faf5e8;
  --ink: #2a2520;
  --ink-soft: #5a544a;
  --ink-mute: #948c7e;
  --hair: #e6dfce;
  --hair-soft: #efe9d8;
  --accent: #b8533a;
  --accent-soft: #f5e8e0;
  --accent-2: #6b7c5b;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  background: var(--bg);
  color: var(--ink);
  font-family: 'Pretendard', -apple-system, sans-serif;
  -webkit-font-smoothing: antialiased;
  min-height: 100vh;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 60px 20px;
  background-image: radial-gradient(circle at 1px 1px, rgba(80,60,30,.08) 1px, transparent 0);
  background-size: 22px 22px;
}
.card {
  background: var(--paper);
  border: 1px solid var(--hair);
  border-radius: 16px;
  width: 100%;
  max-width: 540px;
  box-shadow: 0 30px 80px -30px rgba(60,45,20,.25), 0 8px 24px -12px rgba(60,45,20,.15);
  overflow: hidden;
}
.head {
  padding: 36px 36px 26px;
  border-bottom: 1px solid var(--hair-soft);
  text-align: center;
}
.eyebrow {
  font-size: 10px;
  font-weight: 600;
  color: var(--accent);
  letter-spacing: .22em;
  text-transform: uppercase;
  margin-bottom: 12px;
}
.title {
  font-size: 24px;
  font-weight: 800;
  letter-spacing: -.025em;
  margin-bottom: 8px;
  line-height: 1.25;
}
.subtitle {
  font-size: 13px;
  color: var(--ink-soft);
  line-height: 1.65;
}
.steps {
  display: flex;
  justify-content: center;
  gap: 6px;
  margin-top: 22px;
}
.step-dot {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 12px;
  border-radius: 999px;
  background: var(--hair-soft);
  font-size: 11px;
  font-weight: 700;
  color: var(--ink-mute);
}
.step-dot.active { background: var(--accent); color: white; }
.step-dot.done   { background: var(--accent-2); color: white; }
.body { padding: 30px 36px 36px; }
.field { margin-bottom: 18px; }
.field label {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 6px;
}
.field .hint {
  display: block;
  font-size: 11px;
  color: var(--ink-mute);
  margin-bottom: 7px;
  font-weight: 500;
  line-height: 1.55;
}
.field .hint code {
  background: var(--accent-soft);
  color: var(--accent);
  padding: 1px 6px;
  border-radius: 3px;
  font-size: 11px;
  font-family: 'IBM Plex Mono', ui-monospace, monospace;
  font-weight: 600;
}
.field input {
  width: 100%;
  background: var(--paper-2);
  border: 1px solid var(--hair);
  border-radius: 8px;
  padding: 11px 13px;
  font-size: 13px;
  color: var(--ink);
  font-family: inherit;
  outline: none;
  transition: border-color .15s;
}
.field input:focus { border-color: var(--accent); }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.theme-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}
.theme-opt {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 13px;
  background: var(--paper-2);
  border: 1px solid var(--hair);
  border-radius: 8px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  transition: all .15s;
}
.theme-opt:hover { border-color: var(--ink-mute); }
.theme-opt input { display: none; }
.theme-opt.active {
  border-color: var(--accent);
  background: var(--accent-soft);
}
.theme-opt.active .name { color: var(--accent); }
.swatch {
  display: flex;
  width: 32px; height: 20px;
  border-radius: 4px;
  overflow: hidden;
  border: 1px solid rgba(0,0,0,.1);
  flex-shrink: 0;
}
.swatch span { flex: 1; }
.btn {
  display: block;
  width: 100%;
  background: var(--accent);
  color: white;
  border: none;
  border-radius: 9px;
  padding: 14px;
  font-size: 14px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  margin-top: 8px;
  transition: opacity .15s, transform .1s;
}
.btn:hover { opacity: .92; }
.btn:active { transform: scale(.98); }
.error {
  background: #fbe9e3;
  color: #a23a1a;
  border: 1px solid #f0c6b9;
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 12px;
  margin-bottom: 18px;
  font-weight: 500;
  line-height: 1.55;
}
.note {
  font-size: 11px;
  color: var(--ink-mute);
  line-height: 1.65;
  margin: 4px 0 8px;
  padding: 12px 14px;
  background: var(--paper-2);
  border-radius: 8px;
  border-left: 2px solid var(--accent-2);
}
.note b { color: var(--ink-soft); }
.note code {
  background: rgba(255,255,255,.7);
  padding: 1px 5px;
  border-radius: 3px;
  font-family: 'IBM Plex Mono', ui-monospace, monospace;
  font-size: 11px;
  color: var(--accent);
  font-weight: 600;
}
.done-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--accent-2);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 32px;
  margin: 0 auto 20px;
  font-family: 'Material Symbols Rounded';
  box-shadow: 0 6px 20px rgba(107, 124, 91, .3);
}
.done-links {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 24px;
}
.done-link {
  text-align: center;
  padding: 18px 12px;
  border: 1px solid var(--hair);
  border-radius: 10px;
  text-decoration: none;
  color: var(--ink);
  font-weight: 700;
  font-size: 13px;
  transition: all .15s;
  background: var(--paper-2);
}
.done-link:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-1px); }
.done-link .ic {
  display: block;
  font-family: 'Material Symbols Rounded';
  font-size: 24px;
  color: var(--ink-mute);
  margin-bottom: 6px;
}
.done-link:hover .ic { color: var(--accent); }
.delete-warn {
  margin-top: 22px;
  padding: 14px 16px;
  background: #fff8e1;
  border: 1px solid #f0d896;
  border-radius: 8px;
  font-size: 12px;
  color: #6b5520;
  line-height: 1.7;
}
.delete-warn b { color: #4a3a10; display: block; margin-bottom: 4px; }
.delete-warn code {
  background: rgba(255,255,255,.65);
  padding: 1px 6px;
  border-radius: 3px;
  font-family: 'IBM Plex Mono', ui-monospace, monospace;
  font-size: 11px;
}
</style>
</head>
<body>

<div class="card">
  <div class="head">
    <div class="eyebrow">homepage kit · installer</div>
    <div class="title">홈페이지 키트 설치</div>
    <div class="subtitle">3분이면 끝나요. 천천히 따라와 주세요.</div>
    <div class="steps">
      <?php
        $cls1 = $state === 'step1' ? 'active' : 'done';
        $cls2 = $state === 'step2' ? 'active' : (in_array($state, ['done','locked']) ? 'done' : '');
        $cls3 = in_array($state, ['done','locked']) ? 'active' : '';
      ?>
      <span class="step-dot <?= $cls1 ?>"><?= $state==='step1' ? '①' : '✓' ?> DB</span>
      <span class="step-dot <?= $cls2 ?>"><?= in_array($state,['done','locked']) ? '✓' : '②' ?> 관리자</span>
      <span class="step-dot <?= $cls3 ?>">③ 완료</span>
    </div>
  </div>

  <div class="body">
    <?php if ($error): ?>
      <div class="error"><?= _h($error) ?></div>
    <?php endif; ?>

    <?php if ($state === 'step1'): ?>
      <!-- ─── STEP 1: DB 정보 ─── -->
      <form method="post">
        <input type="hidden" name="action" value="step1">

        <div class="field">
          <label>DB 호스트</label>
          <input type="text" name="host" value="<?= _h($_POST['host'] ?? 'localhost') ?>" required>
        </div>
        <div class="field">
          <label>DB 이름</label>
          <input type="text" name="name" value="<?= _h($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="row">
          <div class="field">
            <label>DB 사용자</label>
            <input type="text" name="user" value="<?= _h($_POST['user'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>DB 비밀번호</label>
            <input type="password" name="pass" value="">
          </div>
        </div>

        <div class="field">
          <label>테이블 접두사 (prefix)</label>
          <span class="hint">
            나만의 홈페이지라는 느낌으로! 영문 소문자/숫자, <code>_</code> 로 끝나기.<br>
            예: <code>milk_</code>, <code>myroom_</code>, <code>quietroom_</code>
          </span>
          <input type="text" name="prefix"
                 value="<?= _h($_POST['prefix'] ?? '') ?>"
                 placeholder="milk_"
                 required
                 pattern="^[a-z][a-z0-9_]{1,18}_$">
        </div>

        <div class="note">
          <b>왜 접두사가 있나요?</b><br>
          하나의 DB 에 여러 사이트를 깔아도 서로 충돌하지 않게 해줘요. 그리고
          모든 테이블 이름에 <code>milk_post</code>, <code>milk_board</code>
          처럼 본인이 정한 이름이 붙어서, 내 사이트만의 작은 표식이 됩니다.
        </div>

        <button class="btn" type="submit">DB 설정 저장하고 다음 →</button>
      </form>

    <?php elseif ($state === 'step2'): ?>
      <!-- ─── STEP 2: 관리자 + 사이트 정보 ─── -->
      <form method="post">
        <input type="hidden" name="action" value="step2">

        <div class="field">
          <label>사이트 이름</label>
          <input type="text" name="sitename"
                 value="<?= _h($_POST['sitename'] ?? '') ?>"
                 required maxlength="60">
        </div>

        <div class="row">
          <div class="field">
            <label>관리자 비밀번호</label>
            <input type="password" name="pw" required minlength="8">
          </div>
          <div class="field">
            <label>비밀번호 확인</label>
            <input type="password" name="pw2" required minlength="8">
          </div>
        </div>

        <div class="note">
          단일 관리자 사이트라 ID 는 따로 받지 않아요. 비밀번호 하나면 충분합니다. 8자 이상 권장.
        </div>

        <div class="field">
          <label>기본 테마</label>
          <span class="hint">언제든 관리자 페이지에서 다시 바꿀 수 있어요.</span>
          <div class="theme-grid">
            <label class="theme-opt active">
              <input type="radio" name="theme" value="paper" checked>
              <div class="swatch"><span style="background:#f5f1e8"></span><span style="background:#fffdf6"></span><span style="background:#b8533a"></span></div>
              <span class="name">Paper · light</span>
            </label>
            <label class="theme-opt">
              <input type="radio" name="theme" value="linen">
              <div class="swatch"><span style="background:#eef0f3"></span><span style="background:#ffffff"></span><span style="background:#3a6ea5"></span></div>
              <span class="name">Linen · light</span>
            </label>
            <label class="theme-opt">
              <input type="radio" name="theme" value="ink">
              <div class="swatch"><span style="background:#1a1612"></span><span style="background:#221d18"></span><span style="background:#d4925a"></span></div>
              <span class="name">Ink · dark</span>
            </label>
            <label class="theme-opt">
              <input type="radio" name="theme" value="midnight">
              <div class="swatch"><span style="background:#0f1218"></span><span style="background:#161a22"></span><span style="background:#7eb3d4"></span></div>
              <span class="name">Midnight · dark</span>
            </label>
          </div>
        </div>

        <button class="btn" type="submit">설치 완료하기 →</button>
      </form>

    <?php elseif ($state === 'done' || $state === 'locked'): ?>
      <!-- ─── STEP 3: 완료 / Already installed ─── -->
      <div style="text-align:center">
        <div class="done-icon">check</div>
        <div class="title" style="font-size:20px">
          <?= $state === 'locked' ? '이미 설치되어 있어요' : '설치 완료!' ?>
        </div>
        <div class="subtitle" style="margin-top:6px">
          <?php if ($state === 'locked'): ?>
            홈페이지 키트가 이미 설치된 상태입니다. 추가 설정은 관리자 페이지에서 진행하세요.
          <?php else: ?>
            이제 사이트를 둘러보거나 관리자 페이지로 이동하세요.
          <?php endif; ?>
        </div>

        <div class="done-links">
          <a class="done-link" href="<?= _h(HP_BASE) ?>/">
            <span class="ic">cottage</span>
            홈으로
          </a>
          <a class="done-link" href="<?= _h(HP_BASE) ?>/admin/">
            <span class="ic">settings</span>
            관리자 페이지
          </a>
        </div>

        <div class="delete-warn">
          <b>⚠ 보안 권고</b>
          다른 사람이 다시 설치를 못 하도록 <code>install.php</code> 파일과
          <code>install/</code> 폴더를 삭제해 주세요. 이미 설치된 상태이므로 새 요청은
          자동으로 차단되지만, 파일 삭제가 가장 안전합니다.
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  // 테마 라디오 active 토글
  document.querySelectorAll('input[name="theme"]').forEach(radio => {
    radio.addEventListener('change', () => {
      document.querySelectorAll('.theme-opt').forEach(o => o.classList.remove('active'));
      radio.closest('.theme-opt').classList.add('active');
    });
  });
</script>

</body>
</html>
