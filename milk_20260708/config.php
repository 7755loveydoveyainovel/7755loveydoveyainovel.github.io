<?php
/**
 * config.php — 환경 부트스트랩
 *
 * 모든 진입점(index.php, admin/*, ajax/*)에서 가장 먼저 require.
 * 책임:
 *  - 경로/URL 상수 정의
 *  - DB 시크릿 로드 (없으면 install.php로 리다이렉트)
 *  - 라이브러리 일괄 로드
 *  - DB 연결, 세션 시작
 *  - 공통 헬퍼 함수 (h, hp_url, hp_config 등)
 */

// ─── 경로 / URL ───
define('HP_PATH', __DIR__);

// ─── PHP 운영 설정 — 에러를 화면에 노출하지 않음 (정보 노출 방지) ───
// 어떤 호스팅이든 일관되게 production 모드로 동작하게 명시.
// 에러는 호스팅 제공 로그(또는 PHP error_log)로만 기록.
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

// ─── 캐시 제어 ───
// 동적 페이지 (메뉴 구조, 섹션 상태가 매 요청 달라질 수 있음) — 호스팅의 자동 캐싱 방지.
// 일부 무료/공유 호스팅은 PHP 응답을 자동 캐싱해서 옛 메뉴가 한참 노출되는 일이 있음.
// 이미지/CSS 같은 정적 파일은 자기 헤더를 가지므로 영향 없음.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// 호출 위치(루트/admin/ajax)에 상관없이 항상 사이트 루트 URL을 가리키도록 보정

$_script_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if (preg_match('#/(admin|ajax)$#', $_script_dir)) {
    $_parent = dirname($_script_dir);
    // dirname('/admin') == '/'  (Linux),  '\\' (Windows),  '.' (상대경로)
    define('HP_BASE',
        ($_parent === '' || $_parent === '/' || $_parent === '\\' || $_parent === '.')
            ? '' : $_parent);
} else {
    // 일반 페이지에서도 루트면 빈 문자열로 (방어적)
    // rtrim 결과가 '' 이 되는 경우(SCRIPT_NAME='/index.php')도 명시적으로 처리
    define('HP_BASE',
        ($_script_dir === '' || $_script_dir === '/' || $_script_dir === '\\' || $_script_dir === '.')
            ? '' : $_script_dir);
}
define('HP_URL', HP_BASE);  // 별칭

// ─── DB 시크릿 로드 ───
$_secret_path = HP_PATH . '/data/.db_secret.php';

if (!file_exists($_secret_path)) {
    // 설치 화면 자체에서는 그냥 통과시키고, 그 외에는 install.php로 보냄
    if (basename($_SERVER['SCRIPT_NAME']) !== 'install.php') {
        header('Location: ' . HP_BASE . '/install.php');
        exit;
    }
    return;  // install.php 안에서는 여기서 리턴
}

$_secret = require $_secret_path;
// $_secret = ['host'=>..., 'name'=>..., 'user'=>..., 'pass'=>..., 'prefix'=>'milk_']

if (!is_array($_secret) || empty($_secret['prefix'])) {
    die('DB 시크릿 파일이 손상되었습니다. data/.db_secret.php 확인 필요.');
}

// ★ 사용자가 install 단계에서 정한 테이블 prefix
define('HP_PREFIX', $_secret['prefix']);

// ─── 라이브러리 로드 ───
require_once HP_PATH . '/lib/db.php';
require_once HP_PATH . '/lib/auth.php';
require_once HP_PATH . '/lib/csrf.php';
require_once HP_PATH . '/lib/menu.php';
require_once HP_PATH . '/lib/theme.php';
require_once HP_PATH . '/lib/block.php';
require_once HP_PATH . '/lib/spam.php';
require_once HP_PATH . '/lib/ratelimit.php';
require_once HP_PATH . '/lib/markdown.php';

// ─── DB 연결 ───
db_connect($_secret['host'], $_secret['name'], $_secret['user'], $_secret['pass']);

