<?php
/**
 * skins/board/dream/index.php — 드림/페어 카드 갤러리
 *
 * 모드:
 *   기본       : 페어 카드 그리드
 *   ?write=1   : 새 드림 작성 (관리자, 1-C 에서 _form 추가)
 *   ?edit=N    : 드림 수정 (관리자, 1-C)
 *
 * 변수 (pages/board.php 전달):
 *   $board, $bo_id, $posts, $page_num, $total_pages, $total
 */

require_once __DIR__ . '/_helpers.php';
dr_handle_post($board, $bo_id);

$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

// ─── 모드 결정 ───
$mode = 'list';
$edit_post = null;

if (is_admin()) {
    if (isset($_GET['edit'])) {
        $edit_id = (int)$_GET['edit'];
        if ($edit_id > 0) {
            $edit_post = qOne("SELECT * FROM {post} WHERE po_id = ? AND po_bo_id = ?", [$edit_id, $bo_id]);
            if ($edit_post) $mode = 'edit';
        }
    } elseif (isset($_GET['write'])) {
        $mode = 'write';
    }
}
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/dream/style.css?v=<?= filemtime(HP_PATH . '/skins/board/dream/style.css') ?>">

<div class="list-wrap dream-wrap">

  <?php if ($flash): ?>
    <div class="dr-flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <?php if ($mode === 'write' || $mode === 'edit'): ?>
    <?php
    // 1-C 에서 _form.php 추가 예정. 그 전까지 안내.
    if (is_file(__DIR__ . '/_form.php')) {
        include __DIR__ . '/_form.php';
    } else {
        echo '<div class="empty-msg">드림 작성 폼은 다음 단계(1-C)에서 제공됩니다.</div>';
    }
    ?>

  <?php else: /* ─── list 모드 ─── */ ?>
    <?php
    $cats = function_exists('hp_board_categories') ? hp_board_categories($board) : [];
    $current_cat = trim($_GET['cat'] ?? '');
    $is_pro = (defined('DREAM_TIER') && DREAM_TIER === 'pro');
    ?>
    <div class="list-actions">
      <?php if ($cats): ?>
        <nav class="cat-nav">
          <a href="<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>"
             class="cat-tab <?= empty($current_cat) ? 'active' : '' ?>">전체</a>
          <?php foreach ($cats as $cat): ?>
            <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'cat' => $cat]) ?>"
               class="cat-tab <?= $current_cat === $cat ? 'active' : '' ?>"><?= h($cat) ?></a>
          <?php endforeach; ?>
        </nav>
      <?php else: ?>
        <span class="meta">총 <?= number_format($total) ?>개</span>
      <?php endif; ?>

      <div class="dr-actions-right">
        <?php if ($is_pro): ?>
          <a class="dr-btn-game" href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'game' => 1]) ?>">게임</a>
          <a class="dr-btn-ai" href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'aiconfig' => 1]) ?>">AI 설정</a>
        <?php endif; ?>
        <?php if (is_admin()): ?>
          <a class="btn-new" href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'write' => 1]) ?>">+ 새 드림</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($posts)): ?>
      <div class="empty-msg">
        <?= is_admin()
              ? '아직 드림이 없어요. 우측 상단 [+ 새 드림] 으로 시작해보세요.'
              : '아직 등록된 드림이 없어요.' ?>
      </div>
    <?php else: ?>
      <div class="dr-grid">
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
  <?php endif; ?>
</div>
