<?php
/**
 * skins/board/dream/_form.php — 드림 작성/수정 폼
 *
 * 페어 스킨(_form.php) 구조를 그대로 따름 (milk 공통 톤 일치):
 *   상단 메타바(.dr-meta-bar) + .dr-fs / .dr-row / .dr-img-block /
 *   .dr-file-input / .dr-char-panel / .dr-palette-grid + markdown-editor.php
 *
 * 드림 고유: 캐릭터 신상(나이·생일·키·MBTI)·좋싫·서브이미지3·BGM.
 * 캐릭터 말투/호칭/페르소나는 폼에서 받지 않음 → AI 레일 캐릭터 설정(persona).
 *
 * 변수: $board, $edit_post (nullable)
 */
$is_edit = !empty($edit_post);
$d       = $is_edit ? dr_data($edit_post) : dr_data([]);
$ext     = !empty($d['ext_images']) ? $d['ext_images'] : [''];

$cur_thumb  = $is_edit ? ($edit_post['po_thumbnail'] ?? '') : '';
$cur_header = $d['header_image'] ?? '';

$category_options = function_exists('hp_board_categories') ? hp_board_categories($board) : [];
$category_cur     = $is_edit ? ($edit_post['po_category'] ?? '') : '';
$cancel_url       = hp_url('board', ['slug' => $board['bo_slug']]);

// 컬러 디폴트
$a_color_defaults = ['#29c7ca', '#5fd6d8', '#93e4e5', '#c7f1f2'];
$b_color_defaults = ['#e8a0b4', '#efb9c8', '#f5d2dc', '#fbe9ef'];
?>

