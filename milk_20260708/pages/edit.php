<?php
/**
 * pages/edit.php — 게시글 수정 / 삭제 (관리자 전용)
 */

require_admin();

$po_id = (int)($_GET['po_id'] ?? 0);
$post  = qOne("SELECT * FROM {post} WHERE po_id = ?", [$po_id]);
if (!$post) {
    include HP_PATH . '/pages/home.php';
    return;
}

$board = hp_board_by_id($post['po_bo_id']);
if (!$board) {
    include HP_PATH . '/pages/home.php';
    return;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // 삭제
    if (($_POST['action'] ?? '') === 'delete') {
        // 모든 첨부 이미지 함께 삭제
        $dir = HP_PATH . '/data/posts';
        foreach (hp_post_images($post) as $img) {
            $f = $dir . '/' . $img;
            if (file_exists($f)) @unlink($f);
        }
        qExec("DELETE FROM {post} WHERE po_id = ?", [$po_id]);
        hp_redirect(hp_url('board', ['slug' => $board['bo_slug']]));
    }

    // 수정
    $title_v    = trim($_POST['title']    ?? '');
    $subtitle_v = trim($_POST['subtitle'] ?? '');
    $content_v  = trim($_POST['content']  ?? '');
    $category_v = trim($_POST['category'] ?? '');
    $is_private = !empty($_POST['is_private']) ? 1 : 0;
    $pw_input   = $_POST['post_pw'] ?? '';
    $remove_set = $_POST['remove_images'] ?? [];
    if (!is_array($remove_set)) $remove_set = [];

    // 카테고리 검증
    $valid_cats = hp_board_categories($board);
    if ($category_v !== '' && !in_array($category_v, $valid_cats, true)) {
        $category_v = '';
    }

    // 비공개 비밀번호 hash 계산
    // 1) 비공개 OFF → NULL
    // 2) 비공개 ON + 새 비번 입력 → 새로 hash
    // 3) 비공개 ON + 빈 입력 + 기존 hash 있음 → 기존 hash 유지
    // 4) 비공개 ON + 빈 입력 + 기존 hash 없음 → 일괄 비밀번호 fallback
    if (!$is_private) {
        $pw_hash = null;
    } elseif ($pw_input !== '') {
        $pw_hash = password_hash($pw_input, PASSWORD_DEFAULT);
    } elseif (!empty($post['po_pw_hash'])) {
        $pw_hash = $post['po_pw_hash'];
    } else {
        $bulk = hp_config('bulk_private_pw_hash', '');
        $pw_hash = $bulk ?: null;
    }

    if ($title_v === '' || $content_v === '') {
        $error = '제목과 내용을 모두 입력해주세요.';
        $post['po_title']    = $title_v;
        $post['po_subtitle'] = $subtitle_v;
        $post['po_content']  = $content_v;
    } else {
        $current_images = hp_post_images($post);
        $dir = HP_PATH . '/data/posts';

        // 1. 기존 중에 제거 표시된 것 제거 + 파일 unlink
        $kept_images = [];
        foreach ($current_images as $img) {
            if (in_array($img, $remove_set, true)) {
                $f = $dir . '/' . $img;
                if (file_exists($f)) @unlink($f);
                // 본문에 삽입돼 있던 해당 이미지 태그도 함께 제거 (escape 형태 포함)
                $content_v = preg_replace(
                    '/[ \t]*\\\\?\[img:' . preg_quote($img, '/') . '\\\\?\]\r?\n?/',
                    '',
                    $content_v
                );
            } else {
                $kept_images[] = $img;
            }
        }

        // 2. 새 업로드 처리
        $new_images = [];
        $img_map    = []; // 업로드 슬롯 인덱스 → 저장 파일명 ([img:new:N] 치환용)
        if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
            $count = count($_FILES['images']['name']);
            for ($i = 0; $i < $count && !$error; $i++) {
                if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $name = hp_save_uploaded_image(
                    $_FILES['images']['tmp_name'][$i],
                    $_FILES['images']['name'][$i],
                    $_FILES['images']['size'][$i],
                    $error
                );
                if ($name) { $new_images[] = $name; $img_map[$i] = $name; }
            }
        }

        if (!$error) {
            // 본문 내 [img:new:N] 플레이스홀더 → 실제 저장 파일명 태그로 치환
            // (마크다운 직렬화가 \[img:new:N\] 으로 escape 했을 수 있어 둘 다 허용)
            foreach ($img_map as $i => $name) {
                $content_v = preg_replace(
                    '/\\\\?\[img:new:' . $i . '\\\\?\]/',
                    '[img:' . $name . ']',
                    $content_v
                );
            }
            // 업로드 실패 등으로 남은 플레이스홀더는 제거
            $content_v = preg_replace('/[ \t]*\\\\?\[img:new:\d+\\\\?\]\r?\n?/', '', $content_v);

            $all_images = array_merge($kept_images, $new_images);
            list($thumbnail, $extra) = hp_pack_post_images($all_images);

            qExec(
                "UPDATE {post} SET po_title = ?, po_subtitle = ?, po_content = ?, po_thumbnail = ?, po_extra = ?, po_category = ?, po_is_private = ?, po_pw_hash = ?, po_updated_at = NOW() WHERE po_id = ?",
                [
                    mb_substr($title_v, 0, 200),
                    mb_substr($subtitle_v, 0, 200),
                    $content_v,
                    $thumbnail,
                    $extra,
                    $category_v ?: null,
                    $is_private,
                    $pw_hash,
                    $po_id,
                ]
            );
            hp_redirect(hp_url('view', ['po_id' => $po_id]));
        }
    }
}
?>
<div class="write-wrap">
  <?php if ($error): ?>
    <div class="form-error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post" class="write-form" enctype="multipart/form-data">
    <?= csrf_input() ?>
    <input type="text" name="title" class="write-title"
           placeholder="제목"
           value="<?= h($post['po_title']) ?>"
           required maxlength="200">
    <?php $cats = hp_board_categories($board); if ($cats):
      $current_cat = $post['po_category'] ?? '';
    ?>
      <select name="category" class="write-category">
        <option value="">카테고리 선택</option>
        <?php foreach ($cats as $cat): ?>
          <option value="<?= h($cat) ?>" <?= $current_cat === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <input type="text" name="subtitle" class="write-subtitle"
           placeholder="부제목 (선택)"
           value="<?= h($post['po_subtitle'] ?? '') ?>"
           maxlength="200">
    <?php
      $editor_name  = 'content';
      $editor_value = $post['po_content'];
      include HP_PATH . '/inc/markdown-editor.php';
    ?>

    <label class="write-upload">
      <span class="lbl">이미지 첨부 <span class="hint">(선택, 1장당 5MB · 최대 20장 — 클릭할 때마다 추가됨)</span></span>
      <input type="file" name="images[]" accept="image/*" multiple>
      <div class="upload-preview"></div>
      <?php $imgs = hp_post_images($post); if ($imgs): ?>
        <div class="current-imgs">
          <div class="current-imgs-label">현재 이미지 (<?= count($imgs) ?>장) — 체크하면 삭제됩니다</div>
          <div class="current-imgs-grid">
            <?php foreach ($imgs as $img): ?>
              <label class="current-img-item">
                <input type="checkbox" name="remove_images[]" value="<?= h($img) ?>">
                <img src="<?= HP_BASE ?>/data/posts/<?= h($img) ?>" alt="">
                <span class="rm-mark"><?= hp_icon('fas fa-trash') ?></span>
                <button type="button" class="cur-img-insert"
                        data-file="<?= h($img) ?>"
                        data-src="<?= HP_BASE ?>/data/posts/<?= h(rawurlencode($img)) ?>"
                        data-tag="[img:<?= h($img) ?>]">본문 삽입</button>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </label>

    <div class="write-private">
      <label class="write-private-toggle">
        <input type="checkbox" name="is_private" value="1" <?= !empty($post['po_is_private']) ? 'checked' : '' ?>>
        <span><i class="fas fa-lock"></i> 비공개 글</span>
      </label>
      <input type="password" name="post_pw" class="write-private-pw" maxlength="60"
             placeholder="<?= !empty($post['po_pw_hash']) ? '비밀번호 변경 (비워두면 기존 비밀번호 유지)' : '비밀번호 (비워두면 공통 비밀번호 사용)' ?>"
             autocomplete="new-password">
    </div>

    <div class="write-actions">
      <button type="submit" name="action" value="delete" class="btn-delete"
              onclick="return confirm('정말 삭제하시겠어요? 되돌릴 수 없습니다.')">
        삭제
      </button>
      <a href="<?= hp_url('view', ['po_id' => $po_id]) ?>" class="btn-cancel">취소</a>
      <button type="submit" class="btn-submit">저장</button>
    </div>
  </form>
