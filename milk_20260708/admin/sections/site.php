<?php
/**
 * admin/sections/site.php — 사이트 기본 정보 + BGM + 차단 리스트
 *
 * 모든 섹션이 하나의 폼으로 묶여 있어서, 어떤 저장 버튼을 눌러도
 * 페이지의 모든 변경사항이 한 번에 저장됨.
 * 비밀번호 변경만 별도 폼 (보안 — 현재 비번 검증 필요).
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save_all';

    if ($action === 'save_all') {
        // ─── 기본 정보 ───
        if (isset($_POST['site_name'])) hp_config_set('site_name', trim($_POST['site_name']));
        if (isset($_POST['favicon']))   hp_config_set('favicon',   trim($_POST['favicon']));
        if (isset($_POST['site_url'])) {
            $_su = trim($_POST['site_url']);
            // http:// 또는 https:// 만 허용. 빈 값이면 자동 감지로 fallback.
            if ($_su === '' || preg_match('#^https?://[^/\s]+/?$#i', $_su)) {
                hp_config_set('site_url', rtrim($_su, '/'));
            } else {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '사이트 URL은 http:// 또는 https:// 로 시작하는 호스트만 입력해주세요. (예: https://example.com)'];
            }
        }
        hp_config_set('noindex', !empty($_POST['noindex']) ? '1' : '0');

        // ─── 비밀글 일괄 비밀번호 ───
        if (!empty($_POST['clear_bulk_pw'])) {
            hp_config_set('bulk_private_pw_hash', '');
        } elseif (!empty($_POST['bulk_private_pw'])) {
            hp_config_set('bulk_private_pw_hash', password_hash($_POST['bulk_private_pw'], PASSWORD_DEFAULT));
        }

        // ─── BGM ───
        if (isset($_POST['bgm_url'])) hp_config_set('bgm_url', trim($_POST['bgm_url']));
        hp_config_set('bgm_autoplay', !empty($_POST['bgm_autoplay']) ? '1' : '0');
        hp_config_set('bgm_loop',     !empty($_POST['bgm_loop'])     ? '1' : '0');

        // ─── 차단 리스트 ───
        if (isset($_POST['blocked_ips'])) {
            $ips = preg_split('/\r\n|\r|\n/', trim($_POST['blocked_ips']));
            $ips = array_filter(array_map('trim', $ips));
            hp_config_set('blocked_ips', implode("\n", $ips));
        }
        if (isset($_POST['blocked_words'])) {
            $words = preg_split('/\r\n|\r|\n/', trim($_POST['blocked_words']));
            $words = array_filter(array_map('trim', $words));
            hp_config_set('blocked_words', implode("\n", $words));
        }

        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '설정이 저장되었습니다.'];
    }

    elseif ($action === 'change_password') {
        $current = $_POST['current_pw'] ?? '';
        $new1    = $_POST['new_pw']     ?? '';
        $new2    = $_POST['new_pw2']    ?? '';

        if ($new1 === '' || $current === '') {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '모든 필드를 입력해주세요.'];
        } elseif (strlen($new1) < 8) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '새 비밀번호는 8자 이상이어야 합니다.'];
        } elseif ($new1 !== $new2) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '새 비밀번호 확인이 일치하지 않습니다.'];
        } else {
            $admin = qOne("SELECT * FROM {admin} ORDER BY ad_id LIMIT 1");
            if (!$admin || !password_verify($current, $admin['ad_pw_hash'])) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '현재 비밀번호가 일치하지 않습니다.'];
            } else {
                qExec(
                    "UPDATE {admin} SET ad_pw_hash = ? WHERE ad_id = ?",
                    [password_hash($new1, PASSWORD_DEFAULT), $admin['ad_id']]
                );
                $_SESSION['_flash'] = ['type' => 'success', 'msg' => '비밀번호가 변경되었습니다.'];
            }
        }
    }

    header('Location: ' . HP_BASE . '/admin/?section=site');
    exit;
}
?>

<!-- ═══════════════ 통합 폼 (모든 섹션이 함께 저장) ═══════════════ -->
<form method="post">
  <?= csrf_input() ?>
  <input type="hidden" name="action" value="save_all">

  <!-- ─── 기본 정보 ─── -->
  <div class="adm-section">
    <h2>사이트 정보</h2>
    <div class="sub">사이트의 이름과 파비콘. 사이드바·브라우저 탭·검색엔진 등에 사용돼요.</div>

    <div class="adm-field">
      <label>사이트 이름</label>
      <input type="text" name="site_name" value="<?= h(hp_config('site_name')) ?>" maxlength="60" required>
    </div>

    <div class="adm-field">
      <label>파비콘 URL</label>
      <span class="hint">브라우저 탭에 표시되는 작은 아이콘. 외부 URL로 입력. 비워두면 사이트 첫 글자 + 테마색으로 자동 생성.</span>
      <input type="url" name="favicon" value="<?= h(hp_config('favicon')) ?>">
    </div>

    <div class="adm-field">
      <label>사이트 URL <span class="hint" style="font-weight:normal">(권장)</span></label>
      <span class="hint">
        공유 카드(OG)와 공유 링크에 사용되는 절대 URL. 비워두면 요청 헤더에서 자동 감지되지만,
        프록시 환경이나 캐시 포이즈닝 방지를 위해 직접 지정하는 것을 권장합니다.
        형식: <code>https://example.com</code> (경로/슬래시 없이)
      </span>
      <input type="url" name="site_url" value="<?= h(hp_config('site_url')) ?>" placeholder="https://example.com">
    </div>

    <div class="adm-field">
      <label class="check-row">
        <input type="checkbox" name="noindex" value="1" <?= hp_config('noindex') === '1' ? 'checked' : '' ?>>
        <span>
          <strong>검색 엔진 노출 차단</strong>
          <span class="hint" style="display:block;margin-top:2px;">
            구글·네이버·다음 등의 검색 결과에 노출되지 않도록 noindex 메타 태그를 추가합니다.
            지인끼리만 공유하고 싶은 사이트라면 켜두세요.
          </span>
        </span>
      </label>
    </div>

    <div class="adm-field">
      <label>비밀글 일괄 비밀번호</label>
      <span class="hint">
        방문자가 댓글/방명록에 <strong>비공개</strong> 체크 후 비밀번호를 입력하지 않으면 이 값이 자동으로 사용됩니다.
        비워두면 비공개 + 무비번 = 관리자만 열 수 있게 됩니다.
        <?php if (hp_config('bulk_private_pw_hash')): ?>
          <br><strong style="color:var(--accent)">현재 설정되어 있음</strong>
        <?php else: ?>
          <br><strong style="color:var(--ink-mute)">현재 설정 안 됨</strong>
        <?php endif; ?>
      </span>
      <input type="password" name="bulk_private_pw" maxlength="60" placeholder="<?= hp_config('bulk_private_pw_hash') ? '변경하려면 새 비밀번호 입력' : '예: openpage' ?>" autocomplete="new-password">
      <?php if (hp_config('bulk_private_pw_hash')): ?>
        <label class="check-row" style="margin-top:8px">
          <input type="checkbox" name="clear_bulk_pw" value="1">
          <span style="font-size:12px;color:var(--ink-soft)">일괄 비밀번호 사용 중지</span>
        </label>
      <?php endif; ?>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </div>

  <!-- ─── BGM ─── -->
  <div class="adm-section">
    <h2>BGM (배경음악)</h2>
    <div class="sub">
      유튜브 영상 또는 플레이리스트 URL 을 입력하면 사이트 우측 하단에 작은 음악 플레이어가 나타나요.
      방문자가 ▶ 버튼을 누르면 재생됩니다 (브라우저 정책상 자동 재생은 사용자 동작이 필요해요).
    </div>

    <div class="adm-field">
      <label>유튜브 URL</label>
      <span class="hint">
        지원 형식:<br>
        · 단일 영상: <code>https://www.youtube.com/watch?v=XXXXX</code> 또는 <code>https://youtu.be/XXXXX</code><br>
        · 플레이리스트: <code>https://www.youtube.com/playlist?list=PLXXXXX</code><br>
        · 영상+플레이리스트: <code>https://www.youtube.com/watch?v=XXXXX&list=PLXXXXX</code><br>
        비워두면 BGM 플레이어가 표시되지 않아요.
      </span>
      <input type="url" name="bgm_url" value="<?= h(hp_config('bgm_url')) ?>" placeholder="https://www.youtube.com/...">
    </div>

    <div class="adm-field">
      <label class="check-row">
        <input type="checkbox" name="bgm_autoplay" value="1" <?= hp_config('bgm_autoplay') === '1' ? 'checked' : '' ?>>
        <span>
          <strong>자동 재생 시도</strong>
          <span class="hint" style="display:block;margin-top:2px;">
            대부분의 브라우저는 사용자가 페이지를 한 번 클릭하기 전엔 자동 재생을 차단해요.
            플레이어 ▶ 버튼이 활성화된 상태로 시작.
          </span>
        </span>
      </label>
    </div>

    <div class="adm-field">
      <label class="check-row">
        <input type="checkbox" name="bgm_loop" value="1" <?= hp_config('bgm_loop', '1') === '1' ? 'checked' : '' ?>>
        <span><strong>반복 재생</strong></span>
      </label>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </div>

  <!-- ─── 차단 리스트 ─── -->
  <div class="adm-section">
    <h2>차단 리스트</h2>
    <div class="sub">스팸·악성 댓글 방지용. 한 줄에 하나씩 입력하세요.</div>

    <div class="adm-field">
      <label>차단 IP</label>
      <span class="hint">댓글·방명록 등록 시 이 목록의 IP 는 차단됩니다. 와일드카드(*) 사용 가능 (예: <code>123.45.*.*</code>).</span>
      <textarea name="blocked_ips" class="code" rows="6"><?= h(hp_config('blocked_ips')) ?></textarea>
    </div>

    <div class="adm-field">
      <label>금지 단어</label>
      <span class="hint">댓글·방명록 본문에 이 단어가 포함되면 등록이 차단됩니다. 대소문자 구분 안 함.</span>
      <textarea name="blocked_words" class="code" rows="6"><?= h(hp_config('blocked_words')) ?></textarea>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </div>
</form>

<!-- ─── 비밀번호 변경 (별도 폼 — 보안) ─── -->
<div class="adm-section">
  <h2>관리자 비밀번호 변경</h2>
  <div class="sub">단일 관리자 사이트라 ID 는 사용하지 않아요. 비밀번호만 관리하면 됩니다.</div>

  <form method="post" autocomplete="off">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="change_password">

    <div class="adm-field">
      <label>현재 비밀번호</label>
      <input type="password" name="current_pw" required autocomplete="current-password">
    </div>

    <div class="adm-row">
      <div class="adm-field">
        <label>새 비밀번호</label>
        <input type="password" name="new_pw" required minlength="8" autocomplete="new-password" placeholder="8자 이상">
      </div>
      <div class="adm-field">
        <label>새 비밀번호 확인</label>
        <input type="password" name="new_pw2" required minlength="8" autocomplete="new-password">
      </div>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">비밀번호 변경</button>
    </div>
  </form>
</div>