// ─── 세션 ───
if (session_status() === PHP_SESSION_NONE) {
    // 세션 수명 = 7일 (lib/auth.php 의 ADMIN_SESSION_TIMEOUT 과 맞춤)
    $_sess_ttl = 86400 * 7;

    // 공유 호스팅(ivyro 등) 대응:
    //  - 시스템 기본 session.gc_maxlifetime 이 1440초(24분)로 짧으면 비활성 24분 만에
    //    세션 파일이 GC 로 삭제 → "간헐적 로그아웃" 의 원인.
    //  - /tmp 같은 공용 세션 디렉토리는 옆 사이트 트래픽이 GC 를 돌릴 때 내 세션까지
    //    같이 청소될 수 있음.
    // → 전용 저장 디렉토리 + gc_maxlifetime 를 7일로 명시해 둘 다 차단.
    @ini_set('session.gc_maxlifetime', (string)$_sess_ttl);
    $_sess_dir = HP_PATH . '/data/.sessions';
    if (!is_dir($_sess_dir)) @mkdir($_sess_dir, 0700, true);
    // 세션 파일(sess_*)은 점으로 시작하지 않아 data/.htaccess 의 ^\. 규칙에 안 걸린다.
    // 폴더 자체에 deny-all .htaccess 를 깔아 외부 직접 접근을 차단.
    $_sess_ht = $_sess_dir . '/.htaccess';
    if (is_dir($_sess_dir) && !file_exists($_sess_ht)) {
        @file_put_contents(
            $_sess_ht,
            "Require all denied\nOrder allow,deny\nDeny from all\n"
        );
    }
    // 디렉토리 쓰기가 가능할 때만 전용 경로 사용 (실패 시 기본 경로로 graceful fallback)
    if (is_dir($_sess_dir) && is_writable($_sess_dir)) {
        @ini_set('session.save_path', $_sess_dir);
        // 전용 디렉토리이므로 우리 세션만 청소 — GC 확률을 정상화
        @ini_set('session.gc_probability', '1');
        @ini_set('session.gc_divisor',     '100');
    }

    // HTTPS 자동 감지 — 사이트가 HTTPS 면 secure 플래그 자동 적용
    // (HTTPS 사이트에서 cookie 가 평문으로 새는 걸 막음)
    $_is_https = (
        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    );
    session_set_cookie_params([
        // lifetime 0(세션 쿠키)이면 브라우저 종료 시 사라짐 → 7일 명시로 변경.
        'lifetime' => $_sess_ttl,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $_is_https,
    ]);
    session_start();
}

// 스키마 자동 업그레이드 — DB 연결 + 세션 시작 후
// (세션당 1회만 검사. 새 컬럼 필요 시 ALTER TABLE 자동 실행)
hp_schema_upgrade();

// ═══════════════════════════════════════════════════════════════
//  공통 헬퍼 함수
// ═══════════════════════════════════════════════════════════════

/** HTML escape */
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** 라우트 URL 생성 */
function hp_url($page = 'home', $params = []) {
    $url = HP_BASE . '/?p=' . urlencode($page);
    foreach ($params as $k => $v) {
        $url .= '&' . urlencode($k) . '=' . urlencode((string)$v);
    }
    return $url;
}

/**
 * 사이트 설정값 조회 (요청 단위 캐시)
 *
 * 캐시는 $GLOBALS 에 저장 → hp_config_set() 도 같은 캐시를 갱신할 수 있음.
 */
function hp_config($key, $default = '') {
    if (!isset($GLOBALS['_hp_config_cache'])) {
        $GLOBALS['_hp_config_cache'] = [];
        foreach (qAll("SELECT cfg_key, cfg_value FROM {config}") as $row) {
            $GLOBALS['_hp_config_cache'][$row['cfg_key']] = $row['cfg_value'];
        }
    }
    return $GLOBALS['_hp_config_cache'][$key] ?? $default;
}

function hp_config_set($key, $value) {
    qExec(
        "INSERT INTO {config} (cfg_key, cfg_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value)",
        [$key, $value]
    );
    // 캐시 갱신 (같은 요청 안에서 set 직후 get 했을 때 새 값이 나오도록)
    if (isset($GLOBALS['_hp_config_cache'])) {
        $GLOBALS['_hp_config_cache'][$key] = $value;
    }
}

/** CSS 안전한 URL인지 검증 (style 속성용) */
function sanitize_css_url($url) {
    if (!$url) return '';
    if (!preg_match('#^https?://#i', $url)) return '';
    return $url;
}

/**
 * 아이콘 출력
 *
 * 두 가지 아이콘 시스템 지원:
 *  - Material Symbols: 그냥 글리프 이름. 예: "cottage", "alternate_email"
 *  - FontAwesome 6:    fa-* 클래스 포함. 예: "fab fa-twitter", "fas fa-envelope"
 *
 * 사용:
 *   <span class="ic"><?= hp_icon($icon_name) ?></span>
 *
 * 부모 .ic span 의 font-size 가 양쪽 모두에 적용됨.
 * Material Symbols 는 span 의 font-family (Material Symbols Rounded) 로 글리프 렌더,
 * FontAwesome 은 자기 클래스의 font-family 가 자식 <i> 에 적용됨.
 */
