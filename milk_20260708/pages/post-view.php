<?php
/**
 * inc/post-view.php — 게시글 상세 + 댓글 (공통)
 *
 * 모든 게시판 스킨에서 공유하는 글 상세 화면.
 * 사용법: 스킨의 view.php 에서 `<?php include HP_PATH . '/inc/post-view.php'; ?>`
 *
 * 필요 변수 (스코프에서 자동 인식): $post, $board, $comments, $flash
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
    <div class="post-body"><?= h($post['po_content']) ?></div>
  </article>

  <section class="comments">
    <h2 class="comments-head">댓글 <span class="count"><?= count($comments) ?></span></h2>

    <?php if (!empty($flash)): ?>
      <div class="cm-flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
    <?php endif; ?>

    <?php if ($comments): ?>
      <div class="comment-list">
        <?php foreach ($comments as $c):
          $cm_unlocked = in_array((int)$c['cm_id'], $_SESSION['cm_unlocked'] ?? [], true);
          $cm_can_view = !$c['cm_is_private'] || $cm_unlocked || is_admin();
        ?>
          <div class="comment <?= $c['cm_is_private'] ? 'private' : '' ?>">
            <div class="head">
              <span class="who"><?= h($c['cm_name']) ?></span>
              <?php if ($c['cm_is_private']): ?>
                <span class="lock"><?= hp_icon('fas fa-lock') ?></span>
              <?php endif; ?>
              <span class="when"><?= h(date('Y.m.d H:i', strtotime($c['cm_created_at']))) ?></span>

              <?php /* 수정 — 본문을 볼 수 있을 때만 */ ?>
              <?php if ($cm_can_view && (is_admin() || $c['cm_pw_hash'])): ?>
                <button type="button" class="entry-action-btn" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='block'?'none':'block'">수정</button>
                <form method="post" class="edit-form" style="display:none">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="edit_comment">
                  <input type="hidden" name="cm_id" value="<?= $c['cm_id'] ?>">
                  <textarea name="content" required maxlength="1000"><?= h($c['cm_content']) ?></textarea>
                  <div class="edit-row">
                    <?php if (!is_admin()): ?>
                      <input type="password" name="password" placeholder="비밀번호" required>
                    <?php endif; ?>
                    <button type="submit">저장</button>
                  </div>
                </form>
              <?php endif; ?>

              <?php /* 삭제 — 본문 노출 없음, 잠금과 무관 */ ?>
              <?php if (is_admin() || $c['cm_pw_hash']): ?>
                <button type="button" class="entry-action-btn" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='block'?'none':'block'">삭제</button>
                <form method="post" class="del-form" style="display:none">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="delete_comment">
                  <input type="hidden" name="cm_id" value="<?= $c['cm_id'] ?>">
                  <?php if (!is_admin()): ?>
                    <input type="password" name="password" placeholder="비밀번호" required>
                  <?php endif; ?>
                  <button type="submit" onclick="return confirm('정말 삭제할까요?')">확인</button>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($cm_can_view): ?>
              <div class="body"><?= h($c['cm_content']) ?></div>
            <?php else: ?>
              <div class="body locked">
                <span class="placeholder"><?= hp_icon('fas fa-lock') ?> 비공개 댓글입니다.</span>
                <?php if ($c['cm_pw_hash']): ?>
                  <form method="post" class="unlock-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="unlock_comment">
                    <input type="hidden" name="cm_id" value="<?= $c['cm_id'] ?>">
                    <input type="password" name="password" placeholder="비밀번호" required>
                    <button type="submit">열기</button>
                  </form>
                <?php endif; ?>
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
