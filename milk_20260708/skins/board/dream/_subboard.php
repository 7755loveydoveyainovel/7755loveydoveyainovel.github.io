<?php
/**
 * skins/board/dream/_subboard.php — 하위 게시글 탭 (게시글/메모/커미션 공통)
 *
 * include 전 설정 변수:
 *   $sb_kind  : 'post' | 'memo' | 'commission'
 *   $sb_label : 표시명 (예: '게시글')
 *   $post, $board (view.php 스코프)
 *
 * 페어 _logs.php 패턴: 목록(카드) + 관리자 인라인 작성/수정 폼.
 * 저장: 폼 POST → dr_handle_post (action=dp_add|dp_update|dp_delete, kind 동봉)
 */
$sb_items = [];
$sb_err = '';
try {
    $sb_items = dr_sub_list($post['po_id'], $sb_kind);
} catch (Throwable $e) {
    $sb_err = $e->getMessage();
}
$sb_count = count($sb_items);
$dream_id = (int)$post['po_id'];
$post_url = hp_url('board', ['slug' => $board['bo_slug']]);
$uid = $sb_kind; // 폼 id 접두사
?>
<div class="dr-sub" id="dr-sub-<?= h($sb_kind) ?>">
  <?php if ($sb_err && is_admin()): ?>
    <div class="dr-flash error" style="white-space:pre-wrap">서브보드 오류: <?= h($sb_err) ?></div>
  <?php endif; ?>
  <div class="dr-sub-head">
    <h3><?= h($sb_label) ?> <small>(<?= number_format($sb_count) ?>)</small></h3>
    <?php if (is_admin()): ?>
      <button type="button" class="dr-sub-toggle" data-uid="<?= h($uid) ?>">+ 새 <?= h($sb_label) ?></button>
    <?php endif; ?>
  </div>

  <?php if (is_admin()): ?>
    <form class="dr-sub-form" id="drSubForm_<?= h($uid) ?>"
          method="post" enctype="multipart/form-data" action="<?= h($post_url) ?>">
      <?= csrf_input() ?>
      <input type="hidden" name="action"     value="dp_add" id="drSubAction_<?= h($uid) ?>">
      <input type="hidden" name="kind"       value="<?= h($sb_kind) ?>">
      <input type="hidden" name="po_id"      value="<?= $dream_id ?>">
      <input type="hidden" name="sub_po_id"  value="0" id="drSubPoId_<?= h($uid) ?>">

      <div class="dr-sub-form-head">
        <strong id="drSubFormTitle_<?= h($uid) ?>">새 <?= h($sb_label) ?></strong>
        <button type="button" class="dr-sub-close" data-uid="<?= h($uid) ?>">×</button>
      </div>

      <input type="text" name="dp_title" id="drSubTitle_<?= h($uid) ?>"
             placeholder="제목" maxlength="200" required class="dr-sub-title-input">

      <?php
        $editor_name  = 'dp_post_content';
        $editor_value = '';
        include HP_PATH . '/inc/markdown-editor.php';
      ?>

      <div class="dr-sub-thumb-row">
        <label class="dr-sub-thumb-label">썸네일 (선택, 비우면 본문 첫 이미지)</label>
        <div class="dr-sub-thumb-inputs">
          <input type="text" name="dp_thumb_url" placeholder="이미지 URL 또는 ↓ 파일">
          <label class="dr-file-input">
            <input type="file" name="dp_thumb_file" accept="image/*"
                   class="dr-file-hidden" data-name-target="drSubThumbName_<?= h($uid) ?>">
            <span class="dr-file-input-btn">파일 선택</span>
            <span class="dr-file-input-name" id="drSubThumbName_<?= h($uid) ?>">선택된 파일 없음</span>
          </label>
        </div>
      </div>

      <div class="dr-sub-form-actions">
        <button type="submit" class="dr-submit" id="drSubSubmit_<?= h($uid) ?>">추가</button>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($sb_err): ?>
    <!-- 위 에러 표시 -->
  <?php elseif (empty($sb_items)): ?>
    <div class="dr-sub-empty">
      <?= is_admin() ? '아직 없어요. [+ 새 ' . h($sb_label) . '] 으로 시작해보세요.' : '아직 등록된 ' . h($sb_label) . '이(가) 없어요.' ?>
    </div>

  <?php elseif ($sb_kind === 'memo'): ?>
    <!-- ═══ 메모: 미리보기 목록 → 클릭 시 모달 ═══ -->
    <div class="dr-memo-list">
      <?php foreach ($sb_items as $it):
        $preview = mb_substr(trim(strip_tags(hp_render_markdown($it['po_content'] ?? ''))), 0, 120);
        $body_html = hp_render_markdown($it['po_content'] ?? '');
      ?>
        <div class="dr-memo-item" data-sub-id="<?= (int)$it['po_id'] ?>"
             data-memo-title="<?= h($it['po_title']) ?>"
             data-memo-date="<?= h(date('Y.m.d', strtotime($it['po_created_at']))) ?>">
          <div class="dr-memo-main">
            <div class="dr-memo-title"><?= h($it['po_title']) ?></div>
            <div class="dr-memo-preview"><?= h($preview) ?><?= mb_strlen($preview) >= 120 ? '…' : '' ?></div>
            <div class="dr-memo-date"><?= h(date('Y.m.d', strtotime($it['po_created_at']))) ?></div>
          </div>
          <div class="dr-memo-body" style="display:none"><?= $body_html ?></div>
          <?php if (is_admin()): ?>
            <button type="button" class="dr-sub-edit"
                    data-sub-id="<?= (int)$it['po_id'] ?>"
                    data-kind="<?= h($sb_kind) ?>"
                    data-title="<?= h($it['po_title']) ?>"
                    data-content="<?= h($it['po_content'] ?? '') ?>"
                    title="수정">✎</button>
            <form method="post" action="<?= h($post_url) ?>" class="dr-sub-del-form"
                  onsubmit="if(!confirm('삭제할까요?')){event.preventDefault();return false;}return true;">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="dp_delete">
              <input type="hidden" name="kind" value="<?= h($sb_kind) ?>">
              <input type="hidden" name="po_id" value="<?= $dream_id ?>">
              <input type="hidden" name="sub_po_id" value="<?= (int)$it['po_id'] ?>">
              <button type="submit" class="dr-sub-del" title="삭제">×</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <!-- ═══ 게시글(카드) / 커미션(이미지) : 목록 → 인라인 상세 전환 ═══ -->
    <div class="dr-sub-listview">
      <div class="<?= $sb_kind === 'commission' ? 'dr-comm-grid' : 'dr-sub-grid' ?>">
        <?php foreach ($sb_items as $it):
          $thumb_src = !empty($it['po_thumbnail'])
            ? (preg_match('#^https?://#i', $it['po_thumbnail']) ? $it['po_thumbnail'] : hp_post_image_url($it['po_thumbnail'], '_dreamlog'))
            : '';
        ?>
          <?php if ($sb_kind === 'commission'): ?>
            <!-- 커미션: 이미지 타일 -->
            <div class="dr-comm-card" data-sub-id="<?= (int)$it['po_id'] ?>">
              <div class="dr-comm-thumb">
                <?php if ($thumb_src): ?>
                  <img src="<?= h($thumb_src) ?>" alt="<?= h($it['po_title']) ?>" loading="lazy">
                <?php else: ?>
                  <span class="dr-sub-no-thumb"><?= function_exists('hp_icon') ? hp_icon('fas fa-image') : '◦' ?></span>
                <?php endif; ?>
                <div class="dr-comm-overlay"><?= h($it['po_title']) ?></div>
              </div>
              <?php if (is_admin()): ?>
                <button type="button" class="dr-sub-edit"
                        data-sub-id="<?= (int)$it['po_id'] ?>"
                        data-kind="<?= h($sb_kind) ?>"
                        data-title="<?= h($it['po_title']) ?>"
                        data-content="<?= h($it['po_content'] ?? '') ?>"
                        title="수정">✎</button>
                <form method="post" action="<?= h($post_url) ?>" class="dr-sub-del-form"
                      onsubmit="if(!confirm('삭제할까요?')){event.preventDefault();return false;}return true;">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="dp_delete">
                  <input type="hidden" name="kind" value="<?= h($sb_kind) ?>">
                  <input type="hidden" name="po_id" value="<?= $dream_id ?>">
                  <input type="hidden" name="sub_po_id" value="<?= (int)$it['po_id'] ?>">
                  <button type="submit" class="dr-sub-del" title="삭제">×</button>
                </form>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <!-- 게시글: 카드 -->
            <div class="dr-sub-card" data-sub-id="<?= (int)$it['po_id'] ?>">
              <div class="dr-sub-card-link dr-sub-openable" data-sub-id="<?= (int)$it['po_id'] ?>">
                <div class="dr-sub-card-thumb">
                  <?php if ($thumb_src): ?>
                    <img src="<?= h($thumb_src) ?>" alt="<?= h($it['po_title']) ?>" loading="lazy">
                  <?php else: ?>
                    <span class="dr-sub-no-thumb"><?= function_exists('hp_icon') ? hp_icon('fas fa-file-lines') : '◦' ?></span>
                  <?php endif; ?>
                </div>
                <div class="dr-sub-card-info">
                  <div class="dr-sub-card-title"><?= h($it['po_title']) ?></div>
                  <div class="dr-sub-card-date"><?= h(date('Y.m.d', strtotime($it['po_created_at']))) ?></div>
                </div>
              </div>
              <?php if (is_admin()): ?>
                <button type="button" class="dr-sub-edit"
                        data-sub-id="<?= (int)$it['po_id'] ?>"
                        data-kind="<?= h($sb_kind) ?>"
                        data-title="<?= h($it['po_title']) ?>"
                        data-content="<?= h($it['po_content'] ?? '') ?>"
                        title="수정">✎</button>
                <form method="post" action="<?= h($post_url) ?>" class="dr-sub-del-form"
                      onsubmit="if(!confirm('삭제할까요?')){event.preventDefault();return false;}return true;">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="dp_delete">
                  <input type="hidden" name="kind" value="<?= h($sb_kind) ?>">
                  <input type="hidden" name="po_id" value="<?= $dream_id ?>">
                  <input type="hidden" name="sub_po_id" value="<?= (int)$it['po_id'] ?>">
                  <button type="submit" class="dr-sub-del" title="삭제">×</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <!-- 인라인 상세 (게시글/커미션 공통) — 클릭 시 JS 가 채워 표시 -->
      <?php foreach ($sb_items as $it):
        $detail_thumb = !empty($it['po_thumbnail'])
          ? (preg_match('#^https?://#i', $it['po_thumbnail']) ? $it['po_thumbnail'] : hp_post_image_url($it['po_thumbnail'], '_dreamlog'))
          : '';
      ?>
        <div class="dr-sub-detail" id="dr-detail-<?= (int)$it['po_id'] ?>" style="display:none">
          <button type="button" class="dr-detail-back">← 목록</button>
          <h4 class="dr-detail-title"><?= h($it['po_title']) ?></h4>
          <div class="dr-detail-date"><?= h(date('Y년 m월 d일', strtotime($it['po_created_at']))) ?></div>
          <?php if ($sb_kind === 'commission' && $detail_thumb): ?>
            <div class="dr-detail-image"><img src="<?= h($detail_thumb) ?>" alt=""></div>
          <?php endif; ?>
          <div class="dr-detail-body markdown-body"><?= hp_render_markdown($it['po_content'] ?? '') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- 메모 모달 (탭당 1개, 비어있다가 JS 가 채움) -->
<?php if ($sb_kind === 'memo'): ?>
  <div class="dr-memo-modal" id="drMemoModal_<?= h($uid) ?>" style="display:none">
    <div class="dr-memo-modal-backdrop"></div>
    <div class="dr-memo-modal-box">
      <button type="button" class="dr-memo-modal-close">×</button>
      <h4 class="dr-memo-modal-title"></h4>
      <div class="dr-memo-modal-date"></div>
      <div class="dr-memo-modal-body markdown-body"></div>
    </div>
  </div>
<?php endif; ?>
