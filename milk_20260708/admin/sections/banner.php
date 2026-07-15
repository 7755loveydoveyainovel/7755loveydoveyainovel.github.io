<?php
/**
 * admin/sections/banner.php — 본인 배너 + 친구 배너
 *
 * 친구 배너는 보통 친구 사이트에 있는 이미지를 직접 링크하므로 URL 입력이 우선.
 * 단, 친구 사이트가 사라져 배너가 깨질 가능성이 있으니 업로드도 옵션으로 지원.
 *
 * bn_image 컬럼 규칙:
 *  - 'https?://...' 로 시작 → 외부 URL (그대로 사용)
 *  - 그 외 → data/banners/ 안의 로컬 파일명
 */

/**
 * 업로드된 배너 이미지를 data/banners/ 에 저장
 */
function _handle_banner_upload($file_key, $existing = null) {
    _last_upload_error(null);

    if (empty($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }
    $file = $_FILES[$file_key];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        _last_upload_error('업로드 실패 (PHP 에러 코드 ' . $file['error'] . ')');
        return $existing;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        _last_upload_error('파일 크기가 2MB 를 초과합니다.');
        return $existing;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        _last_upload_error('지원 형식: JPG, PNG, GIF, WebP');
        return $existing;
    }

    $info = @getimagesize($file['tmp_name']);
    if (!$info) {
        _last_upload_error('이미지 파일이 손상되었거나 형식이 잘못되었습니다.');
        return $existing;
    }

    // 실제 MIME 타입 검증 — polyglot 파일(이미지+PHP) 차단
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($mime && !in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                _last_upload_error('이미지가 아닌 파일입니다.');
                return $existing;
            }
        }
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $dir      = HP_PATH . '/data/banners';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        _last_upload_error('파일 이동 실패. data/banners/ 폴더 쓰기 권한을 확인해주세요.');
        return $existing;
    }

    // 기존이 로컬 파일이었다면 삭제
    if ($existing && !preg_match('#^https?://#i', $existing) && file_exists($dir . '/' . $existing)) {
        @unlink($dir . '/' . $existing);
    }
    return $filename;
}

function _last_upload_error($set = 'GET') {
    static $msg = null;
    if ($set !== 'GET') $msg = $set;
    return $msg;
}

/**
 * 사용자 입력 (URL or 업로드) 을 받아서 bn_image 에 저장할 값 결정
 *
 * @return string|null  저장할 값 ('https://..' 또는 로컬 파일명) 혹은 null (아무것도 안 받음)
 */
function _resolve_banner_source($url_field, $upload_field, $existing = null) {
    $url = trim($_POST[$url_field] ?? '');
    if ($url !== '') {
        if (!preg_match('#^https?://#i', $url)) {
            _last_upload_error('이미지 URL은 http:// 또는 https:// 로 시작해야 합니다.');
            return $existing;
        }
        // URL 사용 → 기존 로컬 파일 정리
        if ($existing && !preg_match('#^https?://#i', $existing)) {
            $local = HP_PATH . '/data/banners/' . $existing;
            if (file_exists($local)) @unlink($local);
        }
        return $url;
    }
    // URL 비어있으면 업로드 시도
    return _handle_banner_upload($upload_field, $existing);
}