<form id="drForm" class="dr-form" method="post" enctype="multipart/form-data" action="<?= h($cancel_url) ?>">
  <?= csrf_input() ?>
  <input type="hidden" name="action" value="<?= $is_edit ? 'update_dream' : 'new_dream' ?>">
  <?php if ($is_edit): ?><input type="hidden" name="po_id" value="<?= (int)$edit_post['po_id'] ?>"><?php endif; ?>

  <div class="dr-form-head">
    <h2><?= $is_edit ? '드림 수정' : '새 드림' ?></h2>
    <a class="dr-form-cancel" href="<?= h($cancel_url) ?>">← 일람으로</a>
  </div>

  <!-- 상단 메타: 카테고리 + 비밀글 -->
  <div class="dr-meta-bar">
    <?php if ($category_options): ?>
      <select name="dr_category" class="write-category">
        <option value="">카테고리 선택</option>
        <?php foreach ($category_options as $cat): ?>
          <option value="<?= h($cat) ?>" <?= $category_cur === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <label class="dr-private-toggle">
      <input type="checkbox" name="dr_secret" value="1"
             <?= ($is_edit && !empty($edit_post['po_is_private'])) ? 'checked' : '' ?>>
      <span class="ico"><?= function_exists('hp_icon') ? hp_icon('fas fa-lock') : '🔒' ?></span>
      <span class="lbl">비밀글로 설정 <small>— 관리자만 볼 수 있어요</small></span>
    </label>
  </div>

  <!-- 1. 페어 정보 -->
  <fieldset class="dr-fs">
    <legend>페어 정보</legend>
    <div class="dr-row">
      <label class="dr-flex-grow">페어 이름 <span class="req">*</span>
        <input type="text" name="dr_title" required maxlength="200"
               placeholder="예: 달과 별의 기록"
               value="<?= h($is_edit ? $edit_post['po_title'] : '') ?>">
      </label>
      <label class="dr-flex-color">대표색
        <div class="dr-color-pick">
          <input type="color" name="dr_color_picker" id="drMainPicker"
                 value="<?= h($d['color'] ?: '#29c7ca') ?>">
          <input type="text" name="dr_color" id="drMainText" maxlength="7"
                 placeholder="#29c7ca" pattern="^#[0-9a-fA-F]{6}$"
                 value="<?= h($d['color']) ?>">
        </div>
      </label>
    </div>
  </fieldset>

  <!-- 2. 이미지 -->
  <fieldset class="dr-fs">
    <legend>이미지</legend>
    <?php
    $render_img_block = function($key, $title, $hint, $cur_value) use ($board) {
      $cur_src = $cur_value ? dr_img_src($cur_value, $board) : '';
      ?>
      <div class="dr-img-block">
        <div class="dr-img-block-head">
          <strong><?= h($title) ?></strong>
          <?php if ($hint): ?><small><?= h($hint) ?></small><?php endif; ?>
        </div>
        <?php if ($cur_src): ?>
          <div class="dr-img-block-cur">
            <img src="<?= h($cur_src) ?>" alt="">
            <small>새 URL/파일 입력시 교체</small>
          </div>
        <?php endif; ?>
        <input type="text" name="<?= h($key) ?>_url" placeholder="https://..." class="dr-img-url-input">
        <label class="dr-file-input">
          <input type="file" name="<?= h($key) ?>_file" accept="image/*"
                 class="dr-file-hidden" data-name-target="drFileName_<?= h($key) ?>">
          <span class="dr-file-input-btn">파일 선택</span>
          <span class="dr-file-input-name" id="drFileName_<?= h($key) ?>">선택된 파일 없음</span>
        </label>
      </div>
      <?php
    };
    $render_img_block('thumb',     '카드 썸네일', '비워두면 헤더/A·B 얼굴 순으로 자동', $cur_thumb);
    $render_img_block('dr_header', '헤더 배경',   'view 페이지 상단 배경',              $cur_header);
    ?>
    <div class="dr-row">
      <label class="dr-flex-grow">헤더 출처 <small>(선택)</small>
        <input type="text" name="dr_header_credit" maxlength="200" value="<?= h($d['header_credit'] ?? '') ?>">
      </label>
    </div>
  </fieldset>

  <!-- 3. 두 캐릭터 -->
  <fieldset class="dr-fs dr-fs-chars">
    <legend>두 캐릭터</legend>
    <div class="dr-chars-grid">
      <?php
      foreach ([
        ['side' => 'a', 'label' => 'A', 'data' => $d['char_a'], 'defaults' => $a_color_defaults],
        ['side' => 'b', 'label' => 'B', 'data' => $d['char_b'], 'defaults' => $b_color_defaults],
      ] as $c):
        $side = $c['side']; $cd = $c['data'];
        $cur_img_src = !empty($cd['main_img']) ? dr_img_src($cd['main_img'], $board) : '';
        $colors = ($cd['colors'] ?? []) + ['', '', '', ''];
        $subs   = $cd['sub_imgs'] ?? [];
      ?>
        <div class="dr-char-panel dr-char-panel--<?= h($side) ?>">
          <div class="dr-char-head">
            <span class="dr-char-tag"><?= h($c['label']) ?></span>
          </div>

          <div class="dr-row">
            <label>이름
              <input type="text" name="<?= h($side) ?>_name" maxlength="100" value="<?= h($cd['name']) ?>">
            </label>
            <label>원어/영문명
              <input type="text" name="<?= h($side) ?>_fullname" maxlength="100" value="<?= h($cd['fullname']) ?>">
            </label>
          </div>
          <div class="dr-row">
            <label>서브타이틀
              <input type="text" name="<?= h($side) ?>_subtitle" maxlength="200" value="<?= h($cd['subtitle']) ?>">
            </label>
            <label>태그
              <input type="text" name="<?= h($side) ?>_tags" maxlength="200" value="<?= h($cd['tags']) ?>">
            </label>
          </div>

          <!-- 대표 이미지 -->
          <div class="dr-img-block dr-img-block-compact">
            <div class="dr-img-block-head"><strong>대표 이미지</strong></div>
            <?php if ($cur_img_src): ?>
              <div class="dr-img-block-cur"><img src="<?= h($cur_img_src) ?>" alt=""></div>
            <?php endif; ?>
            <input type="text" name="<?= h($side) ?>_main_img" placeholder="https://..."
                   class="dr-img-url-input" value="<?= h($cd['main_img']) ?>">
            <label class="dr-file-input">
              <input type="file" name="<?= h($side) ?>_main_file" accept="image/*"
                     class="dr-file-hidden" data-name-target="drFileName_<?= h($side) ?>main">
              <span class="dr-file-input-btn">파일 선택</span>
              <span class="dr-file-input-name" id="drFileName_<?= h($side) ?>main">선택된 파일 없음</span>
            </label>
            <input type="text" name="<?= h($side) ?>_main_img_credit" placeholder="이미지 출처(선택)"
                   class="dr-img-url-input" value="<?= h($cd['main_credit'] ?? '') ?>" style="margin-top:6px">
          </div>

          <!-- 컬러 팔레트 -->
          <div class="dr-color-palette">
            <div class="dr-sub-label">컬러 팔레트 <small>— 메인 1 + 서브 3</small></div>
            <div class="dr-palette-grid">
              <?php for ($i = 0; $i < 4; $i++): $val = $colors[$i] ?: $c['defaults'][$i]; ?>
                <input type="color" name="<?= h($side) ?>_color_<?= $i ?>" value="<?= h($val) ?>"
                       title="<?= $i === 0 ? '메인' : '서브 ' . $i ?>" class="dr-palette-input">
              <?php endfor; ?>
            </div>
          </div>

          <!-- 신상 -->
          <div class="dr-row dr-row-tight">
            <label>나이 <input type="text" name="<?= h($side) ?>_age" maxlength="50" value="<?= h($cd['age']) ?>"></label>
            <label>생일 <input type="text" name="<?= h($side) ?>_birthday" maxlength="50" value="<?= h($cd['birthday']) ?>"></label>
          </div>
          <div class="dr-row dr-row-tight">
            <label>키 <input type="text" name="<?= h($side) ?>_height" maxlength="50" value="<?= h($cd['height']) ?>"></label>
            <label>MBTI <input type="text" name="<?= h($side) ?>_mbti" maxlength="20" value="<?= h($cd['mbti']) ?>"></label>
          </div>
          <div class="dr-row dr-row-tight">
            <label>좋아하는 것 <input type="text" name="<?= h($side) ?>_likes" maxlength="200" value="<?= h($cd['likes']) ?>"></label>
            <label>싫어하는 것 <input type="text" name="<?= h($side) ?>_dislikes" maxlength="200" value="<?= h($cd['dislikes']) ?>"></label>
          </div>

          <!-- 서브 이미지 -->
          <div class="dr-sub-label">서브 이미지 URL <small>(최대 3)</small></div>
          <?php for ($i = 0; $i < 3; $i++): ?>
            <input type="text" name="<?= h($side) ?>_sub_img_<?= $i ?>" placeholder="서브 이미지 <?= $i+1 ?>"
                   class="dr-img-url-input" value="<?= h($subs[$i] ?? '') ?>" style="margin-bottom:6px">
          <?php endfor; ?>

          <!-- 자유 설명 -->
          <div class="dr-textarea-label">
            <span class="dr-ta-head"><strong>설명</strong></span>
            <textarea name="<?= h($side) ?>_desc" rows="4"
                      placeholder="이 캐릭터에 대한 설명…"><?= h($cd['desc']) ?></textarea>
          </div>

          <!-- BGM (미니홈 장식) -->
          <div class="dr-row dr-row-tight">
            <label>BGM 제목 <input type="text" name="<?= h($side) ?>_bgm_title" maxlength="100" value="<?= h($cd['bgm_title'] ?? '') ?>"></label>
            <label>BGM YouTube ID <input type="text" name="<?= h($side) ?>_bgm_id" maxlength="30" value="<?= h($cd['bgm_id'] ?? '') ?>"></label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </fieldset>

  <!-- 4. 페어 설정 / 본문 -->
  <fieldset class="dr-fs">
    <legend>페어 설정 <small>— 두 캐릭터 사이의 이야기·세계관</small></legend>
    <?php
      $editor_name  = 'dr_content';
      $editor_value = ($is_edit && ($d['content'] ?? '') !== '')
          ? hp_render_markdown($d['content'])
          : '';
      include HP_PATH . '/inc/markdown-editor.php';
    ?>
  </fieldset>

  <!-- 5. 분위기 컷 -->
  <fieldset class="dr-fs">
    <legend>분위기 컷 <small>— 추가 일러/장면 등</small></legend>
    <div id="drImgList">
      <?php foreach ($ext as $url): ?>
        <div class="img-row dr-row">
          <input type="text" name="ext_image[]" placeholder="https://..." value="<?= h($url) ?>">
          <button type="button" class="img-del dr-mini-btn">−</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" id="drImgAdd" class="dr-mini-btn dr-add-btn">+ URL 추가</button>
  </fieldset>

  <div class="dr-form-actions">
    <a class="dr-cancel-link" href="<?= h($cancel_url) ?>">취소</a>
    <button type="submit" class="dr-submit"><?= $is_edit ? '수정 완료' : '드림 등록' ?></button>
  </div>