function hp_icon($name) {
    if (!$name) return '';
    $name = trim($name);
    if (strpos($name, 'fa-') !== false) {
        return '<i class="' . h($name) . '"></i>';
    }
    return h($name);
}

/**
 * 게시글의 이미지 목록 가져오기. po_extra JSON 의 images 우선,
 * 없으면 po_thumbnail (단일) fallback. 결과는 항상 배열.
 */
function hp_post_images($post) {
    $images = [];
    if (!empty($post['po_extra'])) {
        $extra = json_decode($post['po_extra'], true);
        if (is_array($extra) && isset($extra['images']) && is_array($extra['images'])) {
            $images = array_values(array_filter($extra['images'], 'strlen'));
        }
    }
    if (empty($images) && !empty($post['po_thumbnail'])) {
        $images = [$post['po_thumbnail']];
    }
    return $images;
}

/**
 * 이미지 배열을 받아 [thumbnail, po_extra_json] 으로 변환.
 * 첫 번째 이미지는 po_thumbnail 에도 저장 — 목록·메인 페이지의 단일 썸네일 표시용.
 */
function hp_pack_post_images(array $images) {
    $images = array_values(array_filter($images, 'strlen'));
    if (count($images) > 20) $images = array_slice($images, 0, 20);
    $thumbnail = $images[0] ?? null;
    $extra     = $images ? json_encode(['images' => $images], JSON_UNESCAPED_SLASHES) : null;
    return [$thumbnail, $extra];
}

/**
 * 게시판 slug 검증 — 영소문자, 숫자, 언더바만, 1~30자.
 * 빈 문자열도 false. 통과하면 sanitized slug 반환, 실패 시 false.
 */
function hp_validate_slug($slug) {
    $slug = trim((string)$slug);
    if ($slug === '') return false;
    if (!preg_match('/^[a-z0-9_]{1,30}$/', $slug)) return false;
    return $slug;
}

/**
 * 게시글 이미지 URL 생성. board 정보 (또는 bo_slug 문자열) 가 있으면
 * data/posts/{slug}/filename 우선, 그 위치에 없으면 data/posts/filename 폴백.
 */
function hp_post_image_url($filename, $board_or_slug = null) {
    if (!$filename) return '';
    // 외부 URL 은 그대로
    if (preg_match('#^https?://#i', $filename)) return $filename;

    $slug = '';
    if (is_array($board_or_slug)) {
        $slug = $board_or_slug['bo_slug'] ?? '';
    } elseif (is_string($board_or_slug)) {
        $slug = $board_or_slug;
    }

    // slug 가 있고 해당 폴더에 파일이 존재하면 분리 폴더 사용
    if ($slug !== '') {
        $sub = HP_PATH . '/data/posts/' . $slug . '/' . $filename;
        if (file_exists($sub)) {
            return HP_BASE . '/data/posts/' . rawurlencode($slug) . '/' . rawurlencode($filename);
        }
    }
    // 폴백: slug 폴더에 없거나 slug 정보 자체가 없는 경우
    return HP_BASE . '/data/posts/' . rawurlencode($filename);
}

/**
 * 게시글 이미지의 절대 경로 (디스크 unlink/검사용).
 */
function hp_post_image_path($filename, $board_or_slug = null) {
    if (!$filename) return '';
    $slug = '';
    if (is_array($board_or_slug)) {
        $slug = $board_or_slug['bo_slug'] ?? '';
    } elseif (is_string($board_or_slug)) {
        $slug = $board_or_slug;
    }
    if ($slug !== '') {
        $sub = HP_PATH . '/data/posts/' . $slug . '/' . $filename;
        if (file_exists($sub)) return $sub;
    }
    return HP_PATH . '/data/posts/' . $filename;
}

/**
 * 단일 업로드 파일 검증 + data/posts/{slug}/ 저장. 성공 시 파일명, 실패 시 false.
 * 에러 메시지는 $err 참조로 전달.
 *
 * @param string|null $bo_slug 게시판 slug. null 이면 data/posts/ 직접에 저장.
 */
