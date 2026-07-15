<?php
/**
 * skins/board/dream/_helpers.php — 드림 스킨 공통 헬퍼
 *
 * 캐릭터 스킨(_helpers.php) 패턴 준용:
 *   · 드림 본체는 {post} 1행. po_content 에 JSON(페어/캐릭터 A·B/일상) 저장.
 *   · 하위 게시글은 '_dreamlog' 게시판 + {dream_post} 매칭 (character_post 준용).
 *
 * 1-A 범위: 데이터 로드(dr_data) · 카드 썸네일 · 이미지 헬퍼 · 서브보드 스키마.
 *           등록/수정(dr_handle_post 의 new/update)은 1-C(_form)에서 확장.
 *
 * 의존: milk 코어 (qOne/qAll/qExec/qInsert, hp_url, is_admin, csrf_check,
 *       require_admin, hp_redirect, hp_save_uploaded_image, hp_post_image_url ...)
 */

if (!defined('HP_PATH')) { http_response_code(403); exit; }

/* ──────────────────────────────────────────────
   1) 데이터 로드 — po_content(JSON) → 구조화 배열
   ────────────────────────────────────────────── */
/**
 * 드림 본체 데이터.
 * 캐릭터 A/B 는 각각 풍부한 필드를 가짐. 페어 공통은 최상위.
 */
function dr_data($post) {
    $d = json_decode($post['po_content'] ?? '', true);
    if (!is_array($d)) $d = [];

    $char_default = [
        'name'         => '',
        'fullname'     => '',   // 원어/영문명
        'subtitle'     => '',
        'tags'         => '',
        'main_img'     => '',
        'main_credit'  => '',
        'sub_imgs'     => [],   // 최대 3
        'colors'       => ['', '', '', ''],
        'age'          => '',
        'birthday'     => '',
        'height'       => '',
        'mbti'         => '',
        'likes'        => '',
        'dislikes'     => '',
        'desc'         => '',
        // AI 전용
        'calls_other'  => [],   // 상대 호칭 (배열)
        'bubble_lines' => [],   // 말풍선 대사 (배열)
        'self_call'    => '',   // 자기 호칭
        // BGM
        'bgm_title'    => '',
        'bgm_id'       => '',
    ];

    return array_replace_recursive([
        'color'         => '',   // 페어 대표색 → --dr-color
        'header_image'  => '',
        'header_credit' => '',
        'content'       => '',   // 페어 설정/세계관
        'char_a'        => $char_default,
        'char_b'        => $char_default,
        'ext_images'    => [],   // 분위기 컷
        'daily'         => [],   // 일상 로그 [{date,period,mood,rating,content}, ...]
    ], $d);
}

/** 카드 썸네일 — po_thumbnail → 헤더 → A 대표이미지 폴백 */
function dr_card_thumb($post) {
    if (!empty($post['po_thumbnail'])) return $post['po_thumbnail'];
    $d = dr_data($post);
    if (!empty($d['header_image'])) return $d['header_image'];
    return $d['char_a']['main_img'] ?? '';
}

/** 헥스 밝기 → 대비 텍스트색 (캐릭터 스킨 ch_text_color 동일) */
function dr_text_color($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) !== 6) return '#000';
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return ((($r * 299) + ($g * 587) + ($b * 114)) / 1000) > 155 ? '#000' : '#fff';
}

/* ──────────────────────────────────────────────
   2) 이미지 헬퍼 (캐릭터 스킨 준용)
   ────────────────────────────────────────────── */
/** 업로드 파일 우선, 없으면 URL. 저장된 파일명 또는 URL 반환 */
function dr_resolve_image($file_key, $url_key, $bo_slug = null) {
    // 파일 우선
    if (!empty($_FILES[$file_key]['name']) && empty($_FILES[$file_key]['error'])) {
        $err  = '';
        $name = hp_save_uploaded_image(
            $_FILES[$file_key]['tmp_name'],
            $_FILES[$file_key]['name'],
            $_FILES[$file_key]['size'],
            $err,
            $bo_slug
        );
        if ($name) return $name;
    }
    // URL
    $url = trim($_POST[$url_key] ?? '');
    if ($url !== '' && preg_match('#^https?://#i', $url)) return $url;
    return '';
}

/** 저장값(파일명 또는 URL) → 표시 URL */
function dr_img_src($value, $board_or_slug = null) {
    $value = (string)$value;
    if ($value === '') return '';
    if (preg_match('#^https?://#i', $value)) return $value;
    return hp_post_image_url($value, $board_or_slug);
}

