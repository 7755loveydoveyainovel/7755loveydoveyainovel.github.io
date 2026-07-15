<?php
/**
 * skins/board/memo/_helpers.php — 메모 스킨 전용 헬퍼 + POST 핸들러
 *
 * 이미지는 모두 data/posts/{bo_slug}/ 하위에 저장. data/posts/ 직접
 * 저장된 파일도 에 있는 파일도 폴백으로 표시 가능.
 *
 * 함수 일람:
 *   memo_handle_post  — 새 메모 등록 / 카드 삭제 / 수정 (관리자만)
 *   memo_resolve_image — 파일 업로드 또는 URL 입력 → 저장 가능한 값
 *   memo_img_src      — po_thumbnail 값 → 표시 가능한 src (외부 URL 자동 처리)
 *   memo_youtube_id   — 유튜브 URL → video ID
 *   memo_unlink_local — 로컬 파일이면 unlink
 */

/**
 * 메모 카드 타입 결정 — po_extra.memo_type 우선, 없으면 po_category 폴백 (옛 데이터)
 * 새 메모는 po_extra 에 저장, 옛 메모는 po_category 에서 읽어 자연스럽게 호환.
 */
function memo_card_type($post) {
    $extra = !empty($post['po_extra']) ? json_decode($post['po_extra'], true) : null;
    if (is_array($extra) && !empty($extra['memo_type'])) {
        $t = $extra['memo_type'];
    } else {
        $t = $post['po_category'] ?? '';
    }
    return in_array($t, ['quote', 'image', 'video', 'link'], true) ? $t : 'quote';
}

/**
 * 메모 사용자 카테고리 — 옛 데이터(po_category 가 카드 타입)는 빈 값으로 처리.
 * po_extra.memo_type 이 있다는 건 새 모델이라 po_category 가 진짜 사용자 카테고리.
 */
function memo_user_category($post) {
    $extra = !empty($post['po_extra']) ? json_decode($post['po_extra'], true) : null;
    if (is_array($extra) && !empty($extra['memo_type'])) {
        return $post['po_category'] ?? '';
    }
    // 옛 데이터: po_category 가 카드 타입이면 사용자 카테고리 없음
    $cat = $post['po_category'] ?? '';
    return in_array($cat, ['quote', 'image', 'video', 'link'], true) ? '' : $cat;
}

/**
 * POST 요청 처리 — GET 이면 즉시 return.
 * 새 메모 / 카드 삭제 / 수정 처리 후 board 페이지로 redirect.
 */
