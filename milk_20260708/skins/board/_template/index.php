<?php
/**
 * skins/board/_template/index.php — 게시판 목록 페이지
 *
 * pages/board.php 가 전달하는 변수:
 *   $board       — 게시판 행 (bo_id, bo_slug, bo_name, bo_skin, bo_categories, ...)
 *   $bo_id       — 게시판 ID (int)
 *   $posts       — 글 배열. 각 행: po_id, po_title, po_subtitle, po_content,
 *                  po_thumbnail, po_category, po_extra, po_views,
 *                  po_created_at, po_is_private
 *   $page_num    — 현재 페이지 번호 (1~)
 *   $total_pages — 전체 페이지 수
 *   $total       — 전체 글 수
 *   $current_cat — 선택된 카테고리 (없으면 빈 문자열)
 */
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/_template/style.css?v=<?= filemtime(HP_PATH . '/skins/board/_template/style.css') ?>">

<div class="list-wrap">
  <!-- 카테고리 네비 + 새 글 버튼 (공통 컴포넌트) -->
  <?php include HP_PATH . '/inc/board-actions.php'; ?>

  <!-- 글 목록 -->
  <?php if (empty($posts)): ?>
    <div class="empty-msg">아직 글이 없어요.</div>
  <?php else: ?>
    <div class="tpl-list">
      <?php foreach ($posts as $p): ?>
        <a class="tpl-item" href="<?= hp_url('view', ['po_id' => $p['po_id']]) ?>">
          <?php if (!empty($p['po_category'])): ?>
            <span class="post-cat-chip"><?= h($p['po_category']) ?></span>
          <?php endif; ?>
          <div class="tpl-title"><?= h($p['po_title']) ?></div>
          <div class="tpl-date"><?= h(date('Y.m.d', strtotime($p['po_created_at']))) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- 페이지네이션 -->
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
