<?php
/**
 * inc/post-locked.php — 비공개 글 잠금 화면
 *
 * 사용법: pages/view.php 의 잠금 체크 블록에서 include
 * 필요 변수: $post, $board, $po_id, $unlock_error
 */
?>
<div class="list-wrap">
  <div class="list-actions">
    <a href="<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>" class="btn-list">목록</a>
  </div>

  <div class="post-locked">
    <div class="post-locked-icon"><?= hp_icon('fas fa-lock') ?></div>
    <div class="post-locked-title">비공개 글</div>
    <div class="post-locked-meta">
      <?= h($board['bo_name']) ?> · <?= h(date('Y년 m월 d일', strtotime($post['po_created_at']))) ?>
    </div>
    <div class="post-locked-hint">이 글은 비밀번호가 설정되어 있어요. 비밀번호를 입력하면 내용을 볼 수 있습니다.</div>

    <?php if ($unlock_error): ?>
      <div class="post-locked-error"><?= h($unlock_error) ?></div>
    <?php endif; ?>

    <form method="post" class="post-locked-form" action="<?= hp_url('view', ['po_id' => $po_id]) ?>">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="unlock_post">
      <input type="password" name="post_pw" placeholder="비밀번호" autocomplete="current-password" autofocus required>
      <button type="submit" class="btn-submit">
        <span class="lbl-text">잠금 해제</span>
        <span class="lbl-icon"><?= hp_icon('fas fa-unlock') ?></span>
      </button>
    </form>
  </div>
</div>
