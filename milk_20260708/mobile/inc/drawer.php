<?php
/**
 * mobile/inc/drawer.php — 풀스크린 드로어
 *
 * 더보기 탭 또는 섹션 탭을 누르면 위로 슬라이드 업.
 * 섹션 탭을 통해 열렸을 땐 해당 섹션의 그룹만 표시 (data-section 으로 필터링).
 *
 * 각 그룹은 .m-group div 로 감싸여 있어서 JS 가 일괄 토글할 수 있음.
 */

$menu          = $menu ?? hp_load_menu();
$current_bo_id = (int)($_GET['bo_id'] ?? 0);
$current_page  = $page ?? 'home';
$current_sec   = (int)hp_current_section();
?>
<div class="m-drawer" id="mDrawer" aria-hidden="true">
  <header class="m-drawer-head">
    <div class="ti" id="mDrawerTitle">전체 메뉴</div>
    <button type="button" class="close" id="mDrawerClose" aria-label="닫기">close</button>
  </header>

  <!-- 섹션 선택 view (sections 2개 이상일 때 더보기로 진입) -->
  <?php if (!empty($menu['sections']) && count($menu['sections']) >= 2): ?>
  <div class="m-drawer-sections" id="mDrawerSections" hidden>
    <div class="m-section">섹션 선택</div>
    <?php foreach ($menu['sections'] as $sec): ?>
      <button type="button"
              class="m-item m-section-pick <?= $current_sec == $sec['sec_id'] ? 'active' : '' ?>"
              data-section-id="<?= (int)$sec['sec_id'] ?>">
        <span class="ic"><?= hp_icon($sec['sec_icon'] ?: 'fas fa-folder') ?></span>
        <?= h($sec['sec_name']) ?>
        <?php if ($current_sec == $sec['sec_id']): ?>
          <span class="pin"><?= hp_icon('fas fa-check') ?></span>
        <?php endif; ?>
      </button>
    <?php endforeach; ?>

    <!-- 관리 (sections view 에서도 진입 가능하도록 마지막 섹션 아래에 표시) -->
    <div class="m-section">관리</div>
    <?php if (is_admin()): ?>
      <a href="<?= HP_BASE ?>/admin/" class="m-item">
        <span class="ic">settings</span> 관리자 설정
      </a>
      <form method="post" action="<?= HP_BASE ?>/" data-no-spa style="margin:0">
        <?= csrf_input() ?>
        <input type="hidden" name="admin_logout" value="1">
        <button type="submit" class="m-item">
          <span class="ic">logout</span> 로그아웃
        </button>
      </form>
    <?php else: ?>
      <button type="button" class="m-item m-login-toggle">
        <span class="ic">lock</span> 관리자 로그인
      </button>
      <div class="m-login-pop" style="display:none">
        <form method="post" data-no-spa>
          <?= csrf_input() ?>
          <input type="hidden" name="admin_login" value="1">
          <input type="password" name="ad_pw" placeholder="비밀번호" required>
          <button type="submit">로그인</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="m-drawer-body" id="mDrawerBody">
    <div class="m-group" data-section="0">
      <div class="m-section">main</div>
      <a href="<?= HP_BASE ?>/" class="m-item <?= $current_page === 'home' ? 'active' : '' ?>">
        <span class="ic">cottage</span> 홈
      </a>
    </div>

    <?php foreach ($menu['groups'] as $g):
      if (!empty($g['mg_is_divider'])):
    ?>
      <div class="m-group" data-section="<?= (int)($g['mg_sec_id'] ?? 0) ?>">
        <hr class="m-divider">
      </div>
    <?php
        continue;
      endif;

      if (!empty($g['mg_special_link'])):
        $sl = $g['mg_special_link'];
        if ($sl === 'guestbook' && hp_config('guestbook_enabled', '0') === '1'):
    ?>
      <div class="m-group" data-section="<?= (int)($g['mg_sec_id'] ?? 0) ?>">
        <a id="mg-<?= (int)$g['mg_id'] ?>" href="<?= hp_url('guestbook') ?>" class="m-item <?= $current_page === 'guestbook' ? 'active' : '' ?>">
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
      <div class="m-group" data-section="<?= (int)($g['mg_sec_id'] ?? 0) ?>">
        <div class="m-section" id="mg-<?= (int)$g['mg_id'] ?>"><?= h($g['mg_name']) ?></div>
        <?php foreach ($g['boards'] as $b): ?>
          <a href="<?= hp_url('board', ['slug' => $b['bo_slug']]) ?>"
             class="m-item <?= $current_bo_id == $b['bo_id'] ? 'active' : '' ?>">
            <span class="ic"><?= hp_icon($b['bo_icon'] ?: 'article') ?></span>
            <?= h($b['bo_name']) ?>
            <?php if ($b['bo_mobile_pinned']): ?><span class="pin">push_pin</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if (!empty($menu['ungrouped'])): ?>
      <div class="m-group" data-section="0">
        <div class="m-section">기타</div>
        <?php foreach ($menu['ungrouped'] as $b): ?>
          <a href="<?= hp_url('board', ['slug' => $b['bo_slug']]) ?>"
             class="m-item <?= $current_bo_id == $b['bo_id'] ? 'active' : '' ?>">
            <span class="ic"><?= hp_icon($b['bo_icon'] ?: 'article') ?></span>
            <?= h($b['bo_name']) ?>
            <?php if ($b['bo_mobile_pinned']): ?><span class="pin">push_pin</span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="m-group" data-section="0">
      <div class="m-section">관리</div>
      <?php if (is_admin()): ?>
        <a href="<?= HP_BASE ?>/admin/" class="m-item">
          <span class="ic">settings</span> 관리자 설정
        </a>
        <form method="post" action="<?= HP_BASE ?>/" data-no-spa style="margin:0">
          <?= csrf_input() ?>
          <input type="hidden" name="admin_logout" value="1">
          <button type="submit" class="m-item">
            <span class="ic">logout</span> 로그아웃
          </button>
        </form>
      <?php else: ?>
        <button type="button" class="m-item m-login-toggle">
          <span class="ic">lock</span> 관리자 로그인
        </button>
        <div class="m-login-pop" style="display:none">
          <form method="post" data-no-spa>
            <?= csrf_input() ?>
            <input type="hidden" name="admin_login" value="1">
            <input type="password" name="ad_pw" placeholder="비밀번호" required>
            <button type="submit">로그인</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
