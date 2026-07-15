<?php
/**
 * skins/board/memo/_form.php — 인라인 작성/수정 폼 partial
 *
 * 두 모드:
 *  - 새 작성: 평소엔 hidden, [+ 새 메모] 클릭 시 펼침
 *  - 수정:    $edit_post 가 있으면 자동 펼침 + 기존 데이터 채움 + type 고정
 */
$is_edit = !empty($edit_post);
$type = $is_edit ? memo_card_type($edit_post) : 'quote';

// 사용자 정의 카테고리 (board categories)
$cat_options = function_exists('hp_board_categories') ? hp_board_categories($board) : [];
$cat_cur     = $is_edit ? memo_user_category($edit_post) : '';

// edit 모드 — 기존 값 채우기
$v_quote_content = $is_edit && $type === 'quote' ? $edit_post['po_content']  : '';
$v_quote_source  = $is_edit && $type === 'quote' ? $edit_post['po_title']    : '';
$v_image_caption = $is_edit && $type === 'image' ? $edit_post['po_title']    : '';
$v_video_url     = $is_edit && $type === 'video' ? $edit_post['po_content']  : '';
$v_video_title   = $is_edit && $type === 'video' ? $edit_post['po_title']    : '';
$v_link_url      = $is_edit && $type === 'link'  ? $edit_post['po_content']  : '';
$v_link_title    = $is_edit && $type === 'link'  ? $edit_post['po_title']    : '';
$v_link_desc     = $is_edit && $type === 'link'  ? $edit_post['po_subtitle'] : '';

// 기존 썸네일 (image/link)
$existing_thumb = '';
if ($is_edit && in_array($type, ['image', 'link'], true) && !empty($edit_post['po_thumbnail'])) {
    $existing_thumb = memo_img_src($edit_post['po_thumbnail'], $board);
}

$type_label = ['quote' => '글귀', 'image' => '이미지', 'video' => '영상', 'link' => '링크'];
?>
<form class="memo-new" method="post" enctype="multipart/form-data" id="memoNewForm" <?= $is_edit ? '' : 'hidden' ?>>
  <?= csrf_input() ?>
  <input type="hidden" name="action" value="<?= $is_edit ? 'update_memo' : 'new_memo' ?>">

  <?php if ($is_edit): ?>
    <input type="hidden" name="po_id" value="<?= (int)$edit_post['po_id'] ?>">
    <div class="memo-edit-banner">
      <span><i class="fas fa-pen"></i> 수정 중</span>
    </div>
  <?php endif; ?>

  <div class="memo-types">
    <label class="memo-type-pill <?= $type === 'quote' ? 'active' : '' ?>">
      <input type="radio" name="memo_type" value="quote" <?= $type === 'quote' ? 'checked' : '' ?>>
      <i class="fas fa-quote-right"></i> 글귀
    </label>
    <label class="memo-type-pill <?= $type === 'image' ? 'active' : '' ?>">
      <input type="radio" name="memo_type" value="image" <?= $type === 'image' ? 'checked' : '' ?>>
      <i class="fas fa-image"></i> 이미지
    </label>
    <label class="memo-type-pill <?= $type === 'video' ? 'active' : '' ?>">
      <input type="radio" name="memo_type" value="video" <?= $type === 'video' ? 'checked' : '' ?>>
      <i class="fas fa-play"></i> 영상
    </label>
    <label class="memo-type-pill <?= $type === 'link' ? 'active' : '' ?>">
      <input type="radio" name="memo_type" value="link" <?= $type === 'link' ? 'checked' : '' ?>>
      <i class="fas fa-link"></i> 링크
    </label>
  </div>

  <div class="memo-fields" data-type="quote" <?= $type !== 'quote' ? 'hidden' : '' ?>>
    <textarea name="quote_content" placeholder="기록하고 싶은 글귀..." rows="3" maxlength="1000"><?= h($v_quote_content) ?></textarea>
    <input type="text" name="quote_source" value="<?= h($v_quote_source) ?>" placeholder="출처 (작가, 책 제목 등 — 선택)" maxlength="200">
  </div>

  <div class="memo-fields" data-type="image" <?= $type !== 'image' ? 'hidden' : '' ?>>
    <?php if ($existing_thumb && $type === 'image'): ?>
      <div class="memo-current-thumb">
        <img src="<?= h($existing_thumb) ?>" alt="현재 이미지">
        <span>현재 이미지 — 새 파일/URL 입력 시 교체</span>
      </div>
    <?php endif; ?>
    <div class="memo-img-row">
      <input type="text" name="image_url" placeholder="이미지 URL (https://...) 또는 →">
      <label class="memo-upload-btn">
        <input type="file" name="image_file" accept="image/*" hidden>
        <i class="fas fa-upload"></i> 업로드
      </label>
    </div>
    <input type="text" name="image_caption" value="<?= h($v_image_caption) ?>" placeholder="캡션 (선택)" maxlength="200">
  </div>

  <div class="memo-fields" data-type="video" <?= $type !== 'video' ? 'hidden' : '' ?>>
    <input type="text" name="video_url" value="<?= h($v_video_url) ?>" placeholder="유튜브 URL (https://youtu.be/... 또는 https://www.youtube.com/watch?v=...)">
    <input type="text" name="video_title" value="<?= h($v_video_title) ?>" placeholder="제목 (선택)" maxlength="200">
  </div>

  <div class="memo-fields" data-type="link" <?= $type !== 'link' ? 'hidden' : '' ?>>
    <input type="text" name="link_url" value="<?= h($v_link_url) ?>" placeholder="https://... (트위터/X·인스타그램 URL 자동 임베드)">
    <input type="text" name="link_title" value="<?= h($v_link_title) ?>" placeholder="제목" maxlength="200">
    <input type="text" name="link_desc" value="<?= h($v_link_desc) ?>" placeholder="설명 (선택)" maxlength="200">
    <?php if ($existing_thumb && $type === 'link'): ?>
      <div class="memo-current-thumb">
        <img src="<?= h($existing_thumb) ?>" alt="현재 썸네일">
        <span>현재 썸네일 — 새 파일/URL 입력 시 교체</span>
      </div>
    <?php endif; ?>
    <div class="memo-img-row">
      <input type="text" name="link_thumb_url" placeholder="썸네일 URL (선택) 또는 →">
      <label class="memo-upload-btn">
        <input type="file" name="link_thumb_file" accept="image/*" hidden>
        <i class="fas fa-upload"></i>
      </label>
    </div>
  </div>

  <?php if ($cat_options): ?>
    <div class="memo-cat-row">
      <select name="category" class="write-category">
        <option value="">카테고리 선택</option>
        <?php foreach ($cat_options as $c): ?>
          <option value="<?= h($c) ?>" <?= $cat_cur === $c ? 'selected' : '' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="memo-new-bar">
    <?php if ($is_edit): ?>
      <a href="<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>" class="memo-new-cancel">취소</a>
    <?php else: ?>
      <button type="button" class="memo-new-cancel" id="memoNewCancel">취소</button>
    <?php endif; ?>
    <button type="submit" class="memo-new-submit"><?= $is_edit ? '수정 완료' : '등록' ?></button>
  </div>
</form>
