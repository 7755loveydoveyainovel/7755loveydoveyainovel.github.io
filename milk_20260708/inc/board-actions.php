<?php
/**
 * inc/board-actions.php — 게시판 리스트 페이지의 공통 액션바
 *
 * 사용법: 스킨의 index.php 에서 `<?php include HP_PATH . '/inc/board-actions.php'; ?>`
 *
 * 카테고리가 정의된 보드면 카테고리 탭, 아니면 메타 칩.
 * 우측엔 + 새 글 (관리자만).
 *
 * 필요 변수 (스코프에서 자동 인식): $board, $total, $current_cat
 */
?>
<div class="list-actions">
  <?php $cats = hp_board_categories($board); if ($cats): ?>
    <nav class="cat-nav">
      <a href="<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>"
         class="cat-tab <?= empty($current_cat) ? 'active' : '' ?>">전체</a>
      <?php foreach ($cats as $cat): ?>
        <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'cat' => $cat]) ?>"
           class="cat-tab <?= ($current_cat ?? '') === $cat ? 'active' : '' ?>"><?= h($cat) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php else: ?>
    <span class="meta">총 <?= number_format($total) ?>편 · 최신순</span>
  <?php endif; ?>
  <?php if (is_admin()): ?>
    <a href="<?= hp_url('write', ['slug' => $board['bo_slug']]) ?>" class="btn-new">+ 새 글</a>
  <?php endif; ?>
</div>