// ─── POST 처리 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // 친구 배너 순서 변경 (D&D AJAX)
    if ($action === 'reorder') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            foreach ($ids as $i => $id) {
                qExec("UPDATE {banner} SET bn_order = ? WHERE bn_id = ? AND bn_type = 'friend'",
                      [(int)$i + 1, (int)$id]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'save_self') {
        $existing = qOne("SELECT * FROM {banner} WHERE bn_type = 'self' LIMIT 1");
        $url   = trim($_POST['bn_url']   ?? '');
        $title = trim($_POST['bn_title'] ?? '');
        $image = _resolve_banner_source('bn_image_url', 'bn_image', $existing['bn_image'] ?? null);
        $err   = _last_upload_error();

        if ($err) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err];
        } elseif ($existing) {
            qExec(
                "UPDATE {banner} SET bn_image = ?, bn_url = ?, bn_title = ? WHERE bn_id = ?",
                [$image ?: $existing['bn_image'], $url, $title, $existing['bn_id']]
            );
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '본인 배너가 저장되었습니다.'];
        } elseif ($image) {
            qInsert(
                "INSERT INTO {banner} (bn_type, bn_image, bn_url, bn_title, bn_order, bn_created_at)
                 VALUES ('self', ?, ?, ?, 0, NOW())",
                [$image, $url, $title]
            );
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '본인 배너가 등록되었습니다.'];
        } else {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '이미지 URL 또는 업로드 파일이 필요합니다.'];
        }
    }

    elseif ($action === 'add_friend') {
        $url   = trim($_POST['bn_url']   ?? '');
        $title = trim($_POST['bn_title'] ?? '');
        $image = _resolve_banner_source('bn_image_url', 'bn_image');
        $err   = _last_upload_error();

        if ($err) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err];
        } elseif ($image) {
            $max_order = (int)qVal("SELECT COALESCE(MAX(bn_order), 0) FROM {banner} WHERE bn_type = 'friend'");
            qInsert(
                "INSERT INTO {banner} (bn_type, bn_image, bn_url, bn_title, bn_order, bn_created_at)
                 VALUES ('friend', ?, ?, ?, ?, NOW())",
                [$image, $url, $title, $max_order + 1]
            );
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '친구 배너가 추가되었습니다.'];
        } else {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '이미지 URL 또는 업로드 파일이 필요합니다.'];
        }
    }

    elseif ($action === 'update_friends') {
        if (!empty($_POST['friends']) && is_array($_POST['friends'])) {
            foreach ($_POST['friends'] as $id => $f) {
                $id = (int)$id;
                if ($id <= 0) continue;
                qExec(
                    "UPDATE {banner}
                     SET bn_url = ?, bn_title = ?
                     WHERE bn_id = ? AND bn_type = 'friend'",
                    [trim($f['url'] ?? ''), trim($f['title'] ?? ''), $id]
                );
            }
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '친구 배너가 저장되었습니다.'];
        }
    }

    elseif ($action === 'delete_self') {
        $b = qOne("SELECT * FROM {banner} WHERE bn_type = 'self' LIMIT 1");
        if ($b) {
            // 로컬 업로드 파일이면 같이 삭제 (외부 URL 이면 DB 행만 제거)
            if ($b['bn_image'] && !preg_match('#^https?://#i', $b['bn_image'])) {
                $local = HP_PATH . '/data/banners/' . $b['bn_image'];
                if (file_exists($local)) @unlink($local);
            }
            qExec("DELETE FROM {banner} WHERE bn_id = ?", [$b['bn_id']]);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '본인 배너가 삭제되었습니다.'];
        }
    }

    elseif ($action === 'delete_friend') {
        $id = (int)($_POST['bn_id'] ?? 0);
        $b  = qOne("SELECT * FROM {banner} WHERE bn_id = ? AND bn_type = 'friend'", [$id]);
        if ($b) {
            // 로컬 파일이면 삭제
            if ($b['bn_image'] && !preg_match('#^https?://#i', $b['bn_image'])) {
                $local = HP_PATH . '/data/banners/' . $b['bn_image'];
                if (file_exists($local)) @unlink($local);
            }
            qExec("DELETE FROM {banner} WHERE bn_id = ?", [$id]);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '삭제되었습니다.'];
        }
    }

    header('Location: ' . HP_BASE . '/admin/?section=banner');
    exit;
}

$self    = qOne("SELECT * FROM {banner} WHERE bn_type = 'self' LIMIT 1");
$friends = qAll("SELECT * FROM {banner} WHERE bn_type = 'friend' ORDER BY bn_order, bn_id");

// 배너 src 변환 헬퍼 (URL 이면 그대로, 아니면 로컬)
function _bn_src($bn_image) {
    if (!$bn_image) return '';
    if (preg_match('#^https?://#i', $bn_image)) return $bn_image;
    return HP_BASE . '/data/banners/' . $bn_image;
}
?>