</div>
<script>
/* 다중 이미지 업로드 — 누적 + 미리보기 + 개별 제거 (edit) */
(function () {
  document.querySelectorAll('.write-form input[type=file][multiple]').forEach(function (input) {
    var dt = new DataTransfer();
    var preview = input.parentElement.querySelector('.upload-preview');
    if (!preview) return;
    var form = input.closest('form');

    function mdEditor() {
      return form ? form.querySelector('.md-editor') : null;
    }

    // 텍스트 태그 삽입 fallback (에디터 모듈이 아직 안 떴을 때)
    function insertTag(tag) {
      var ta = form ? form.querySelector('textarea[name="content"]') : null;
      if (!ta) return;
      var s = ta.selectionStart, e = ta.selectionEnd;
      var t = '\n' + tag + '\n';
      ta.value = ta.value.slice(0, s) + t + ta.value.slice(e);
      ta.focus();
      ta.selectionStart = ta.selectionEnd = s + t.length;
    }

    // 커서 위치에 이미지 삽입 — 에디터엔 실제 이미지로 표시됨
    function insertImage(idx, file) {
      var mw = mdEditor();
      if (mw && mw._insertImage) {
        if (!file._hpUrl) file._hpUrl = URL.createObjectURL(file);
        mw._insertImage('new:' + idx, file._hpUrl);
        return;
      }
      insertTag('[img:new:' + idx + ']');
    }

    // 마크다운 직렬화가 대괄호를 \[ \] 로 escape 할 수 있어 둘 다 허용 (fallback용)
    function retagTransform(v, removedIdx) {
      v = v.replace(new RegExp('\\\\?\\[img:new:' + removedIdx + '\\\\?\\]', 'g'), '');
      v = v.replace(/\\?\[img:new:(\d+)\\?\]/g, function (m, n) {
        n = parseInt(n, 10);
        return n > removedIdx ? '[img:new:' + (n - 1) + ']' : m;
      });
      return v;
    }

    // 미리보기에서 파일 제거 시: 해당 이미지 삭제 + 뒤 번호 당김
    function retagOnRemove(removedIdx) {
      var mw = mdEditor();
      if (mw && mw._retagNewImages) {
        mw._retagNewImages(removedIdx);
        return;
      }
      var ta = form ? form.querySelector('textarea[name="content"]') : null;
      if (!ta) return;
      ta.value = retagTransform(ta.value, removedIdx);
    }

    input.addEventListener('change', function () {
      Array.from(input.files).forEach(function (f) {
        var dup = false;
        for (var i = 0; i < dt.items.length; i++) {
          var ex = dt.files[i];
          if (ex.name === f.name && ex.size === f.size) { dup = true; break; }
        }
        if (!dup) dt.items.add(f);
      });
      input.files = dt.files;
      render();
    });

    function render() {
      preview.innerHTML = '';
      Array.from(dt.files).forEach(function (file, idx) {
        var item = document.createElement('div');
        item.className = 'upload-preview-item';

        var img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.onload = function () { URL.revokeObjectURL(img.src); };
        item.appendChild(img);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'upload-preview-remove';
        btn.textContent = '×';
        btn.addEventListener('click', function () {
          retagOnRemove(idx);
          dt.items.remove(idx);
          input.files = dt.files;
          render();
        });
        item.appendChild(btn);

        var ins = document.createElement('button');
        ins.type = 'button';
        ins.className = 'upload-preview-insert';
        ins.textContent = '본문 삽입';
        ins.title = '커서 위치에 이 이미지를 삽입합니다';
        ins.addEventListener('click', function () {
          insertImage(idx, file);
        });
        item.appendChild(ins);

        preview.appendChild(item);
      });
    }
  });
})();

