<?php
/**
 * lib/menu.php — 사이드바 + 모바일 탭바 데이터 로드
 *
 * 새 모델 (v1.6.0):
 *   섹션(section) → 그룹(menu_group) → 게시판(board)
 *   그룹의 mg_sec_id 가 NULL 이면 공용 (어떤 섹션에서든 표시)
 */

const HP_MOBILE_TAB_LIMIT = 4;

function hp_load_menu() {
    if (isset($GLOBALS['_hp_menu_cache'])) return $GLOBALS['_hp_menu_cache'];

    // 섹션 로드 — 새 테이블이 없을 수도 있으니 try
    $sections = [];
    try {
        $sections = qAll("SELECT * FROM {section} ORDER BY sec_order, sec_id");
    } catch (Throwable $e) {
        $sections = [];
    }

    $groups = qAll("SELECT * FROM {menu_group} ORDER BY mg_order, mg_id");
    $boards = qAll("SELECT * FROM {board} ORDER BY bo_order, bo_id");

    // 그룹별로 게시판 묶기
    $by_group  = [];
    $ungrouped = [];
    foreach ($boards as $b) {
        if (!empty($b['bo_admin_only']) && !is_admin()) continue;
        // 내부 전용 게시판 (slug 가 _ 로 시작) 은 메뉴에서 숨김.
        // 예: _charlog, _pairlog — 캐릭터/페어 view 안에서만 노출되는 종속 게시판
        if (!empty($b['bo_slug']) && substr($b['bo_slug'], 0, 1) === '_') continue;
        if (!empty($b['bo_mg_id'])) {
            $by_group[$b['bo_mg_id']][] = $b;
        } else {
            $ungrouped[] = $b;
        }
    }

    foreach ($groups as &$g) {
        $g['boards'] = $by_group[$g['mg_id']] ?? [];
    }
    unset($g);

    // 현재 섹션 결정 — ?section=N (정수) > 쿠키 > 첫 섹션
    $query_id  = isset($_GET['section']) ? (int)$_GET['section'] : 0;
    $cookie_id = (int)($_COOKIE['hp_section'] ?? 0);
    $current_section_id = $query_id > 0 ? $query_id : $cookie_id;

    // 유효성 — 존재하지 않는 섹션이면 첫 섹션으로 폴백
    $valid_ids = array_map('intval', array_column($sections, 'sec_id'));
    if (!in_array($current_section_id, $valid_ids, true)) {
        $current_section_id = $valid_ids[0] ?? 0;
    }

    // 모바일 탭바 핀 게시판 (bo_mobile_pinned 가 1 인 게시판)
    $pinned = array_filter($boards, function ($b) {
        if (!empty($b['bo_slug']) && substr($b['bo_slug'], 0, 1) === '_') return false;
        return !empty($b['bo_mobile_pinned'])
            && (empty($b['bo_admin_only']) || is_admin());
    });
    $pinned = array_slice($pinned, 0, HP_MOBILE_TAB_LIMIT);

    $cache = [
        'sections'           => $sections,
        'groups'             => $groups,
        'ungrouped'          => $ungrouped,
        'pinned'             => array_values($pinned),
        'all_boards'         => $boards,
        'current_section_id' => $current_section_id,
    ];
    $GLOBALS['_hp_menu_cache'] = $cache;
    return $cache;
}

/**
 * 슬러그로 게시판 찾기
 */
function hp_board_by_slug($slug) {
    if (!$slug) return null;
    return qOne("SELECT * FROM {board} WHERE bo_slug = ? LIMIT 1", [$slug]);
}

function hp_board_by_id($id) {
    return qOne("SELECT * FROM {board} WHERE bo_id = ? LIMIT 1", [(int)$id]);
}

/**
 * 게시판 컨텍스트로부터 현재 섹션 자동 결정.
 *
 * 사용자가 게시판 X 에 들어가면, X 가 속한 그룹의 mg_sec_id 를
 * 현재 섹션으로 set + 쿠키 갱신. drawer/sidebar 가 그 섹션의 메뉴를 보여줌.
 *
 * 호출 위치: 라우터 (slug → bo_id 변환 시) + view.php (post → board 결정 시)
 *
 * 다음 경우엔 갱신 안 함:
 *  - ?section=N 쿼리로 명시적 지정 (우선순위 최상)
 *  - board 의 그룹이 공용 (mg_sec_id 가 0/NULL)
 *  - board 가 어떤 그룹에도 속하지 않음
 */
function hp_set_section_from_board($board) {
    if (!$board) return;
    // 사용자가 ?section= 로 명시 지정한 경우는 건드리지 않음
    if (isset($_GET['section'])) return;

    $mg_id = (int)($board['bo_mg_id'] ?? 0);
    if ($mg_id <= 0) return;  // 그룹 미지정

    $group = qOne("SELECT mg_sec_id FROM {menu_group} WHERE mg_id = ?", [$mg_id]);
    if (!$group) return;

    $sec_id = (int)($group['mg_sec_id'] ?? 0);
    if ($sec_id <= 0) return;  // 공용 그룹

    // 이미 같은 섹션이면 쿠키 재설정 안 함 (불필요한 Set-Cookie 헤더 방지)
    $current = (int)($_COOKIE['hp_section'] ?? 0);
    if ($current === $sec_id) return;

    hp_set_section_cookie($sec_id);

    // menu 캐시 무효화 — 이 함수가 menu 로드 전에 호출되면 효과 없고 (캐시 자체가 없음),
    // menu 로드 후에 호출되면 다음 호출 시 새 섹션 반영해서 다시 로드함.
    if (function_exists('hp_reset_menu_cache')) {
        hp_reset_menu_cache();
    }
}

/** menu 캐시 무효화 (섹션이 도중에 바뀐 경우용) */
function hp_reset_menu_cache() {
    // hp_load_menu() 의 글로벌 캐시
    unset($GLOBALS['_hp_menu_cache']);
    // 라우터(index.php)에서 미리 로드해둔 $menu 변수도 비움.
    // sidebar/drawer/tabbar 가 `$menu = $menu ?? hp_load_menu()` 패턴을 쓰므로,
    // 이걸 비우지 않으면 옛 섹션의 메뉴가 그대로 표시됨.
    unset($GLOBALS['menu']);
}

/**
 * 현재 섹션에서 이 그룹이 보여야 하는지 판단.
 *  - 그룹의 mg_sec_id 가 NULL/0 → 공용, 항상 표시
 *  - 일치 → 표시
 *  - 그 외 → 숨김
 */
function hp_group_visible($group, $current_section_id) {
    $gs = (int)($group['mg_sec_id'] ?? 0);
    if ($gs === 0) return true;
    return $gs === (int)$current_section_id;
}
