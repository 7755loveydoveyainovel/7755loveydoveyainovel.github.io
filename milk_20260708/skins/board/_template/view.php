<?php
/**
 * skins/board/_template/view.php — 글 상세 페이지
 *
 * pages/view.php 가 전달하는 변수:
 *   $post     — 글 행 (po_id, po_title, po_subtitle, po_content, po_thumbnail,
 *               po_category, po_extra, po_views, po_created_at, po_updated_at,
 *               po_is_private, po_pw_hash, po_bo_id)
 *   $board    — 게시판 행
 *   $comments — 댓글 배열
 *   $flash    — 일회성 알림 ['type' => 'success'|'error', 'msg' => '...'] 또는 null
 */
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/_template/style.css?v=<?= filemtime(HP_PATH . '/skins/board/_template/style.css') ?>">

<div class="list-wrap">
  <!-- 목록/공유/수정/삭제 버튼 (공통 컴포넌트) -->
  <?php include HP_PATH . '/inc/post-actions.php'; ?>

  <article class="tpl-view">
    <header class="tpl-view-head">
      <?php if (!empty($post['po_category'])): ?>
        <span class="post-cat-chip"><?= h($post['po_category']) ?></span>
      <?php endif; ?>
      <h1><?= h($post['po_title']) ?></h1>
      <?php if (!empty($post['po_subtitle'])): ?>
        <div class="tpl-subtitle"><?= h($post['po_subtitle']) ?></div>
      <?php endif; ?>
      <div class="tpl-meta">
        <?= h(date('Y년 m월 d일', strtotime($post['po_created_at']))) ?>
        · 조회 <?= number_format($post['po_views']) ?>
      </div>
    </header>

    <!-- 이미지 (po_extra.images 또는 po_thumbnail) -->
    <?php $images = hp_post_images($post); if ($images): ?>
      <div class="tpl-images">
        <?php foreach ($images as $img): ?>
          <img src="<?= h(hp_post_image_url($img, $board)) ?>"
               alt="<?= h($post['po_title']) ?>" loading="lazy">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- 본문 (마크다운 렌더링) -->
    <div class="tpl-body markdown-body"><?= hp_render_markdown($post['po_content']) ?></div>
  </article>

  <!-- 댓글 (공통 컴포넌트) -->
  <?php include HP_PATH . '/inc/post-comments.php'; ?>
</div>
