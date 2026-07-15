<?php
/**
 * inc/post-view.php — [DEPRECATED] 게시글 상세 + 댓글 (공통)
 *
 * 이 partial 은 v1.6.x 까지 모든 스킨이 공유하는 글 상세 마크업이었으나,
 * v1.7.0 부터 각 스킨이 자기 view.php 에 직접 마크업을 가지고 댓글 영역만
 * `inc/post-comments.php` 를 include 하는 방식으로 변경됨.
 *
 * 새 스킨은 `skins/board/list/view.php` 를 참고해서 만들 것.
 * 이 파일은 backward compat 용으로만 남아 있음.
 *
 * 필요 변수: $post, $board, $comments, $flash
 */
?>
<div class="list-wrap">
  <?php include HP_PATH . '/inc/post-actions.php'; ?>

  <article class="post-view">
    <?php $post_images = hp_post_images($post); if ($post_images): ?>
      <div class="post-images <?= count($post_images) > 1 ? 'multi' : 'single' ?>">
        <?php foreach ($post_images as $img): ?>
          <div class="post-image">
            <img src="<?= HP_BASE ?>/data/posts/<?= h($img) ?>" alt="<?= h($post['po_title']) ?>" loading="lazy">
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <header class="post-head">
      <?php if (!empty($post['po_category'])): ?>
        <div class="post-category"><?= h($post['po_category']) ?></div>
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

  <section class="comments">
    <h2 class="comments-head">댓글 <span class="count"><?= count($comments) ?></span></h2>

    <?php if (!empty($flash)): ?>
      <div class="cm-flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
    <?php endif; ?>

    <?php if ($comments):
      // 트리 빌드 — 1단계 깊이만 (top + replies)
      $top_comments = [];
      $children_map = [];
      foreach ($comments as $_c) {
          if (!empty($_c['cm_parent_id'])) {
              $children_map[(int)$_c['cm_parent_id']][] = $_c;
          } else {
              $top_comments[] = $_c;
          }
      }
    ?>
      <div class="comment-list">
        <?php foreach ($top_comments as $top):
          $c = $top;       // partial 이 $c 를 기대함
          $is_top = true;
          $kids = $children_map[(int)$top['cm_id']] ?? [];
        ?>
          <div class="comment-thread">
            <?php include HP_PATH . '/inc/comment-item.php'; ?>

            <?php if ($kids): ?>
              <div class="comment-replies">
                <?php foreach ($kids as $c): $is_top = false; ?>
                  <?php include HP_PATH . '/inc/comment-item.php'; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- 댓글 폼 — 관리자 / 방문자 분기 -->
    <form method="post" class="comment-form <?= is_admin() ? 'admin' : '' ?>">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="comment">

      <?php if (is_admin()): ?>
        <div class="row">
          <span class="admin-name">
            <?= h(hp_config('admin_nickname', '') ?: hp_config('site_name', '주인장')) ?>
            <span class="admin-tag">관리자</span>
          </span>
        </div>
      <?php else: ?>
        <div class="row">
          <input type="text" name="name" placeholder="이름" required maxlength="40" class="cm-name">
          <input type="password" name="password" placeholder="비밀번호 (선택, 비공개용)" maxlength="40" class="cm-pw">
          <label class="private-toggle">
            <input type="checkbox" name="private" value="1">
            <span class="ico"><?= hp_icon('fas fa-lock') ?></span><span class="lbl">비공개</span>
          </label>
        </div>
      <?php endif; ?>

      <textarea name="content" placeholder="댓글을 남겨보세요" required maxlength="1000"></textarea>
      <button type="submit">댓글 등록</button>
    </form>
  </section>
</div>
