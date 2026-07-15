<?php
/**
 * skins/board/list/view.php — list 스킨의 글 상세 (자기종결)
 *
 * 스킨이 본문 마크업을 직접 가짐. 디자인/레이아웃 변경은 이 파일과 style.css
 * 만 만지면 됨. 댓글 영역은 inc/post-comments.php 공통 partial 사용.
 *
 * pages/view.php 가 전달하는 변수: $post, $board, $comments, $flash
 */
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/list/style.css?v=<?= filemtime(HP_PATH . '/skins/board/list/style.css') ?>">

<div class="list-wrap">
  <?php include HP_PATH . '/inc/post-actions.php'; ?>

  <article class="post-view">

    <header class="post-head">
      <?php if (!empty($post['po_category'])): ?>
        <span class="post-cat-chip"><?= h($post['po_category']) ?></span>
      <?php endif; ?>
      <h1 class="post-title"><?= h($post['po_title']) ?></h1>
      <?php if (!empty($post['po_subtitle'])): ?>
        <div class="post-subtitle"><?= h($post['po_subtitle']) ?></div>
      <?php endif; ?>
      <div class="post-meta">
        <span><?= h(date('Y년 m월 d일', strtotime($post['po_created_at']))) ?></span>
        <span class="dot">·</span>
        <span>조회 <?= number_format($post['po_views']) ?></span>
        <?php if ($post['po_updated_at'] && $post['po_updated_at'] !== $post['po_created_at']): ?>
          <span class="dot">·</span>
          <span>수정됨</span>
        <?php endif; ?>
      </div>
    </header>
    
    <?php list($body_html, $top_images) = hp_render_post_body($post, $board); ?>

    <?php if ($top_images): ?>
      <div class="post-images <?= count($top_images) > 1 ? 'multi' : 'single' ?>">
        <?php foreach ($top_images as $img): ?>
          <div class="post-image">
            <img src="<?= h(hp_post_image_url($img, $board)) ?>"
                 alt="<?= h($post['po_title']) ?>" loading="lazy">
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="post-body markdown-body"><?= $body_html ?></div>
  </article>

  <?php include HP_PATH . '/inc/post-comments.php'; ?>
</div>
