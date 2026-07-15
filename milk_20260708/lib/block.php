<?php
/**
 * lib/block.php — 메인 페이지 블록 로드/렌더 + 데이터 헬퍼
 *
 * 메인 스킨이 hp_load_blocks() 로 활성 블록 목록을 가져와서
 * hp_render_block($block) 로 하나씩 렌더한다.
 *
 * 각 블록은 blocks/{type}/render.php 파일이 책임지고,
 * $block (mb_id, mb_type, mb_options[], options[]) 가 변수로 주어짐.
 */

/**
 * 활성 블록을 정렬 순서대로 가져옴
 */
function hp_load_blocks() {
    return qAll("
        SELECT mb_id, mb_type, mb_options
        FROM {main_block}
        WHERE mb_is_active = 1
        ORDER BY mb_order, mb_id
    ");
}

/**
 * 블록 데이터를 로드해서 주어진 스킨 폴더의 blocks/ 안에 있는
 * render 파일을 mb_order 순서대로 한꺼번에 실행한다.
 *
 * 메인 스킨의 index.php 에서 한 줄로 호출:
 *     hp_render_blocks_from(__DIR__);
 *
 * 각 블록 파일은 변수 $block (mb_id, mb_type, mb_options, options)
 * 와 $opts (디코딩된 옵션 배열) 를 전역 스코프에서 사용 가능.
 */
function hp_render_blocks_from($skin_dir) {
    foreach (hp_load_blocks() as $block) {
        hp_render_block_in($block, $skin_dir);
    }
}

/**
 * 단일 블록을 특정 스킨 폴더에서 찾아 렌더
 *
 * 새 메인 스킨이 자체 blocks/ 폴더를 갖지 않거나 일부만 갖고 있으면
 * default 스킨의 blocks/ 에서 자동으로 찾아 사용 (테마 상속 패턴).
 */
function hp_render_block_in($block, $skin_dir) {
    $type = $block['mb_type'] ?? '';

    // 디렉터리 트래버설/잘못된 블록 이름 차단
    if (!preg_match('/^[a-z][a-z0-9_-]*$/', $type)) return;

    $path = rtrim($skin_dir, '/\\') . "/blocks/{$type}.php";

    // 스킨에 해당 블록이 없으면 default 스킨 blocks/ 에서 찾음
    if (!file_exists($path)) {
        $fallback = HP_PATH . "/skins/main/default/blocks/{$type}.php";
        if (file_exists($fallback)) {
            $path = $fallback;
        }
    }

    if (!file_exists($path)) {
        // 누락된 블록은 관리자에게만 경고 표시
        if (is_admin()) {
            echo '<div class="home-card" style="border-color:#c33">';
            echo '<div class="home-block-label">⚠ 이 스킨에 누락된 블록: ' . h($type) . '</div>';
            echo '</div>';
        }
        return;
    }

    // 옵션 JSON 디코딩
    $opts = [];
    if (!empty($block['mb_options'])) {
        $decoded = json_decode($block['mb_options'], true);
        if (is_array($decoded)) $opts = $decoded;
    }
    $block['options'] = $opts;

    include $path;
}

// ═══════════════════════════════════════════════════════════════
//  메인 페이지 블록들이 자주 쓰는 데이터 헬퍼
//  (블록 render.php 에서 hp_get_xxx() 한 줄로 호출)
// ═══════════════════════════════════════════════════════════════

/**
 * 배너 이미지 src 변환
 *  - http(s):// 로 시작 → 외부 URL 그대로
 *  - 그 외 → data/banners/ 안의 로컬 파일 경로
 */
function hp_banner_src($bn_image) {
    if (!$bn_image) return '';
    if (preg_match('#^https?://#i', $bn_image)) return $bn_image;
    return HP_BASE . '/data/banners/' . $bn_image;
}

/** 본인 배너 (1개) */
function hp_get_self_banner() {
    return qOne(
        "SELECT * FROM {banner} WHERE bn_type = 'self' ORDER BY bn_order LIMIT 1"
    );
}

/** 친구 배너 목록 */
function hp_get_friend_banners($limit = 50) {
    return qAll(
        "SELECT * FROM {banner} WHERE bn_type = 'friend'
         ORDER BY bn_order, bn_id LIMIT " . (int)$limit
    );
}

/** 프로필 카드 소셜 링크 */
function hp_get_links() {
    return qAll("SELECT * FROM {link} ORDER BY lk_order, lk_id");
}

/**
 * 최근 글 (특정 게시판들로 한정 가능)
 *
 * @param int   $limit
 * @param int[] $bo_ids null 이면 전체 게시판
 */
function hp_get_recent_posts($limit = 6, $bo_ids = null) {
    $limit    = max(1, min(50, (int)$limit));
    $is_admin = is_admin() ? 1 : 0;

    if (is_array($bo_ids) && count($bo_ids) > 0) {
        $bo_ids = array_map('intval', $bo_ids);
        $place  = implode(',', array_fill(0, count($bo_ids), '?'));
        $params = $bo_ids;
        $params[] = $is_admin;
        return qAll(
            "SELECT p.po_id, p.po_title, p.po_created_at, p.po_bo_id,
                    b.bo_name, b.bo_slug, b.bo_skin
             FROM {post} p
             JOIN {board} b ON b.bo_id = p.po_bo_id
             WHERE p.po_bo_id IN ($place)
               AND (b.bo_admin_only = 0 OR ? = 1)
               AND (b.bo_password IS NULL OR b.bo_password = '')
             ORDER BY p.po_created_at DESC
             LIMIT " . $limit,
            $params
        );
    }

    return qAll(
        "SELECT p.po_id, p.po_title, p.po_created_at, p.po_bo_id,
                b.bo_name, b.bo_slug, b.bo_skin
         FROM {post} p
         JOIN {board} b ON b.bo_id = p.po_bo_id
         WHERE (b.bo_admin_only = 0 OR ? = 1)
           AND (b.bo_password IS NULL OR b.bo_password = '')
         ORDER BY p.po_created_at DESC
         LIMIT " . $limit,
        [$is_admin]
    );
}

/** 방명록 최근 N개 (비공개 제외) */
function hp_get_recent_guestbook($limit = 4) {
    $limit = max(1, min(50, (int)$limit));
    return qAll(
        "SELECT * FROM {guestbook}
         WHERE gb_is_private = 0 AND gb_parent_id IS NULL
         ORDER BY gb_created_at DESC
         LIMIT " . $limit
    );
}
