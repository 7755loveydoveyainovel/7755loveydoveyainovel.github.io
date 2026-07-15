<?php
/**
 * skins/board/gallery/view.php — gallery 스킨의 글 상세 (자기종결)
 *
 * 디자인/레이아웃 변경은 이 파일 + style.css 만. 댓글은 공통 partial.
 *
 * 변수: $post, $board, $comments, $flash
 */
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/gallery/style.css?v=<?= filemtime(HP_PATH . '/skins/board/gallery/style.css') ?>">

<div class="list-wrap gallery-view">
  <?php include HP_PATH . '/inc/post-actions.php'; ?>

  <article class="post-view">
    <?php $post_images = hp_post_images($post); if ($post_images): ?>
      <div class="post-images <?= count($post_images) > 1 ? 'multi' : 'single' ?>">
        <?php foreach ($post_images as $img): ?>
          <div class="post-image">
            <img src="<?= h(hp_post_image_url($img, $board)) ?>"
                 alt="<?= h($post['po_title']) ?>" loading="lazy">
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

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

    <div class="post-body markdown-body"><?= hp_render_markdown($post['po_content']) ?></div>
  </article>

  <?php include HP_PATH . '/inc/post-comments.php'; ?>
</div>
