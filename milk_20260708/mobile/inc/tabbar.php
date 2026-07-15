<?php
/**
 * mobile/inc/tabbar.php — 하단 탭바
 *
 * 슬롯 구성:
 *   1. 홈 (항상)
 *   2~N. 핀 게시판 (현재 섹션 + 공용 그룹의 핀, 최대 N개)
 *   N+1. 방명록 (있으면)
 *   마지막. 더보기
 *
 * 더보기 동작 (mobile.js 에서 처리):
 *   - sections 0~1 개: 전체 메뉴 drawer 직접 표시
 *   - sections 2+ 개: 섹션 선택 view (drawer 안 다른 모드)
 */

$menu          = $menu ?? hp_load_menu();
$current_bo_id = (int)($_GET['bo_id'] ?? 0);
$current_page  = $page ?? 'home';
$current_sec   = (int)hp_current_section();

$sections      = $menu['sections'] ?? [];
$has_guestbook = hp_config('guestbook_enabled', '0') === '1';

// ─── 핀 필터: 현재 섹션 + 공용 그룹의 핀만 ───
$pinned_filtered = [];
if (!empty($menu['pinned'])) {
    // 그룹 ID → mg_sec_id 매핑
    $group_sec = [];
    foreach ($menu['groups'] as $g) {
        $group_sec[(int)$g['mg_id']] = (int)($g['mg_sec_id'] ?? 0);
    }
    foreach ($menu['pinned'] as $b) {
        $gid = (int)($b['bo_mg_id'] ?? 0);
        if ($gid === 0) {
            // 그룹 미지정 = 공용
            $pinned_filtered[] = $b;
            continue;
        }
        if (!isset($group_sec[$gid])) continue;
        $gs = $group_sec[$gid];
        if ($gs === 0 || $gs === $current_sec) {
            $pinned_filtered[] = $b;
        }
    }
}

// 핀 슬롯 수 제약
$max_pins = max(0, 4 - ($has_guestbook ? 1 : 0));  // 홈 + 핀 + 방명록 + 더보기 = 5 슬롯
$pinned_filtered = array_slice($pinned_filtered, 0, $max_pins);
?>
<nav class="m-tabbar">
  <a href="<?= HP_BASE ?>/" class="m-tab <?= $current_page === 'home' ? 'active' : '' ?>">
    <span class="ic"><?= hp_icon('fas fa-house') ?></span>
    <span class="lbl">홈</span>
  </a>

  <?php foreach ($pinned_filtered as $b): ?>
    <a href="<?= hp_url('board', ['slug' => $b['bo_slug']]) ?>"
       class="m-tab <?= $current_bo_id == $b['bo_id'] ? 'active' : '' ?>">
      <span class="ic"><?= hp_icon($b['bo_icon'] ?: 'fas fa-bookmark') ?></span>
      <span class="lbl"><?= h($b['bo_name']) ?></span>
    </a>
  <?php endforeach; ?>

  <?php if ($has_guestbook): ?>
    <a href="<?= hp_url('guestbook') ?>" class="m-tab <?= $current_page === 'guestbook' ? 'active' : '' ?>">
      <span class="ic"><?= hp_icon('fas fa-comment-dots') ?></span>
      <span class="lbl">방명록</span>
    </a>
  <?php endif; ?>

  <button type="button" class="m-tab" id="moreTabBtn"
          data-section-count="<?= count($sections) ?>"
          onclick="window.hpOpenMore && window.hpOpenMore(this)">
    <span class="ic"><?= hp_icon('fas fa-ellipsis') ?></span>
    <span class="lbl">더보기</span>
  </button>
</nav>
