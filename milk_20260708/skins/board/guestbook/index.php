<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/guestbook/style.css?v=<?= filemtime(HP_PATH . '/skins/board/guestbook/style.css') ?>">

<?php if ($flash): ?>
  <div class="gb-flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<!-- 작성 폼 -->
<form method="post" class="gb-form <?= is_admin() ? 'admin' : '' ?>">
  <?= csrf_input() ?>
  <input type="hidden" name="action" value="new_entry">

  <?php if (is_admin()): ?>
    <!-- 관리자 — 공지 옵션 -->
    <div class="row">
      <span class="admin-name">
        <?= h(hp_config('admin_nickname', '') ?: hp_config('site_name', '주인장')) ?>
        <span class="admin-tag">관리자</span>
      </span>
      <label class="private-toggle pin-toggle">
        <input type="checkbox" name="pinned" value="1">
        <span class="ico"><?= hp_icon('fas fa-bullhorn') ?></span><span class="lbl">공지</span>
      </label>
    </div>
  <?php else: ?>
    <!-- 방문자 — 이름 + 비번 + 비공개 -->
    <div class="row">
      <input type="text" name="name" placeholder="이름" required maxlength="40" class="name">
      <input type="password" name="password" placeholder="비밀번호 (선택, 비공개·수정·삭제용)" maxlength="40" class="pw">
      <label class="private-toggle">
        <input type="checkbox" name="private" value="1">
        <span class="ico"><?= hp_icon('fas fa-lock') ?></span><span class="lbl">비공개</span>
      </label>
    </div>
  <?php endif; ?>

  <textarea name="content" placeholder="<?= is_admin() ? '방명록에 글을 남겨보세요.' : '방명록을 남겨보세요.' ?>" required maxlength="2000"></textarea>
  <button type="submit"><?= is_admin() ? '등록' : '남기기' ?></button>
</form>

<div class="crumb chip">
  <span class="path"><strong>방명록</strong></span>
  <span class="meta">총 <?= number_format($total) ?>개</span>
</div>

<!-- 목록 -->
<div class="gb-list">
  <?php if (!$entries): ?>
    <div class="empty-msg">아직 방명록이 없어요. 첫 글을 남겨보세요!</div>
  <?php else: foreach ($entries as $e):
    $is_unlocked = in_array((int)$e['gb_id'], $_SESSION['gb_unlocked'], true);
    $can_view    = !$e['gb_is_private'] || $is_unlocked || is_admin();
    $is_pinned   = !empty($e['gb_is_pinned']);
  ?>
    <div class="gb-entry <?= $e['gb_is_private'] ? 'private' : '' ?> <?= $is_pinned ? 'pinned' : '' ?>">
      <div class="head">
        <?php if ($is_pinned): ?>
          <span class="pin-badge"><?= hp_icon('fas fa-bullhorn') ?> 공지</span>
        <?php endif; ?>
        <span class="name"><?= h($e['gb_name']) ?></span>
        <?php if ($e['gb_is_private']): ?>
          <span class="lock"><?= hp_icon('fas fa-lock') ?></span>
        <?php endif; ?>
        <span class="date"><?= h(date('Y.m.d H:i', strtotime($e['gb_created_at']))) ?></span>

        <?php /* 수정 버튼 — 본문을 볼 수 있을 때만 (비공개 글은 잠금 해제 후) */ ?>
        <?php if ($can_view && (is_admin() || $e['gb_pw_hash'])): ?>
          <button type="button" class="entry-action-btn" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='block'?'none':'block'">수정</button>
          <form method="post" class="edit-form" style="display:none">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="gb_id" value="<?= $e['gb_id'] ?>">
            <textarea name="content" required maxlength="2000"><?= h($e['gb_content']) ?></textarea>
            <div class="edit-row">
              <?php if (!is_admin()): ?>
                <input type="password" name="password" placeholder="비밀번호" required>
              <?php endif; ?>
              <button type="submit">저장</button>
            </div>
          </form>
        <?php endif; ?>

        <?php /* 삭제 버튼 — 본문 노출 없으므로 잠금과 무관 */ ?>
        <?php if (is_admin() || $e['gb_pw_hash']): ?>
          <button type="button" class="entry-action-btn" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='block'?'none':'block'">삭제</button>
          <form method="post" class="del-form" style="display:none">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="gb_id" value="<?= $e['gb_id'] ?>">
            <?php if (!is_admin()): ?>
              <input type="password" name="password" placeholder="비밀번호" required>
            <?php endif; ?>
            <button type="submit" onclick="return confirm('정말 삭제할까요?')">확인</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($can_view): ?>
        <div class="body"><?= h($e["gb_content"]) ?></div>
      <?php else: ?>
        <div class="body locked">
          <span class="placeholder"><?= hp_icon('fas fa-lock') ?> 비공개 메시지입니다.</span>
          <?php if ($e['gb_pw_hash']): ?>
            <form method="post" class="unlock-form">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="unlock">
              <input type="hidden" name="gb_id" value="<?= $e['gb_id'] ?>">
              <input type="password" name="password" placeholder="비밀번호" required>
              <button type="submit">열기</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- 답글 -->
      <?php if (!empty($replies_by_parent[$e['gb_id']])):
        foreach ($replies_by_parent[$e['gb_id']] as $r): ?>
        <?php if ($can_view): ?>
          <div class="gb-reply">
            <div class="head">
              <span class="arrow">↳</span>
              <span class="name"><?= h($r['gb_name']) ?></span>
              <span class="date"><?= h(date('Y.m.d H:i', strtotime($r['gb_created_at']))) ?></span>
              <?php if (is_admin()): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('답글을 삭제할까요?')">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="gb_id" value="<?= $r['gb_id'] ?>">
                  <button type="submit" class="reply-del">×</button>
                </form>
              <?php endif; ?>
            </div>
            <div class="body"><?= h($r["gb_content"]) ?></div>
          </div>
        <?php endif; ?>
      <?php endforeach; endif; ?>

      <!-- 답글 폼 — 비공개 글은 잠금 해제한 사람과 관리자만 -->
      <?php if ($can_view): ?>
        <form method="post" class="reply-form <?= is_admin() ? 'admin' : '' ?>">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="reply">
          <input type="hidden" name="parent_id" value="<?= $e['gb_id'] ?>">
          <?php if (!is_admin()): ?>
            <div class="reply-row">
              <input type="text" name="name" placeholder="이름" required maxlength="40" class="r-name">
              <input type="password" name="password" placeholder="비밀번호 (선택)" maxlength="40" class="r-pw">
            </div>
          <?php endif; ?>
          <div class="reply-row">
            <textarea name="content" placeholder="답글 남기기" required maxlength="2000"></textarea>
            <button type="submit">답글</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>

<!-- 페이지네이션 -->
<?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php if ($page_num > 1): ?>
      <a href="<?= hp_url('guestbook', ['page_num' => $page_num - 1]) ?>">← 이전</a>
    <?php else: ?>
      <span class="pg-disabled">← 이전</span>
    <?php endif; ?>
    <span class="pg-info"><?= $page_num ?> / <?= $total_pages ?></span>
    <?php if ($page_num < $total_pages): ?>
      <a href="<?= hp_url('guestbook', ['page_num' => $page_num + 1]) ?>">다음 →</a>
    <?php else: ?>
      <span class="pg-disabled">다음 →</span>
    <?php endif; ?>
  </div>
<?php endif; ?>