function memo_handle_post($board, $bo_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    csrf_check();
    require_admin();

    $action = $_POST['action'] ?? '';
    $back   = hp_url('board', ['slug' => $board['bo_slug']]);
    $slug   = $board['bo_slug'];

    // 사용자 카테고리 검증 (board 에 정의된 것만 허용)
    $valid_cats = function_exists('hp_board_categories') ? hp_board_categories($board) : [];
    $user_cat   = trim($_POST['category'] ?? '');
    if ($user_cat !== '' && !in_array($user_cat, $valid_cats, true)) $user_cat = '';

    // ─── 새 메모 ───
    if ($action === 'new_memo') {
        $type = $_POST['memo_type'] ?? 'quote';
        if (!in_array($type, ['quote', 'image', 'video', 'link'], true)) $type = 'quote';

        $title    = '';
        $subtitle = '';
        $content  = '';
        $thumb    = null;

        if ($type === 'quote') {
            $content = trim($_POST['quote_content'] ?? '');
            $title   = trim($_POST['quote_source']  ?? '');
        }
        elseif ($type === 'image') {
            $title = trim($_POST['image_caption'] ?? '');
            $thumb = memo_resolve_image('image_file', 'image_url', $slug);
        }
        elseif ($type === 'video') {
            $content = trim($_POST['video_url']   ?? '');
            $title   = trim($_POST['video_title'] ?? '');
            if (!preg_match('#youtube\.com|youtu\.be#i', $content)) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '유튜브 URL 만 지원됩니다.'];
                hp_redirect($back);
            }
        }
        elseif ($type === 'link') {
            $content  = trim($_POST['link_url']   ?? '');
            $title    = trim($_POST['link_title'] ?? '');
            $subtitle = trim($_POST['link_desc']  ?? '');
            $thumb    = memo_resolve_image('link_thumb_file', 'link_thumb_url', $slug);
            if (!preg_match('#^https?://#i', $content)) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '링크는 http(s):// 로 시작해야 합니다.'];
                hp_redirect($back);
            }
        }

        // 빈 메모 차단
        if ($content === '' && $title === '' && !$thumb) {
            hp_redirect($back);
        }

        // po_extra 에 memo_type 저장, po_category 는 사용자 카테고리
        $extra = json_encode(['memo_type' => $type], JSON_UNESCAPED_UNICODE);

        qInsert(
            "INSERT INTO {post} (po_bo_id, po_title, po_subtitle, po_content, po_thumbnail, po_category, po_extra, po_created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $bo_id,
                mb_substr($title, 0, 200),
                mb_substr($subtitle, 0, 200),
                mb_substr($content, 0, 1000),
                $thumb,
                $user_cat ?: null,
                $extra,
            ]
        );
        hp_redirect($back);
    }

    // ─── 카드 삭제 ───
    if ($action === 'delete_memo') {
        $po_id = (int)($_POST['po_id'] ?? 0);
        $post  = qOne("SELECT * FROM {post} WHERE po_id = ? AND po_bo_id = ?", [$po_id, $bo_id]);
        if ($post) {
            memo_unlink_local($post['po_thumbnail'] ?? '', $slug);
            qExec("DELETE FROM {post} WHERE po_id = ?", [$po_id]);
        }
        hp_redirect($back);
    }

    // ─── 카드 수정 ───
    if ($action === 'update_memo') {
        $po_id    = (int)($_POST['po_id'] ?? 0);
        $existing = qOne("SELECT * FROM {post} WHERE po_id = ? AND po_bo_id = ?", [$po_id, $bo_id]);
        if (!$existing) hp_redirect($back);

        // 카드 타입 — 사용자가 폼에서 변경 가능 (POST 우선, 누락 시 기존 type)
        $type = $_POST['memo_type'] ?? memo_card_type($existing);
        if (!in_array($type, ['quote', 'image', 'video', 'link'], true)) {
            $type = memo_card_type($existing);
        }

        $title    = '';
        $subtitle = '';
        $content  = '';
        // type 이 바뀌었으면 기존 thumbnail 도 새로 받음 — 새 type 의 컨텐츠만 의미 있음
        $thumb    = $existing['po_thumbnail'];

        if ($type === 'quote') {
            $content = trim($_POST['quote_content'] ?? '');
            $title   = trim($_POST['quote_source']  ?? '');
        }
        elseif ($type === 'image') {
            $title = trim($_POST['image_caption'] ?? '');
            $new_thumb = memo_resolve_image('image_file', 'image_url', $slug);
            if ($new_thumb) {
                memo_unlink_local($existing['po_thumbnail'], $slug);
                $thumb = $new_thumb;
            }
        }
        elseif ($type === 'video') {
            $content = trim($_POST['video_url']   ?? '');
            $title   = trim($_POST['video_title'] ?? '');
            if (!preg_match('#youtube\.com|youtu\.be#i', $content)) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '유튜브 URL 만 지원됩니다.'];
                hp_redirect($back);
            }
        }
        elseif ($type === 'link') {
            $content  = trim($_POST['link_url']   ?? '');
            $title    = trim($_POST['link_title'] ?? '');
            $subtitle = trim($_POST['link_desc']  ?? '');
            $new_thumb = memo_resolve_image('link_thumb_file', 'link_thumb_url', $slug);
            if ($new_thumb) {
                memo_unlink_local($existing['po_thumbnail'], $slug);
                $thumb = $new_thumb;
            }
            if (!preg_match('#^https?://#i', $content)) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '링크는 http(s):// 로 시작해야 합니다.'];
                hp_redirect($back);
            }
        }

        // po_extra 에 memo_type 보장 (옛 데이터 자동 이전)
        $extra_arr = !empty($existing['po_extra']) ? json_decode($existing['po_extra'], true) : [];
        if (!is_array($extra_arr)) $extra_arr = [];
        $extra_arr['memo_type'] = $type;
        $extra_json = json_encode($extra_arr, JSON_UNESCAPED_UNICODE);

        qExec(
            "UPDATE {post}
                SET po_title = ?, po_subtitle = ?, po_content = ?, po_thumbnail = ?,
                    po_category = ?, po_extra = ?, po_updated_at = NOW()
              WHERE po_id = ?",
            [
                mb_substr($title, 0, 200),
                mb_substr($subtitle, 0, 200),
                mb_substr($content, 0, 1000),
                $thumb,
                $user_cat ?: null,
                $extra_json,
                $po_id,
            ]
        );
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '수정되었어요.'];
        hp_redirect($back);
    }
}

/**
 * 로컬 파일이면 unlink. 외부 URL 은 무시.
 * data/posts/{slug}/{file} 또는 data/posts/{file} 모두 처리.
 */
function memo_unlink_local($value, $slug = null) {
    if (!$value || preg_match('#^https?://#i', $value)) return;
    $f = hp_post_image_path($value, $slug);
    if ($f && file_exists($f)) @unlink($f);
}

/**
 * 이미지 입력 처리 — 파일 업로드 우선, 없으면 URL.
 * 둘 다 비어 있으면 null 반환.
 */
function memo_resolve_image($file_key, $url_key, $bo_slug = null) {
    if (!empty($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
        $err = null;
        $name = hp_save_uploaded_image(
            $_FILES[$file_key]['tmp_name'],
            $_FILES[$file_key]['name'],
            $_FILES[$file_key]['size'],
            $err,
            $bo_slug
        );
        if ($name) return $name;
    }
    $url = trim($_POST[$url_key] ?? '');
    return ($url !== '' && preg_match('#^https?://#i', $url)) ? $url : null;
}

/**
 * po_thumbnail 값을 표시할 src 로 변환.
 *  - https?:// → 그대로 (외부 URL)
 *  - 그 외      → data/posts/{slug}/{filename} (또는 폴백으로 data/posts/{filename})
 */
function memo_img_src($value, $board_or_slug = null) {
    if (!$value) return '';
    return preg_match('#^https?://#i', $value) ? $value : hp_post_image_url($value, $board_or_slug);
}

/**
 * 텍스트에서 YouTube video ID 추출.
 * 지원: youtube.com/watch?v=, youtu.be/, youtube.com/embed/, youtube.com/shorts/
 */
function memo_youtube_id($text) {
    if (!$text) return null;
    return preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})#', $text, $m) ? $m[1] : null;
}
