<?php
/**
 * inc/board-locked.php — 비밀번호 보호 게시판 잠금 화면
 *
 * 필요 변수: $board, $unlock_error
 */
?>
<div class="list-wrap">
  <div class="post-locked">
    <div class="post-locked-icon"><?= hp_icon('fas fa-key') ?></div>
    <div class="post-locked-title"><?= h($board['bo_name']) ?></div>
    <div class="post-locked-hint">이 게시판은 비밀번호로 보호되어 있어요.</div>

    <?php if (!empty($unlock_error)): ?>
      <div class="post-locked-error"><?= h($unlock_error) ?></div>
    <?php endif; ?>

    <form method="post" class="post-locked-form" action="<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="unlock_board">
      <input type="password" name="board_pw" placeholder="비밀번호" autocomplete="current-password" autofocus required>
      <button type="submit" class="btn-submit">
        <span class="lbl-text">입장</span>
        <span class="lbl-icon"><?= hp_icon('fas fa-door-open') ?></span>
      </button>
    </form>
  </div>
</div>