/** 로컬 업로드 파일 삭제 (URL 이면 무시) */
function dr_unlink_local($value, $slug = null) {
    $value = (string)$value;
    if ($value === '' || preg_match('#^https?://#i', $value)) return;
    if (function_exists('hp_unlink_post_image')) {
        @hp_unlink_post_image($value, $slug);
    }
}

/**
 * POST 에서 캐릭터 A/B 한쪽 데이터 파싱. ($pre = 'a' | 'b')
 * 폼 필드명 규칙: {pre}_name, {pre}_main_img, {pre}_main_file, {pre}_color_0..3,
 *   {pre}_sub_img_0..2, {pre}_calls_other(쉼표), {pre}_bubble_lines(줄바꿈) ...
 * @param array $existing 수정 시 기존 캐릭터 데이터 (이미지 폴백)
 */
function dr_parse_char_post($pre, $existing = [], $slug = null) {
    $g = function($n) use ($pre) { return trim($_POST["{$pre}_{$n}"] ?? ''); };

    // 대표 이미지 (파일 우선 → URL → 기존)
    $main = $existing['main_img'] ?? '';
    $new_main = dr_resolve_image("{$pre}_main_file", "{$pre}_main_img", $slug);
    if ($new_main) {
        if ($main) dr_unlink_local($main, $slug);
        $main = $new_main;
    } else {
        $u = $g('main_img');
        if ($u === '') $main = '';
        elseif (preg_match('#^https?://#i', $u)) $main = $u;
    }

    // 컬러 4슬롯
    $colors = [];
    for ($i = 0; $i < 4; $i++) {
        $c = trim($_POST["{$pre}_color_{$i}"] ?? '');
        $colors[] = preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '';
    }

    // 서브 이미지 3슬롯
    $subs = [];
    for ($i = 0; $i < 3; $i++) {
        $sv = trim($_POST["{$pre}_sub_img_{$i}"] ?? '');
        if ($sv !== '' && preg_match('#^https?://#i', $sv)) $subs[] = $sv;
    }

    // ※ 호칭·말풍선·자기호칭은 폼에서 받지 않음 — AI 레일의 캐릭터 설정(persona)에서 관리.
    //   수정 시 기존 char JSON 의 해당 값은 dr_handle_post 가 별도로 보존하지 않으므로,
    //   여기서 기존값을 그대로 이어받아 유지한다.
    $keep_calls   = $existing['calls_other']  ?? [];
    $keep_bubbles = $existing['bubble_lines'] ?? [];
    $keep_self    = $existing['self_call']    ?? '';

    return [
        'name'         => mb_substr($g('name'), 0, 100),
        'fullname'     => mb_substr($g('fullname'), 0, 100),
        'subtitle'     => mb_substr($g('subtitle'), 0, 200),
        'tags'         => mb_substr($g('tags'), 0, 200),
        'main_img'     => $main,
        'main_credit'  => mb_substr($g('main_img_credit'), 0, 200),
        'sub_imgs'     => $subs,
        'colors'       => $colors,
        'age'          => mb_substr($g('age'), 0, 50),
        'birthday'     => mb_substr($g('birthday'), 0, 50),
        'height'       => mb_substr($g('height'), 0, 50),
        'mbti'         => mb_substr($g('mbti'), 0, 20),
        'likes'        => mb_substr($g('likes'), 0, 200),
        'dislikes'     => mb_substr($g('dislikes'), 0, 200),
        'desc'         => mb_substr($g('desc'), 0, 2000),
        // AI 설정(persona 탭 관리) — 기존값 보존
        'calls_other'  => $keep_calls,
        'bubble_lines' => $keep_bubbles,
        'self_call'    => $keep_self,
        // BGM (폼 입력)
        'bgm_title'    => mb_substr($g('bgm_title'), 0, 100),
        'bgm_id'       => mb_substr($g('bgm_id'), 0, 30),
    ];
}

