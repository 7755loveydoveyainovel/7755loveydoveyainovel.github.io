<?php
/**
 * inc/sidebar.php — 좌측 사이드바
 *
 * 위에서 아래로:
 *  - 사이트 브랜드
 *  - 섹션 알약 탭 (있을 때만)
 *  - 메뉴 그룹들 (그룹별 wrapper, data-mg-section 으로 JS 필터링)
 *  - 그룹 미지정 게시판
 *  - (admin) 관리자 / 로그아웃 / (guest) 로그인
 *
 * 섹션 토글: 페이지 reload 없이 JS 가 .sb-group 들을 hide/show.
 * 첫 로드 시는 PHP 가 hp_current_section() 으로 active 상태 결정.
 *
 * 변수: $menu, $page, $login_error
 */

$menu            = $menu ?? hp_load_menu();
$current_bo_id   = (int)($_GET['bo_id'] ?? 0);
$current_page    = $page ?? 'home';
$current_section = hp_current_section();

// 그룹의 mg_sec_id 가 공용(0) 이거나 현재 섹션과 일치하면 표시, 아니면 hidden.
// PHP 시점에 결정해두면 JS 가 못 도는 환경 / SPA 갈아끼움 직후 빈 화면 모두 안전.
$sec_hidden = function ($sec_attr) use ($current_section) {
    $sec_attr = (int)$sec_attr;
    if ($sec_attr === 0) return '';
    if ($sec_attr === (int)$current_section) return '';
    return ' style="display:none"';
};
?>
<aside class="sidebar">
  <?php if (hp_config('show_site_name', '1') !== '0'): ?>
    <div class="sb-brand">
      <a href="<?= HP_BASE ?>/" class="name"><?= h(hp_config('site_name', 'My Page')) ?></a>
    </div>
  <?php endif; ?>

  <?php if (!empty($menu['sections'])): ?>
    <nav class="sb-sections">
      <?php foreach ($menu['sections'] as $sec): ?>
        <button type="button"
                class="sb-section-tab <?= $current_section == $sec['sec_id'] ? 'active' : '' ?>"
                data-section-id="<?= (int)$sec['sec_id'] ?>">
          <?php if (!empty($sec['sec_icon'])): ?><span class="ic"><?= hp_icon($sec['sec_icon']) ?></span> <?php endif; ?>
          <?= h($sec['sec_name']) ?>
        </button>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <!-- 공용 (홈) — 항상 표시 -->
  <div class="sb-group" data-mg-section="0">
    <div class="sb-section">main</div>
    <a href="<?= HP_BASE ?>/" class="sb-item <?= $current_page === 'home' ? 'active' : '' ?>">
      <span class="ic">cottage</span> 홈
    </a>
  </div>

  <?php foreach ($menu['groups'] as $g):
    $sec_attr = (int)($g['mg_sec_id'] ?? 0);

    if (!empty($g['mg_is_divider'])):
  ?>
    <div class="sb-group" data-mg-section="<?= $sec_attr ?>"<?= $sec_hidden($sec_attr) ?>>
      <hr class="sb-divider">
    </div>
  <?php
      continue;
    endif;

    if (!empty($g['mg_special_link'])):
      $sl = $g['mg_special_link'];
      if ($sl === 'guestbook' && hp_config('guestbook_enabled', '0') === '1'):
  ?>
    <div class="sb-group" data-mg-section="<?= $sec_attr ?>"<?= $sec_hidden($sec_attr) ?>>
      <a href="<?= hp_url('guestbook') ?>" class="sb-item <?= $current_page === 'guestbook' ? 'active' : '' ?>">
        <span class="ic"><?= hp_icon($g['mg_icon'] ?: 'edit_note') ?></span>
        <?= h($g['mg_name']) ?>
      </a>
    </div>
  <?php
      endif;
      continue;
    endif;

    if (empty($g['boards'])) continue;
  ?>
    <div class="sb-group" data-mg-section="<?= $sec_attr ?>"<?= $sec_hidden($sec_attr) ?>>
      <div class="sb-section"><?= h($g['mg_name']) ?></div>
      <?php foreach ($g['boards'] as $b): ?>
        <a href="<?= hp_url('board', ['slug' => $b['bo_slug']]) ?>"
           class="sb-item <?= $current_bo_id == $b['bo_id'] ? 'active' : '' ?>">
          <span class="ic"><?= hp_icon($b['bo_icon'] ?: 'article') ?></span>
          <?= h($b['bo_name']) ?>
          <?php if ($b['bo_mobile_pinned']): ?><span class="pin">push_pin</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <?php if (!empty($menu['ungrouped'])): ?>
    <div class="sb-group" data-mg-section="0">
      <div class="sb-section">기타</div>
      <?php foreach ($menu['ungrouped'] as $b): ?>
        <a href="<?= hp_url('board', ['slug' => $b['bo_slug']]) ?>"
           class="sb-item <?= $current_bo_id == $b['bo_id'] ? 'active' : '' ?>">
          <span class="ic"><?= hp_icon($b['bo_icon'] ?: 'article') ?></span>
          <?= h($b['bo_name']) ?>
          <?php if ($b['bo_mobile_pinned']): ?><span class="pin">push_pin</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="sb-footer">
    <?php if (is_admin()): ?>
      <a href="<?= HP_BASE ?>/admin/" class="sb-item">
        <span class="ic">settings</span> 관리자 설정
      </a>
      <form method="post" action="<?= HP_BASE ?>/" data-no-spa style="margin:0">
        <?= csrf_input() ?>
        <input type="hidden" name="admin_logout" value="1">
        <button type="submit" class="sb-item">
          <span class="ic">logout</span> 로그아웃
        </button>
      </form>
    <?php else: ?>
      <button class="sb-item login-toggle" type="button"
              onclick="var p=document.getElementById('loginPop');p.style.display=p.style.display==='block'?'none':'block';">
        <span class="ic">lock</span> 관리자 로그인
      </button>
      <div id="loginPop" class="login-pop" style="<?= !empty($login_error) ? '' : 'display:none' ?>">
        <form method="post" data-no-spa>
          <?= csrf_input() ?>
          <input type="hidden" name="admin_login" value="1">
          <input type="password" name="ad_pw" placeholder="비밀번호" required autofocus>
          <button type="submit">로그인</button>
        </form>
        <?php if (!empty($login_error)): ?>
          <?php if (!empty($login_error_wait)): ?>
            <div class="login-error">시도가 너무 많아요. <?= (int)ceil($login_error_wait / 60) ?>분 후 다시 시도해주세요.</div>
          <?php else: ?>
            <div class="login-error">비밀번호가 일치하지 않아요.</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</aside>

