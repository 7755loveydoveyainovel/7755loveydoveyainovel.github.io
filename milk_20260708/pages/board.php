<?php
/**
 * pages/board.php — 게시판 목록
 *
 * URL: /?p=board&slug=diary  (또는 bo_id=1)
 * 책임: 게시판 정보 + 게시글 목록 + 페이지네이션 → 스킨 위임
 */

$bo_id = (int)($_GET['bo_id'] ?? 0);
$board = hp_board_by_id($bo_id);

if (!$board) {
    include HP_PATH . '/pages/home.php';
    return;
}

// 권한
if ($board['bo_admin_only'] && !is_admin()) {
    echo '<div class="crumb"><span class="path"><strong>접근 권한이 없습니다</strong></span></div>';
    return;
}

// 비밀번호 보호 게시판 — unlock 시도 + 잠금 화면
if (hp_board_is_protected($board) && !hp_board_is_unlocked($board)) {
    $unlock_error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unlock_board') {
        csrf_check();
        if (hp_board_try_unlock($board, $_POST['board_pw'] ?? '')) {
            hp_redirect(hp_url('board', ['slug' => $board['bo_slug']]));
        }
        $unlock_error = '비밀번호가 일치하지 않아요.';
    }
    include HP_PATH . '/inc/board-locked.php';
    return;
}

// 페이지네이션
$per_page = 20;
$page_num = max(1, (int)($_GET['page_num'] ?? 1));
$offset   = ($page_num - 1) * $per_page;

// 카테고리 필터 (게시판이 정의한 목록 중 하나여야)
$current_cat = trim($_GET['cat'] ?? '');
$valid_cats  = hp_board_categories($board);
if ($current_cat !== '' && !in_array($current_cat, $valid_cats, true)) {
    $current_cat = '';
}

$where_sql    = "WHERE po_bo_id = ?";
$where_params = [$bo_id];
if ($current_cat !== '') {
    $where_sql    .= " AND po_category = ?";
    $where_params[] = $current_cat;
}

$posts = qAll(
    "SELECT po_id, po_title, po_subtitle, po_content, po_thumbnail, po_views, po_created_at, po_category, po_extra, po_is_private
     FROM {post}
     $where_sql
     ORDER BY po_created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($where_params, [$per_page, $offset])
);

$total       = (int)qVal("SELECT COUNT(*) FROM {post} $where_sql", $where_params);
$total_pages = max(1, (int)ceil($total / $per_page));

// 스킨 위임
$skin = $board['bo_skin'] ?: 'list';
if (!preg_match('/^[a-z][a-z0-9_-]*$/', $skin)) $skin = 'list';

$skin_dir = HP_PATH . "/skins/board/{$skin}";
if (!file_exists("{$skin_dir}/index.php")) {
    $skin_dir = HP_PATH . '/skins/board/list';
}

include "{$skin_dir}/index.php";
