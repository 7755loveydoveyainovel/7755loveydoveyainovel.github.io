<?php
/**
 * inc/comment-item.php — 댓글 한 개 렌더 (최상단 / 대댓글 공통)
 *
 * 필요 변수:
 *   $c       — 댓글 row
 *   $is_top  — 최상단 댓글 여부 (true 이면 답글 버튼/폼 표시, false 이면 미표시)
 *
 * 답글은 1 depth 만 — 대댓글에는 답글 버튼이 안 붙음.
 */
$cm_unlocked = in_array((int)$c['cm_id'], $_SESSION['cm_unlocked'] ?? [], true);
$cm_can_view = !$c['cm_is_private'] || $cm_unlocked || is_admin();
$is_top      = $is_top ?? false;
?>
<div class="comment <?= $c['cm_is_private'] ? 'private' : '' ?>">
  <div class="head">
    <span class="who"><?= h($c['cm_name']) ?></span>
    <?php if ($c['cm_is_private']): ?>
      <span class="lock"><?= hp_icon('fas fa-lock') ?></span>
    <?php endif; ?>
    <span class="when"><?= h(date('Y.m.d H:i', strtotime($c['cm_created_at']))) ?></span>

    <?php /* 답글 — 최상단 댓글에서만, 본문을 볼 수 있을 때만 */ ?>
    <?php if ($is_top && $cm_can_view): ?>
      <button type="button" class="entry-action-btn"
              onclick="var f=this.closest('.comment').querySelector('.reply-form');f.style.display=f.style.display==='block'?'none':'block'">답글</button>
    <?php endif; ?>

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

    <?php /* 삭제 */ ?>
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

  <?php /* 답글 폼 — 최상단 댓글에서만, 답글 버튼이 토글 */ ?>
  <?php if ($is_top): ?>
    <form method="post" class="reply-form <?= is_admin() ? 'admin' : '' ?>" style="display:none">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="comment">
      <input type="hidden" name="parent_id" value="<?= (int)$c['cm_id'] ?>">
      <?php if (!is_admin()): ?>
        <div class="row">
          <input type="text" name="name" placeholder="이름" required maxlength="40" class="cm-name">
          <input type="password" name="password" placeholder="비밀번호 (선택)" maxlength="40" class="cm-pw">
          <label class="private-toggle">
            <input type="checkbox" name="private" value="1">
            <span class="ico"><?= hp_icon('fas fa-lock') ?></span><span class="lbl">비공개</span>
          </label>
        </div>
      <?php endif; ?>
      <textarea name="content" placeholder="답글을 남겨보세요" required maxlength="1000"></textarea>
      <button type="submit">답글 등록</button>
    </form>
  <?php endif; ?>
</div>
