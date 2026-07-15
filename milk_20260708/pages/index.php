<?php
/**
 * skins/board/character/index.php — 캐릭터 도감 갤러리
 *
 * 작성: 보드 상단 [+ 새 캐릭터] → 인라인 펼침 폼
 * 수정: ?edit=N 으로 폼이 수정 모드로 진입
 *
 * 변수 (pages/board.php 가 전달):
 *   $board, $bo_id, $posts, $page_num, $total_pages, $total
 */

require_once __DIR__ . '/_helpers.php';
ch_handle_post($board, $bo_id);

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
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/character/style.css?v=<?= filemtime(HP_PATH . '/skins/board/character/style.css') ?>">

<div class="list-wrap character-wrap">
  <div class="list-actions">
    <span class="meta">총 <?= number_format($total) ?>명</span>
    <?php if (is_admin()): ?>
      <button type="button" class="btn-new" id="chNewToggle">+ 새 캐릭터</button>
    <?php endif; ?>
  </div>

  <?php if ($flash): ?>
    <div class="ch-flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <?php if (is_admin()) include __DIR__ . '/_form.php'; ?>

  <?php if (empty($posts)): ?>
    <div class="empty-msg">
      <?= is_admin()
            ? '아직 캐릭터가 없어요. 우측 상단 [+ 새 캐릭터] 로 시작해보세요.'
            : '아직 등록된 캐릭터가 없어요.' ?>
    </div>
  <?php else: ?>
    <div class="ch-grid">
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

<script>
(function(){
  var toggle = document.getElementById('chNewToggle');
  var form   = document.getElementById('chForm');
  var isEdit = <?= $edit_post ? 'true' : 'false' ?>;

  // 수정 모드면 폼을 자동으로 펼침
  if (form && isEdit) form.classList.add('open');

  if (toggle && form) {
    toggle.addEventListener('click', function(){
      // 수정 중이면 일반 작성으로 전환 (?edit 제거)
      if (isEdit) { window.location.href = '<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>'; return; }
      form.classList.toggle('open');
    });
  }

  // 관계 행 추가/삭제
  var relList = document.getElementById('chRelList');
  var relAdd  = document.getElementById('chRelAdd');
  if (relAdd && relList) {
    relAdd.addEventListener('click', function(){
      var row = relList.firstElementChild ? relList.firstElementChild.cloneNode(true) : null;
      if (!row) return;
      row.querySelectorAll('input,select').forEach(function(el){
        if (el.tagName === 'SELECT') el.selectedIndex = 0;
        else el.value = '';
      });
      relList.appendChild(row);
    });
    relList.addEventListener('click', function(e){
      if (e.target.classList.contains('rel-del')) {
        var rows = relList.querySelectorAll('.rel-row');
        if (rows.length > 1) e.target.closest('.rel-row').remove();
        else {
          // 마지막 한 줄은 비우기만
          var row = e.target.closest('.rel-row');
          row.querySelectorAll('input').forEach(function(el){ el.value = ''; });
        }
      }
    });
  }

  // 외부 이미지 URL 행 추가/삭제
  var imgList = document.getElementById('chImgList');
  var imgAdd  = document.getElementById('chImgAdd');
  if (imgAdd && imgList) {
    imgAdd.addEventListener('click', function(){
      var row = imgList.firstElementChild ? imgList.firstElementChild.cloneNode(true) : null;
      if (!row) return;
      row.querySelector('input').value = '';
      imgList.appendChild(row);
    });
    imgList.addEventListener('click', function(e){
      if (e.target.classList.contains('img-del')) {
        var rows = imgList.querySelectorAll('.img-row');
        if (rows.length > 1) e.target.closest('.img-row').remove();
        else e.target.closest('.img-row').querySelector('input').value = '';
      }
    });
  }
})();
</script>
