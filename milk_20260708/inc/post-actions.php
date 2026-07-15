<?php
/**
 * inc/post-actions.php — 게시글 보기 공통 액션바 (목록 / 공유 / 수정 / 삭제)
 *
 * 사용법: 스킨의 view.php 에서 `<?php include HP_PATH . '/inc/post-actions.php'; ?>`
 * 필요 변수: $post, $board (스코프에서 자동 인식)
 */

// share URL 구성 (절대 URL — 클립보드 복사용)
$_scheme    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$_share_url = $_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . HP_BASE . '/share.php?po_id=' . (int)$post['po_id'];
?>
<div class="list-actions">
  <a href="<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>" class="btn-list">목록</a>
  <?php if (empty($board['bo_admin_only']) && empty($post['po_is_private']) && !hp_board_is_protected($board)): ?>
    <button type="button" class="btn-list" onclick="spaShare('<?= h($_share_url) ?>', this)">공유</button>
  <?php endif; ?>
  <?php if (is_admin()): ?>
    <a href="<?= hp_url('edit', ['po_id' => $post['po_id']]) ?>" class="btn-new">수정</a>
    <form method="post" action="<?= hp_url('edit', ['po_id' => $post['po_id']]) ?>" style="display:inline" onsubmit="return confirm('정말 삭제하시겠어요? 되돌릴 수 없습니다.')">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="delete">
      <button type="submit" class="btn-del">삭제</button>
    </form>
  <?php endif; ?>
</div>