function hp_save_uploaded_image($tmp, $orig_name, $size, &$err, $bo_slug = null) {
    if ($size > 5 * 1024 * 1024) { $err = '이미지 크기는 5MB 이하여야 합니다.'; return false; }
    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
        $err = '이미지 형식: JPG, PNG, GIF, WebP'; return false;
    }
    if (!@getimagesize($tmp)) { $err = '이미지 파일이 손상되었습니다.'; return false; }

    // 실제 MIME 타입도 검증 — polyglot 파일(이미지 + PHP) 차단
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $tmp);
            finfo_close($finfo);
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if ($mime && !in_array($mime, $allowed_mimes, true)) {
                $err = '이미지가 아닌 파일입니다.'; return false;
            }
        }
    }

    // slug 유효 → data/posts/{slug}/, 아니면 data/posts/ 직접
    $base = HP_PATH . '/data/posts';
    $dir  = $base;
    if ($bo_slug && hp_validate_slug($bo_slug)) {
        $dir = $base . '/' . $bo_slug;
    }
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            $err = '이미지 저장 폴더를 만들 수 없습니다. FTP에서 data/posts/ 폴더의 권한을 755로 설정해주세요.';
            return false;
        }
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $filename)) {
        $err = '이미지 저장 실패. data/posts/ 권한을 확인해주세요.'; return false;
    }
    return $filename;
}

/**
 * 게시판의 카테고리 목록 (쉼표 구분 문자열 → 배열). 빈 값/공백 자동 제거.
 */
function hp_board_categories($board) {
    if (empty($board['bo_categories'])) return [];
    $list = array_map('trim', explode(',', $board['bo_categories']));
    return array_values(array_filter($list, 'strlen'));
}

/**
 * SPA-aware redirect — 원본 요청이 ?ajax=1 이면 redirect 에도 ajax=1 자동 추가.
 * SPA fetch 가 follow 후에도 fragment 응답을 받게 함.
 *
 * fragment(#) 가 붙은 URL 도 안전하게 처리:
 *   "/?p=view&po_id=1#logs"   →  "/?p=view&po_id=1&ajax=1#logs"
 *   "/?p=view&po_id=1"        →  "/?p=view&po_id=1&ajax=1"
 *   "/?p=home"                →  "/?p=home&ajax=1"
 *   "/install.php"            →  "/install.php?ajax=1"
 * 잘못된 예 (fragment 뒤에 query 가 붙으면 브라우저가 fragment 일부로 해석):
 *   "/?p=view&po_id=1#logs&ajax=1" ← ajax=1 이 서버 쿼리스트링에 안 들어감
 */
function hp_redirect($url) {
    if (!empty($_GET['ajax']) || !empty($_POST['ajax'])) {
        // fragment 분리 (있으면 #포함, 없으면 빈 문자열)
        $hash = '';
        $hash_pos = strpos($url, '#');
        if ($hash_pos !== false) {
            $hash = substr($url, $hash_pos);   // "#logs"
            $url  = substr($url, 0, $hash_pos); // "/?p=view&po_id=1"
        }
        $url .= (strpos($url, '?') !== false ? '&' : '?') . 'ajax=1';
        $url .= $hash;  // fragment 다시 부착
    }
    header('Location: ' . $url);
    exit;
}

/**
 * 현재 섹션 ID. ?section=N 쿼리 우선, 없으면 쿠키, 없으면 첫 섹션.
 * 결정 로직은 hp_load_menu() 안에서 — 여기서는 그 결과만 반환.
 */
function hp_current_section() {
    $menu = hp_load_menu();
    return (int)($menu['current_section_id'] ?? 0);
}

/**
 * 섹션 쿠키 설정 (30일). 0 이면 삭제.
 * PHP 7.2 호환 — 옛 setcookie signature 사용.
 */
function hp_set_section_cookie($section_id) {
    $section_id = (int)$section_id;
    $expires    = $section_id <= 0 ? time() - 3600 : time() + 86400 * 30;
    $path       = HP_BASE === '' ? '/' : HP_BASE . '/';
    setcookie('hp_section', (string)$section_id, $expires, $path, '', false, false);
    if ($section_id <= 0) {
        unset($_COOKIE['hp_section']);
    } else {
        $_COOKIE['hp_section'] = (string)$section_id;
    }
}

/**
 * 게시판 비밀번호 보호 여부. bo_password 가 설정되어 있으면 true.
 */
function hp_board_is_protected($board) {
    return !empty($board['bo_password']);
}

/**
 * 비밀번호 보호 게시판의 잠금 해제 여부 (세션 기반).
 * 관리자는 항상 해제 상태.
 */
function hp_board_is_unlocked($board) {
    if (is_admin()) return true;
    if (empty($board['bo_password'])) return true;
    return !empty($_SESSION['hp_board_unlock'][(int)$board['bo_id']]);
}

/**
 * 비밀번호 보호 게시판 잠금 해제 시도. 성공 시 세션에 기록.
 */
function hp_board_try_unlock($board, $input_pw) {
    if (empty($board['bo_password'])) return true;
    if (password_verify($input_pw, $board['bo_password'])) {
        $_SESSION['hp_board_unlock'][(int)$board['bo_id']] = true;
        return true;
    }
    return false;
}
