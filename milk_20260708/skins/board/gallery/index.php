<?php
/**
 * skins/board/gallery/index.php — 썸네일 갤러리 그리드
 *
 * 변수: $board, $posts, $page_num, $total_pages, $total
 */
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/gallery/style.css?v=<?= filemtime(HP_PATH . '/skins/board/gallery/style.css') ?>">

<div class="list-wrap gallery-wrap">
  <?php include HP_PATH . '/inc/board-actions.php'; ?>

  <div class="gallery-grid">
    <?php if (!$posts): ?>
      <div class="empty-msg">아직 작성된 글이 없어요.</div>
    <?php else: ?>
      <?php foreach ($posts as $p):
        $thumb   = $p['po_thumbnail'] ?? '';
        $initial = mb_substr($p['po_title'], 0, 1);
      ?>
        <a class="g-card" href="<?= hp_url('view', ['po_id' => $p['po_id']]) ?>">
          <div class="g-thumb">
            <?php if (!empty($p['po_is_private']) && !is_admin()): ?>
              <span class="g-lock"><?= hp_icon('fas fa-lock') ?></span>
            <?php elseif ($thumb): ?>
              <img src="<?= HP_BASE ?>/data/posts/<?= h($thumb) ?>" alt="<?= h($p['po_title']) ?>" loading="lazy">
            <?php else: ?>
              <span class="g-initial"><?= h($initial) ?></span>
            <?php endif; ?>
          </div>
          <div class="g-info">
            <?php if (!empty($p['po_category'])): ?>
              <span class="post-cat-chip"><?= h($p['po_category']) ?></span>
            <?php endif; ?>
            <div class="g-title">
              <?php if (!empty($p['po_is_private'])): ?><span class="post-lock-icon"><?= hp_icon('fas fa-lock') ?></span><?php endif; ?>
              <?= h($p['po_title']) ?>
            </div>
            <?php if (!empty($p['po_subtitle'])): ?>
              <div class="g-subtitle"><?= h($p['po_subtitle']) ?></div>
            <?php endif; ?>
            <div class="g-meta"><?= h(date('Y.m.d', strtotime($p['po_created_at']))) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page_num > 1): ?>
        <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'page_num' => $page_num - 1]) ?>">← 이전</a>
      <?php else: ?>
        <span class="pg-disabled">← 이전</span>
      <?php endif; ?>
      <span class="pg-info"><?= $page_num ?> / <?= $total_pages ?></span>
      <?php if ($page_num < $total_pages): ?>
        <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'page_num' => $page_num + 1]) ?>">다음 →</a>
      <?php else: ?>
        <span class="pg-disabled">다음 →</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
