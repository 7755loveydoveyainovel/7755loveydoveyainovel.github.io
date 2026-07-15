<?php
/**
 * blocks/recent.php — 최근 글
 *
 * 옵션:
 *   - limit  (int, default 6)
 *   - boards (int[], default null) — 특정 게시판만, null 이면 전체
 *   - title  (string, default '최근 글')
 */

$opts = $opts ?? [];
$limit  = max(1, min(20, (int)($opts['limit'] ?? 6)));
$bo_ids = (!empty($opts['boards']) && is_array($opts['boards'])) ? $opts['boards'] : null;
$title  = $opts['title'] ?? '최근 글';

$posts = hp_get_recent_posts($limit, $bo_ids);
?>
<div class="home-card recent">
  <?php if (is_admin()): ?>
    <span class="drag-handle">drag_indicator</span>
  <?php endif; ?>

  <div class="home-block-label"><?= h($title) ?></div>

  <?php if (!$posts): ?>
    <div class="empty-msg">아직 작성된 글이 없어요.</div>
  <?php else: ?>
    <?php
      // ─── 상세 뷰가 없는 스킨들 (board 페이지로 직접 이동) ───
      // 새 무뷰 스킨 추가 시 이 두 array 에 한 줄씩만 추가.
      $_no_view_skins  = ['memo', 'night'];
      $_no_view_labels = ['memo' => '(메모)', 'night' => '(꿈)'];
    ?>
    <?php foreach ($posts as $p):
      $_skin = $p['bo_skin'] ?? '';
      $href = in_array($_skin, $_no_view_skins, true)
          ? hp_url('board', ['slug' => $p['bo_slug']])
          : hp_url('view', ['po_id' => $p['po_id']]);
      // 무뷰 스킨은 po_title 이 비어있을 수 있음 → 스킨별 폴백
      $label = $p['po_title'] !== ''
          ? $p['po_title']
          : ($_no_view_labels[$_skin] ?? '(글)');
    ?>
      <a href="<?= $href ?>" class="recent-item">
        <span class="cat"><?= h($p['bo_name']) ?></span>
        <span class="t"><?= h($label) ?></span>
        <span class="d"><?= h(date('Y.m.d', strtotime($p['po_created_at']))) ?></span>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