<!-- ─── 본인 배너 ─── -->
<div class="adm-section">
  <h2>본인 배너</h2>
  <div class="sub">200×40 사이즈 권장 (동인 표준). 메인 페이지 프로필 카드에 표시되고, 클릭 시 사이트 주소가 복사돼요.</div>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_self">

    <div class="banner-uploader">
      <div class="preview <?= $self ? '' : 'empty' ?>">
        <?php if ($self && $self['bn_image']): ?>
          <img src="<?= h(_bn_src($self['bn_image'])) ?>" alt="">
        <?php else: ?>
          <span>200 × 40</span>
        <?php endif; ?>
      </div>
      <div class="info">
        <div class="name">현재 배너</div>
        <div class="meta">
          <?php if ($self): ?>
            <?php if (preg_match('#^https?://#i', $self['bn_image'])): ?>
              <span style="color:var(--accent)">외부 URL</span> · <?= h($self['bn_image']) ?>
            <?php else: ?>
              <span style="color:var(--accent-2)">업로드 파일</span> · <?= h($self['bn_image']) ?>
            <?php endif; ?>
          <?php else: ?>
            아직 등록되지 않음
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="adm-field" style="margin-top:18px;">
      <label>이미지 URL <span style="color:var(--ink-mute);font-weight:500;">(우선)</span></label>
      <span class="hint">이미 어딘가에 올려둔 배너 이미지가 있다면 그 URL 을 입력하세요. <code>https://</code> 로 시작해야 함.</span>
      <input type="url" name="bn_image_url" placeholder="https://...">
    </div>

    <div class="adm-field">
      <label>또는 파일 업로드</label>
      <span class="hint">URL 칸이 비어있을 때만 사용. JPG / PNG / GIF / WebP, 2MB 이하.</span>
      <input type="file" name="bn_image" accept="image/*">
    </div>

    <div class="adm-row">
      <div class="adm-field">
        <label>제목 (alt)</label>
        <input type="text" name="bn_title" value="<?= h($self['bn_title'] ?? '') ?>" maxlength="100">
      </div>
      <div class="adm-field">
        <label>링크 URL (선택)</label>
        <input type="url" name="bn_url" value="<?= h($self['bn_url'] ?? '') ?>" placeholder="비워두면 사이트 주소">
      </div>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
      <?php if ($self): ?>
        <button type="button" class="adm-btn adm-btn-danger"
                onclick="if(confirm('본인 배너를 삭제할까요? 등록된 이미지가 비워집니다.'))document.getElementById('del-self-banner').submit()">배너 삭제</button>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($self): ?>
    <form method="post" id="del-self-banner" style="display:none">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="delete_self">
    </form>
  <?php endif; ?>
</div>

<!-- ─── 친구 배너 ─── -->
<div class="adm-section">
  <h2>친구 배너</h2>
  <div class="sub">교환한 친구 사이트의 배너. 친구 사이트의 이미지를 직접 링크해도 되고, 깨질 걱정되면 업로드해도 OK.</div>

  <?php if ($friends): ?>
    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="update_friends">
      <div class="friend-list sortable-list" data-reorder-section="banner" data-reorder-action="reorder">
        <?php foreach ($friends as $f): ?>
          <div class="friend-row sortable-item" data-id="<?= $f['bn_id'] ?>">
            <span class="handle">drag_indicator</span>
            <div class="preview">
              <img src="<?= h(_bn_src($f['bn_image'])) ?>" alt="">
            </div>
            <input type="text" name="friends[<?= $f['bn_id'] ?>][title]" value="<?= h($f['bn_title']) ?>" placeholder="제목 (alt)">
            <input type="url" name="friends[<?= $f['bn_id'] ?>][url]" value="<?= h($f['bn_url']) ?>" placeholder="https://">
            <button type="button" class="delete"
                    onclick="if(confirm('이 배너를 삭제할까요?'))document.getElementById('del-friend-<?= $f['bn_id'] ?>').submit()">delete</button>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="adm-actions">
        <button type="submit" class="adm-btn adm-btn-primary">변경사항 저장</button>
      </div>
    </form>

    <?php foreach ($friends as $f): ?>
      <form method="post" id="del-friend-<?= $f['bn_id'] ?>" style="display:none">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="delete_friend">
        <input type="hidden" name="bn_id" value="<?= $f['bn_id'] ?>">
      </form>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="padding:20px;text-align:center;color:var(--ink-mute);font-size:13px;">
      아직 등록된 친구 배너가 없어요.
    </div>
  <?php endif; ?>

  <h3>+ 새 친구 배너 추가</h3>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="add_friend">

    <div class="adm-field">
      <label>이미지 URL <span style="color:var(--ink-mute);font-weight:500;">(우선)</span></label>
      <span class="hint">친구 사이트의 배너 이미지 URL. <code>https://</code> 로 시작.</span>
      <input type="url" name="bn_image_url" placeholder="https://friend.com/banner.png">
    </div>

    <div class="adm-field">
      <label>또는 파일 업로드</label>
      <span class="hint">URL 칸이 비어있을 때만 사용. 2MB 이하.</span>
      <input type="file" name="bn_image" accept="image/*">
    </div>

    <div class="adm-row">
      <div class="adm-field">
        <label>제목</label>
        <input type="text" name="bn_title" maxlength="100" placeholder="친구 사이트 이름">
      </div>
      <div class="adm-field">
        <label>링크 URL</label>
        <input type="url" name="bn_url" placeholder="https://">
      </div>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">추가</button>
    </div>
  </form>
</div>