</form>

<script>
(function(){
  // 컬러 픽커 ↔ 텍스트 동기화
  var picker = document.getElementById('drMainPicker');
  var text   = document.getElementById('drMainText');
  if (picker && text) {
    picker.addEventListener('input', function(){ text.value = picker.value; });
    text.addEventListener('input', function(){
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
    });
  }

  // 파일 input → 이름 표시
  document.querySelectorAll('.dr-file-hidden').forEach(function(inp){
    inp.addEventListener('change', function(){
      var t = document.getElementById(inp.dataset.nameTarget);
      if (t) t.textContent = inp.files.length ? inp.files[0].name : '선택된 파일 없음';
    });
  });

  // 분위기 컷 추가/삭제
  var imgAdd = document.getElementById('drImgAdd');
  if (imgAdd) imgAdd.addEventListener('click', function(){
    var list = document.getElementById('drImgList');
    var div = document.createElement('div');
    div.className = 'img-row dr-row';
    div.innerHTML = '<input type="text" name="ext_image[]" placeholder="https://..."><button type="button" class="img-del dr-mini-btn">−</button>';
    list.appendChild(div);
  });
  document.addEventListener('click', function(e){
    if (e.target.classList.contains('img-del')) {
      e.target.closest('.img-row').remove();
    }
  });
})();
</script>