/* ──────────────────────────────────────────────
   3) 서브보드 스키마 — _dreamlog 게시판 + {dream_post} 매칭
   ──────────────────────────────────────────────
   캐릭터 스킨 ch_ensure_log_schema 준용.
   하위 게시글(게시글/메모/커미션 등)을 단일 _dreamlog 게시판 posts 로 저장하고
   {dream_post} 매칭 테이블로 드림 ↔ 글 연결. (메뉴 비노출 위해 _ prefix)
*/
function dr_ensure_subboard_schema() {
    static $done = false;
    if ($done) return;
    $done = true;

    // 매칭 테이블: dp_char_po_id(드림 po_id) ↔ dp_po_id(글 po_id)
    qExec("CREATE TABLE IF NOT EXISTS {dream_post} (
        dp_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        dp_dream_po_id INT UNSIGNED NOT NULL,
        dp_po_id      INT UNSIGNED NOT NULL,
        dp_kind       VARCHAR(20) NOT NULL DEFAULT 'post',
        dp_order      INT NOT NULL DEFAULT 0,
        PRIMARY KEY (dp_id),
        KEY idx_dream (dp_dream_po_id),
        UNIQUE KEY uniq_po (dp_po_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // _dreamlog 게시판 lazy 생성 (관리자 전용 = 메뉴 비노출)
    $exists = qOne("SELECT bo_id FROM {board} WHERE bo_slug = ?", ['_dreamlog']);
    if (!$exists) {
        qInsert("INSERT INTO {board}
                    (bo_slug, bo_name, bo_skin, bo_admin_only, bo_created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                ['_dreamlog', '드림 로그', 'list', 1]);
    }
}

/** _dreamlog 게시판 ID */
function dr_log_board_id() {
    static $id = null;
    if ($id !== null) return $id;
    dr_ensure_subboard_schema();
    $row = qOne("SELECT bo_id FROM {board} WHERE bo_slug = ?", ['_dreamlog']);
    $id = $row ? (int)$row['bo_id'] : 0;
    return $id;
}

/** 드림 하위 게시글 목록 (kind 별, 본문 제외 — 카드용) */
function dr_sub_list($dream_po_id, $kind = null) {
    dr_ensure_subboard_schema();
    $sql = "SELECT p.po_id, p.po_title, p.po_content, p.po_thumbnail, p.po_is_private,
                   p.po_created_at, dp.dp_kind, dp.dp_order
            FROM {dream_post} dp
            JOIN {post} p ON p.po_id = dp.dp_po_id
            WHERE dp.dp_dream_po_id = ?";
    $params = [(int)$dream_po_id];
    if ($kind !== null) { $sql .= " AND dp.dp_kind = ?"; $params[] = $kind; }
    $sql .= " ORDER BY dp.dp_order ASC, p.po_created_at DESC";
    return qAll($sql, $params);
}

/** 하위 게시글 단건 (본문 포함) — 소유 검증 */
function dr_sub_get($dream_po_id, $sub_po_id) {
    $own = qOne("SELECT dp_kind FROM {dream_post} WHERE dp_dream_po_id = ? AND dp_po_id = ?",
                [(int)$dream_po_id, (int)$sub_po_id]);
    if (!$own) return null;
    $p = qOne("SELECT * FROM {post} WHERE po_id = ?", [(int)$sub_po_id]);
    if ($p) $p['dp_kind'] = $own['dp_kind'];
    return $p;
}

/** kind 라벨 */
function dr_kind_label($kind) {
    $m = ['post' => '게시글', 'memo' => '메모', 'commission' => '커미션'];
    return $m[$kind] ?? $kind;
}

/* ──────────────────────────────────────────────
   이야기(소설) — dream-novels q-헬퍼 어댑터
   작품 {dream_novels} + 회차 {dream_chapters}, dr_id = 드림 본체 po_id
   ────────────────────────────────────────────── */
function dr_novels_ensure_schema() {
    static $done = false;
    if ($done) return;
    $done = true;
    qExec("CREATE TABLE IF NOT EXISTS {dream_novels} (
        dn_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        dr_id          INT UNSIGNED NOT NULL DEFAULT 0,
        bo_id          INT UNSIGNED NOT NULL DEFAULT 0,
        dn_title       VARCHAR(200) NOT NULL DEFAULT '',
        dn_subtitle    VARCHAR(200) NOT NULL DEFAULT '',
        dn_category    VARCHAR(50)  NOT NULL DEFAULT '',
        dn_cover       VARCHAR(500) NOT NULL DEFAULT '',
        dn_description TEXT NULL,
        dn_status      VARCHAR(20)  NOT NULL DEFAULT 'ongoing',
        dn_rating      VARCHAR(20)  NOT NULL DEFAULT 'all',
        dn_secret      TINYINT      NOT NULL DEFAULT 0,
        dn_order       INT          NOT NULL DEFAULT 0,
        dn_created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        dn_updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (dn_id),
        KEY idx_dr_id (dr_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    qExec("CREATE TABLE IF NOT EXISTS {dream_chapters} (
        dc_id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        dn_id         INT UNSIGNED NOT NULL DEFAULT 0,
        dr_id         INT UNSIGNED NOT NULL DEFAULT 0,
        dc_number     INT NOT NULL DEFAULT 1,
        dc_title      VARCHAR(200) NOT NULL DEFAULT '',
        dc_content    LONGTEXT NULL,
        dc_word_count INT NOT NULL DEFAULT 0,
        dc_view_count INT NOT NULL DEFAULT 0,
        dc_secret     TINYINT NOT NULL DEFAULT 0,
        dc_order      INT NOT NULL DEFAULT 0,
        dc_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        dc_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (dc_id),
        KEY idx_dn_id (dn_id),
        KEY idx_dr_id (dr_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** 드림의 작품 목록 (+회차 수 집계) */
function dr_novels_list($dr_id) {
    dr_novels_ensure_schema();
    $novels = qAll("SELECT * FROM {dream_novels} WHERE dr_id = ? ORDER BY dn_order ASC, dn_id DESC", [(int)$dr_id]);
    foreach ($novels as &$n) {
        $c = qOne("SELECT COUNT(*) AS cnt, MAX(dc_updated_at) AS latest FROM {dream_chapters} WHERE dn_id = ?", [(int)$n['dn_id']]);
        $n['chapter_count'] = (int)($c['cnt'] ?? 0);
        $n['latest_chapter_update'] = $c['latest'] ?? null;
    }
    return $novels;
}

/** 단일 작품 (소유 검증: dr_id 일치) */
function dr_novel_get($dr_id, $dn_id) {
    dr_novels_ensure_schema();
    return qOne("SELECT * FROM {dream_novels} WHERE dn_id = ? AND dr_id = ?", [(int)$dn_id, (int)$dr_id]);
}

/** 작품 저장 (dn_id>0 수정, 아니면 신규) → dn_id */
function dr_novel_save($data) {
    dr_novels_ensure_schema();
    $dn_id = (int)($data['dn_id'] ?? 0);
    $f = [
        (int)($data['dr_id'] ?? 0), (int)($data['bo_id'] ?? 0),
        mb_substr(trim($data['dn_title'] ?? ''), 0, 200),
        mb_substr(trim($data['dn_subtitle'] ?? ''), 0, 200),
        mb_substr(trim($data['dn_category'] ?? ''), 0, 50),
        mb_substr(trim($data['dn_cover'] ?? ''), 0, 500),
        trim($data['dn_description'] ?? ''),
        trim($data['dn_status'] ?? 'ongoing'),
        trim($data['dn_rating'] ?? 'all'),
        (int)($data['dn_secret'] ?? 0),
    ];
    if ($dn_id > 0) {
        $f[] = $dn_id;
        qExec("UPDATE {dream_novels} SET dr_id=?, bo_id=?, dn_title=?, dn_subtitle=?, dn_category=?,
               dn_cover=?, dn_description=?, dn_status=?, dn_rating=?, dn_secret=?, dn_updated_at=NOW()
               WHERE dn_id=?", $f);
        return $dn_id;
    }
    $ord = qOne("SELECT COALESCE(MAX(dn_order),0)+1 AS n FROM {dream_novels} WHERE dr_id = ?", [(int)($data['dr_id'] ?? 0)]);
    $f[] = (int)$ord['n'];
    return qInsert("INSERT INTO {dream_novels}
        (dr_id, bo_id, dn_title, dn_subtitle, dn_category, dn_cover, dn_description, dn_status, dn_rating, dn_secret, dn_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)", $f);
}

/** 작품 삭제 (회차 연쇄) */
function dr_novel_delete($dr_id, $dn_id) {
    dr_novels_ensure_schema();
    $own = qOne("SELECT dn_id FROM {dream_novels} WHERE dn_id = ? AND dr_id = ?", [(int)$dn_id, (int)$dr_id]);
    if (!$own) return;
    qExec("DELETE FROM {dream_chapters} WHERE dn_id = ?", [(int)$dn_id]);
    qExec("DELETE FROM {dream_novels} WHERE dn_id = ?", [(int)$dn_id]);
}

/** 작품의 회차 목록 (본문 제외) */
function dr_chapters_list($dn_id) {
    dr_novels_ensure_schema();
    return qAll("SELECT dc_id, dn_id, dr_id, dc_number, dc_title, dc_word_count, dc_view_count,
                 dc_secret, dc_order, dc_created_at, dc_updated_at
                 FROM {dream_chapters} WHERE dn_id = ? ORDER BY dc_order ASC, dc_number ASC", [(int)$dn_id]);
}

/** 단일 회차 (본문 포함) */
function dr_chapter_get($dn_id, $dc_id) {
    dr_novels_ensure_schema();
    return qOne("SELECT * FROM {dream_chapters} WHERE dc_id = ? AND dn_id = ?", [(int)$dc_id, (int)$dn_id]);
}

/** 회차 저장 (dc_id>0 수정, 아니면 신규) → dc_id */
function dr_chapter_save($data) {
    dr_novels_ensure_schema();
    $dc_id   = (int)($data['dc_id'] ?? 0);
    $content = $data['dc_content'] ?? '';
    $wc      = mb_strlen(strip_tags($content));
    $dn_id   = (int)($data['dn_id'] ?? 0);
    $number  = (int)($data['dc_number'] ?? 0);
    $title   = mb_substr(trim($data['dc_title'] ?? ''), 0, 200);
    $secret  = (int)($data['dc_secret'] ?? 0);

    if ($dc_id > 0) {
        if ($number > 0) {
            qExec("UPDATE {dream_chapters} SET dc_number=?, dc_title=?, dc_content=?, dc_word_count=?, dc_secret=?, dc_updated_at=NOW() WHERE dc_id=?",
                  [$number, $title, $content, $wc, $secret, $dc_id]);
        } else {
            qExec("UPDATE {dream_chapters} SET dc_title=?, dc_content=?, dc_word_count=?, dc_secret=?, dc_updated_at=NOW() WHERE dc_id=?",
                  [$title, $content, $wc, $secret, $dc_id]);
        }
        return $dc_id;
    }
    if ($number <= 0) {
        $mn = qOne("SELECT COALESCE(MAX(dc_number),0)+1 AS n FROM {dream_chapters} WHERE dn_id = ?", [$dn_id]);
        $number = (int)$mn['n'];
    }
    $mo = qOne("SELECT COALESCE(MAX(dc_order),0)+1 AS n FROM {dream_chapters} WHERE dn_id = ?", [$dn_id]);
    return qInsert("INSERT INTO {dream_chapters} (dn_id, dr_id, dc_number, dc_title, dc_content, dc_word_count, dc_secret, dc_order)
                    VALUES (?,?,?,?,?,?,?,?)",
                   [$dn_id, (int)($data['dr_id'] ?? 0), $number, $title, $content, $wc, $secret, (int)$mo['n']]);
}

/** 회차 삭제 */
function dr_chapter_delete($dn_id, $dc_id) {
    dr_novels_ensure_schema();
    qExec("DELETE FROM {dream_chapters} WHERE dc_id = ? AND dn_id = ?", [(int)$dc_id, (int)$dn_id]);
}

/** 회차 조회수 +1 */
function dr_chapter_view_inc($dc_id) {
    qExec("UPDATE {dream_chapters} SET dc_view_count = dc_view_count + 1 WHERE dc_id = ?", [(int)$dc_id]);
}

function dr_novel_status_label($s) {
    $m = ['ongoing'=>'연재중','completed'=>'완결','hiatus'=>'휴재','draft'=>'초안'];
    return $m[$s] ?? $s;
}
function dr_novel_rating_label($r) {
    $m = ['all'=>'전체','teen'=>'15+','adult'=>'19+'];
    return $m[$r] ?? $r;
}

/* ──────────────────────────────────────────────
   4) POST 핸들러
   ────────────────────────────────────────────── */
function dr_handle_post($board, $bo_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    csrf_check();
    require_admin();

    $action = $_POST['action'] ?? '';
    $back   = hp_url('board', ['slug' => $board['bo_slug']]);
    $slug   = $board['bo_slug'];

    // ─── 등록 / 수정 ───
    if ($action === 'new_dream' || $action === 'update_dream') {
        $is_update = ($action === 'update_dream');
        $po_id     = (int)($_POST['po_id'] ?? 0);
        $existing  = null;
        $existing_data = [];

        if ($is_update) {
            $existing = qOne("SELECT * FROM {post} WHERE po_id = ? AND po_bo_id = ?", [$po_id, $bo_id]);
            if (!$existing) hp_redirect($back);
            $existing_data = dr_data($existing);
        }

        $title = trim($_POST['dr_title'] ?? '');
        if ($title === '') {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '페어 이름은 필수예요.'];
            hp_redirect($back);
        }

        // 카테고리 검증
        $category = trim($_POST['dr_category'] ?? '');
        if (function_exists('hp_board_categories')) {
            $valid = hp_board_categories($board);
            if ($category !== '' && !in_array($category, $valid, true)) $category = '';
        }

        // 페어 대표색
        $color = trim($_POST['dr_color'] ?? '');
        if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '';

        $is_priv = !empty($_POST['dr_secret']) ? 1 : 0;

        // ─── 썸네일 (페어 패턴: thumb_url/thumb_file) ───
        $thumb = $is_update ? ($existing['po_thumbnail'] ?? '') : '';
        $new_thumb = dr_resolve_image('thumb_file', 'thumb_url', $slug);
        if ($new_thumb) {
            if ($thumb) dr_unlink_local($thumb, $slug);
            $thumb = $new_thumb;
        }

        // ─── 헤더 이미지 (dr_header_url/dr_header_file) ───
        $header = $existing_data['header_image'] ?? '';
        $new_header = dr_resolve_image('dr_header_file', 'dr_header_url', $slug);
        if ($new_header) {
            if ($header) dr_unlink_local($header, $slug);
            $header = $new_header;
        }

        // ─── 캐릭터 A/B 파싱 ───
        $char_a = dr_parse_char_post('a', $existing_data['char_a'] ?? [], $slug);
        $char_b = dr_parse_char_post('b', $existing_data['char_b'] ?? [], $slug);

        // ─── 분위기 컷 ───
        $ext_images = [];
        foreach (($_POST['ext_image'] ?? []) as $u) {
            $u = trim((string)$u);
            if ($u !== '' && preg_match('#^https?://#i', $u)) $ext_images[] = $u;
        }

        // 일상 로그는 유지 (별도 관리)
        $daily = $existing_data['daily'] ?? [];

        $data = [
            'color'         => $color,
            'header_image'  => $header,
            'header_credit' => mb_substr(trim($_POST['dr_header_credit'] ?? ''), 0, 200),
            'content'       => mb_substr(trim($_POST['dr_content'] ?? ''), 0, 8000),
            'char_a'        => $char_a,
            'char_b'        => $char_b,
            'ext_images'    => $ext_images,
            'daily'         => $daily,
        ];

        // 서브타이틀 = "A × B" 자동
        $subtitle = trim(($char_a['name'] ?? '') . ' × ' . ($char_b['name'] ?? ''), ' ×');

        // 썸네일 폴백: 헤더 → A 대표 → B 대표
        if (!$thumb) {
            if ($header) $thumb = $header;
            elseif (!empty($char_a['main_img'])) $thumb = $char_a['main_img'];
            elseif (!empty($char_b['main_img'])) $thumb = $char_b['main_img'];
        }

        $content_json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($is_update) {
            qExec(
                "UPDATE {post}
                    SET po_title = ?, po_subtitle = ?, po_content = ?, po_thumbnail = ?,
                        po_category = ?, po_is_private = ?, po_updated_at = NOW()
                  WHERE po_id = ?",
                [
                    mb_substr($title, 0, 200), mb_substr($subtitle, 0, 200),
                    $content_json, $thumb, $category ?: null, $is_priv, $po_id,
                ]
            );
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '수정되었어요.'];
            hp_redirect(hp_url('view', ['po_id' => $po_id]));
        } else {
            $po_id = qInsert(
                "INSERT INTO {post}
                    (po_bo_id, po_title, po_subtitle, po_content, po_thumbnail,
                     po_category, po_is_private, po_created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $bo_id, mb_substr($title, 0, 200), mb_substr($subtitle, 0, 200),
                    $content_json, $thumb, $category ?: null, $is_priv,
                ]
            );
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '드림이 등록됐어요.'];
            hp_redirect(hp_url('view', ['po_id' => $po_id]));
        }
    }

    // ─── 드림 삭제 (하위 글도 CASCADE) ───
    if ($action === 'delete_dream') {
        $po_id = (int)($_POST['po_id'] ?? 0);
        $post  = qOne("SELECT * FROM {post} WHERE po_id = ? AND po_bo_id = ?", [$po_id, $bo_id]);
        if ($post) {
            // 본체 이미지 정리
            $d = dr_data($post);
            dr_unlink_local($post['po_thumbnail'] ?? '', $slug);
            dr_unlink_local($d['header_image'] ?? '', $slug);

            // 하위 글 정리 (_dreamlog)
            $logs = qAll("SELECT p.po_id, p.po_thumbnail FROM {dream_post} dp
                          JOIN {post} p ON p.po_id = dp.dp_po_id
                          WHERE dp.dp_dream_po_id = ?", [$po_id]);
            foreach ($logs as $log) {
                dr_unlink_local($log['po_thumbnail'] ?? '', '_dreamlog');
                qExec("DELETE FROM {post} WHERE po_id = ?", [$log['po_id']]);
            }
            qExec("DELETE FROM {dream_post} WHERE dp_dream_po_id = ?", [$po_id]);
            qExec("DELETE FROM {post} WHERE po_id = ?", [$po_id]);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '드림이 삭제됐어요.'];
        }
        hp_redirect($back);
    }

    // ─── 하위 게시글: 추가 / 수정 / 삭제 (게시글·메모·커미션) ───
    if (in_array($action, ['dp_add', 'dp_update', 'dp_delete'], true)) {
        $dream_po_id = (int)($_POST['po_id'] ?? 0);
        $dream = qOne("SELECT * FROM {post} WHERE po_id = ? AND po_bo_id = ?", [$dream_po_id, $bo_id]);
        if (!$dream) hp_redirect($back);

        $kind = $_POST['kind'] ?? 'post';
        if (!in_array($kind, ['post', 'memo', 'commission'], true)) $kind = 'post';

        $view_url = hp_url('view', ['po_id' => $dream_po_id]);
        $log_bo_id = dr_log_board_id();
        if (!$log_bo_id) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '하위 게시판 초기화 실패'];
            hp_redirect($view_url);
        }
        $log_slug = '_dreamlog';

        // 삭제
        if ($action === 'dp_delete') {
            $sub_po_id = (int)($_POST['sub_po_id'] ?? 0);
            $own = qOne("SELECT dp_id FROM {dream_post} WHERE dp_dream_po_id = ? AND dp_po_id = ?",
                        [$dream_po_id, $sub_po_id]);
            if ($own) {
                $sub = qOne("SELECT po_thumbnail FROM {post} WHERE po_id = ?", [$sub_po_id]);
                if ($sub) dr_unlink_local($sub['po_thumbnail'] ?? '', $log_slug);
                qExec("DELETE FROM {post} WHERE po_id = ?", [$sub_po_id]);
                qExec("DELETE FROM {dream_post} WHERE dp_po_id = ?", [$sub_po_id]);
                $_SESSION['_flash'] = ['type' => 'success', 'msg' => '삭제되었어요.'];
            }
            hp_redirect($view_url);
        }

        // 추가 / 수정
        $title   = trim($_POST['dp_title']   ?? '');
        $content = $_POST['dp_post_content'] ?? '';
        if ($title === '') {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '제목을 입력해주세요.'];
            hp_redirect($view_url);
        }

        // 썸네일: 파일/URL → 없으면 본문 첫 이미지
        $thumb = dr_resolve_image('dp_thumb_file', 'dp_thumb_url', $log_slug);
        if (!$thumb && $content !== '') {
            if (preg_match('/<img[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $content, $m)) {
                $thumb = $m[1];
            }
        }

        if ($action === 'dp_add') {
            $sub_po_id = qInsert(
                "INSERT INTO {post} (po_bo_id, po_title, po_content, po_thumbnail, po_created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$log_bo_id, mb_substr($title, 0, 200), $content, $thumb]
            );
            $max = qOne("SELECT IFNULL(MAX(dp_order), 0) AS n FROM {dream_post} WHERE dp_dream_po_id = ?",
                        [$dream_po_id]);
            qInsert(
                "INSERT INTO {dream_post} (dp_dream_po_id, dp_po_id, dp_kind, dp_order)
                 VALUES (?, ?, ?, ?)",
                [$dream_po_id, $sub_po_id, $kind, ((int)$max['n']) + 1]
            );
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => dr_kind_label($kind) . '이(가) 추가되었어요.'];
        } else { // dp_update
            $sub_po_id = (int)($_POST['sub_po_id'] ?? 0);
            $own = qOne("SELECT dp_id FROM {dream_post} WHERE dp_dream_po_id = ? AND dp_po_id = ?",
                        [$dream_po_id, $sub_po_id]);
            if (!$own) hp_redirect($view_url);
            $existing = qOne("SELECT po_thumbnail FROM {post} WHERE po_id = ?", [$sub_po_id]);
            $final_thumb = $existing['po_thumbnail'] ?? null;
            if ($thumb) {
                dr_unlink_local($existing['po_thumbnail'] ?? '', $log_slug);
                $final_thumb = $thumb;
            }
            qExec(
                "UPDATE {post} SET po_title = ?, po_content = ?, po_thumbnail = ?, po_updated_at = NOW()
                  WHERE po_id = ?",
                [mb_substr($title, 0, 200), $content, $final_thumb, $sub_po_id]
            );
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '수정되었어요.'];
        }
        hp_redirect($view_url);
    }

    // ─── 이야기(소설): 작품 / 회차 CRUD ───
    if (in_array($action, ['dn_save', 'dn_delete', 'dc_save', 'dc_delete'], true)) {
        $dream_po_id = (int)($_POST['po_id'] ?? 0);
        $dream = qOne("SELECT po_id FROM {post} WHERE po_id = ? AND po_bo_id = ?", [$dream_po_id, $bo_id]);
        if (!$dream) hp_redirect($back);
        $view_url = hp_url('view', ['po_id' => $dream_po_id]);

        // 작품 저장
        if ($action === 'dn_save') {
            $title = trim($_POST['dn_title'] ?? '');
            if ($title === '') {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '작품 제목을 입력해주세요.'];
                hp_redirect($view_url);
            }
            $cover = dr_resolve_image('dn_cover_file', 'dn_cover_url', '_dreamlog');
            $dn_id = (int)($_POST['dn_id'] ?? 0);
            if (!$cover && $dn_id) {
                $ex = dr_novel_get($dream_po_id, $dn_id);
                $cover = $ex['dn_cover'] ?? '';
            }
            dr_novel_save([
                'dn_id'          => $dn_id,
                'dr_id'          => $dream_po_id,
                'bo_id'          => $bo_id,
                'dn_title'       => $title,
                'dn_subtitle'    => $_POST['dn_subtitle'] ?? '',
                'dn_category'    => $_POST['dn_category'] ?? '',
                'dn_cover'       => $cover,
                'dn_description' => $_POST['dn_description'] ?? '',
                'dn_status'      => $_POST['dn_status'] ?? 'ongoing',
                'dn_rating'      => $_POST['dn_rating'] ?? 'all',
                'dn_secret'      => !empty($_POST['dn_secret']) ? 1 : 0,
            ]);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '작품이 저장되었어요.'];
            hp_redirect($view_url);
        }

        // 작품 삭제
        if ($action === 'dn_delete') {
            dr_novel_delete($dream_po_id, (int)($_POST['dn_id'] ?? 0));
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '작품이 삭제되었어요.'];
            hp_redirect($view_url);
        }

        // 회차 저장
        if ($action === 'dc_save') {
            $dn_id = (int)($_POST['dn_id'] ?? 0);
            $novel = dr_novel_get($dream_po_id, $dn_id);
            if (!$novel) hp_redirect($view_url);
            $ctitle = trim($_POST['dc_title'] ?? '');
            if ($ctitle === '') {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '회차 제목을 입력해주세요.'];
                hp_redirect($view_url);
            }
            dr_chapter_save([
                'dc_id'     => (int)($_POST['dc_id'] ?? 0),
                'dn_id'     => $dn_id,
                'dr_id'     => $dream_po_id,
                'dc_number' => (int)($_POST['dc_number'] ?? 0),
                'dc_title'  => $ctitle,
                'dc_content'=> $_POST['dc_content'] ?? '',
                'dc_secret' => !empty($_POST['dc_secret']) ? 1 : 0,
            ]);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '회차가 저장되었어요.'];
            hp_redirect($view_url . '#story-' . $dn_id);
        }

        // 회차 삭제
        if ($action === 'dc_delete') {
            $dn_id = (int)($_POST['dn_id'] ?? 0);
            $novel = dr_novel_get($dream_po_id, $dn_id);
            if ($novel) dr_chapter_delete($dn_id, (int)($_POST['dc_id'] ?? 0));
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '회차가 삭제되었어요.'];
            hp_redirect($view_url . '#story-' . $dn_id);
        }
    }
}
