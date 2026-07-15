<?php
/**
 * skins/board/list/index.php — 게시글 리스트 (텍스트 중심)
 *
 * 변수 (pages/board.php 가 전달):
 *   $board, $posts, $page_num, $total_pages, $total
 */
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/list/style.css?v=<?= filemtime(HP_PATH . '/skins/board/list/style.css') ?>">

<div class="list-wrap">
  <?php include HP_PATH . '/inc/board-actions.php'; ?>

  <div class="post-list">
    <?php if (!$posts): ?>
      <div class="empty-msg">아직 작성된 글이 없어요.<?php if (is_admin()): ?><br>우측 상단 <strong>+ 새 글</strong> 버튼으로 시작해보세요.<?php endif; ?></div>
    <?php else: ?>
      <?php foreach ($posts as $p): ?>
        <a class="post" href="<?= hp_url('view', ['po_id' => $p['po_id']]) ?>">
          <div class="date">
            <b><?= h(date('m.d', strtotime($p['po_created_at']))) ?></b>
            <?= h(date('Y', strtotime($p['po_created_at']))) ?>
          </div>
          <div class="post-body-col">
            <?php if (!empty($p['po_category'])): ?>
              <span class="post-cat-chip"><?= h($p['po_category']) ?></span>
            <?php endif; ?>
            <div class="title">
              <?php if (!empty($p['po_is_private'])): ?><span class="post-lock-icon"><?= hp_icon('fas fa-lock') ?></span><?php endif; ?>
              <?= h($p['po_title']) ?>
            </div>
            <?php if (!empty($p['po_subtitle'])): ?>
              <div class="subtitle"><?= h($p['po_subtitle']) ?></div>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page_num > 1): ?>
        <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'page' => $page_num - 1]) ?>">← 이전</a>
      <?php else: ?>
        <span class="pg-disabled">← 이전</span>
      <?php endif; ?>
      <span class="pg-info"><?= $page_num ?> / <?= $total_pages ?></span>
      <?php if ($page_num < $total_pages): ?>
        <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'page' => $page_num + 1]) ?>">다음 →</a>
      <?php else: ?>
        <span class="pg-disabled">다음 →</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
