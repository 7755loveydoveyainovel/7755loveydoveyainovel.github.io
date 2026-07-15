<?php
/**
 * skins/board/memo/index.php — 핀터레스트 메모 게시판
 *
 * 4 카드 타입: quote / image / video / link
 * 작성: 보드 상단 [+ 새 메모] → 인라인 펼침 → 타입 선택 → 등록
 *
 * 데이터 매핑:
 *   po_category  : 'quote'|'image'|'video'|'link'
 *   po_title     : 출처 / 캡션 / 제목 / 제목
 *   po_subtitle  : 설명 (link 만)
 *   po_content   : 글귀 / -    / 영상URL / 링크URL
 *   po_thumbnail : -    / 이미지 / -      / 썸네일 (파일명 또는 https URL)
 *
 * 변수 (pages/board.php 가 전달):
 *   $board, $bo_id, $posts, $page_num, $total_pages, $total
 */

require_once __DIR__ . '/_helpers.php';
memo_handle_post($board, $bo_id);

$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

// 수정 모드 — ?edit=N (관리자만, 같은 보드 안)
$edit_post = null;
if (is_admin() && isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $edit_post = qOne("SELECT * FROM {post} WHERE po_id = ? AND po_bo_id = ?", [$edit_id, $bo_id]);
    }
}
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/memo/style.css?v=<?= filemtime(HP_PATH . '/skins/board/memo/style.css') ?>">

<div class="list-wrap memo-wrap">
  <div class="list-actions">
    <?php $cats = function_exists('hp_board_categories') ? hp_board_categories($board) : []; ?>
    <?php if ($cats): ?>
      <nav class="cat-nav">
        <a href="<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>"
           class="cat-tab <?= empty($current_cat) ? 'active' : '' ?>">전체</a>
        <?php foreach ($cats as $cat): ?>
          <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'cat' => $cat]) ?>"
             class="cat-tab <?= ($current_cat ?? '') === $cat ? 'active' : '' ?>"><?= h($cat) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php else: ?>
      <span class="meta">총 <?= number_format($total) ?>개</span>
    <?php endif; ?>
    <?php if (is_admin()): ?>
      <button type="button" class="btn-new" id="memoNewToggle">+ 새 메모</button>
    <?php endif; ?>
  </div>

  <?php if ($flash): ?>
    <div class="memo-flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <?php if (is_admin()) include __DIR__ . '/_form.php'; ?>

  <?php if (empty($posts)): ?>
    <div class="empty-msg">
      <?= is_admin() ? '아직 메모가 없어요. 우측 상단 [+ 새 메모] 로 시작해보세요.' : '아직 메모가 없어요.' ?>
    </div>
  <?php else: ?>
    <div class="memo-grid">
      <?php foreach ($posts as $p) include __DIR__ . '/_card.php'; ?>
    </div>
  <?php endif; ?>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page_num > 1): ?>
        <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'page' => $page_num - 1]) ?>">← 이전</a>
      <?php else: ?>
        <span class="pg-disabled">← 이전</span>
      <?php endif; ?>
      <span class="pg-info"><?= $page_num ?> / <?= $total_pages ?></span>
      <?php if ($page_num < $total_pages): ?>
        <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'page' => $page_num + 1]) ?>">다음 →</a>
      <?php else: ?>
        <span class="pg-disabled">다음 →</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<script src="<?= HP_BASE ?>/skins/board/memo/memo.js?v=<?= filemtime(HP_PATH . '/skins/board/memo/memo.js') ?>"></script>