/* 기존 첨부 이미지 — 본문 삽입 버튼 */
(function () {
  document.querySelectorAll('.write-form .cur-img-insert').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.preventDefault(); // label 내부라 삭제 체크박스 토글 방지
      e.stopPropagation();
      var form = b.closest('form');
      if (!form) return;
      var tag = b.getAttribute('data-tag');
      var mw = form.querySelector('.md-editor');
      if (mw && mw._insertImage) {
        mw._insertImage(b.getAttribute('data-file'), b.getAttribute('data-src'));
        return;
      }
      var ta = form.querySelector('textarea[name="content"]');
      if (!ta) return;
      var s = ta.selectionStart, en = ta.selectionEnd;
      var t = '\n' + tag + '\n';
      ta.value = ta.value.slice(0, s) + t + ta.value.slice(en);
      ta.focus();
      ta.selectionStart = ta.selectionEnd = s + t.length;
    });
  });
})();

/* 비공개 토글 — 체크할 때만 비밀번호 입력 표시 */
(function () {
  document.querySelectorAll('.write-private').forEach(function (wrap) {
    var cb = wrap.querySelector('input[type=checkbox]');
    var pw = wrap.querySelector('.write-private-pw');
    if (!cb || !pw) return;
    function sync() { pw.style.display = cb.checked ? 'block' : 'none'; }
    cb.addEventListener('change', sync);
    sync();
  });
})();
</script>