<script>
// 사이드바 섹션 탭 — document 레벨 delegation 으로 sidebar 가 SPA 로 갈아끼워져도 작동.
// 한 번만 등록되도록 window 플래그 사용.
(function () {
  function applyFilter(secId) {
    secId = String(secId);
    document.querySelectorAll('.sidebar .sb-group').forEach(function (g) {
      var s = g.getAttribute('data-mg-section') || '0';
      g.style.display = (s === '0' || s === secId) ? '' : 'none';
    });
    document.querySelectorAll('.sb-section-tab[data-section-id]').forEach(function (t) {
      t.classList.toggle('active', t.getAttribute('data-section-id') === secId);
    });
    var maxAge = 60 * 60 * 24 * 30;
    document.cookie = 'hp_section=' + encodeURIComponent(secId)
      + '; max-age=' + maxAge + '; path=/; SameSite=Lax';
  }

  // 노출 — drawer/tabbar 등에서 호출 가능
  window.hpApplySectionFilter = applyFilter;

  // document delegation — sidebar 가 갈아끼워져도 작동
  if (!window._hpSectionTabBound) {
    window._hpSectionTabBound = true;
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('.sb-section-tab[data-section-id]');
      if (!btn) return;
      e.preventDefault();
      applyFilter(btn.getAttribute('data-section-id'));
    });
  }

  // 페이지 로드 시 active section 결정 우선순위:
  // 1. PHP 가 부여한 .active 탭 (정상 케이스)
  // 2. hp_section 쿠키 (PHP 캐시 등으로 .active 누락된 경우 폴백)
  // 3. 첫 번째 탭 (마지막 폴백)
  var tabs = document.querySelectorAll('.sb-section-tab[data-section-id]');
  if (!tabs.length) return;

  var initActive = document.querySelector('.sb-section-tab.active');
  var resolvedSecId = null;

  if (initActive) {
    resolvedSecId = initActive.getAttribute('data-section-id');
  } else {
    var m = document.cookie.match(/(?:^|;\s*)hp_section=([^;]+)/);
    var cookieId = m ? decodeURIComponent(m[1]) : '';
    if (cookieId) {
      var matched = document.querySelector('.sb-section-tab[data-section-id="' + cookieId + '"]');
      if (matched) resolvedSecId = cookieId;
    }
    if (!resolvedSecId && tabs[0]) {
      resolvedSecId = tabs[0].getAttribute('data-section-id');
    }
  }

  if (resolvedSecId) applyFilter(resolvedSecId);
})();
</script>
