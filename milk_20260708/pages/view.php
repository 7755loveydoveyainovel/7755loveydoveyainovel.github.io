<?php
/**
 * pages/view.php — 게시글 상세
 *
 * URL: /?p=view&po_id=1
 * 책임: 게시글 + 댓글 로드, 댓글 등록 처리, 스킨 위임
 */

$po_id = (int)($_GET['po_id'] ?? 0);

if (!isset($_SESSION['cm_unlocked']) || !is_array($_SESSION['cm_unlocked'])) {
    $_SESSION['cm_unlocked'] = [];
}
if (!isset($_SESSION['post_unlocked']) || !is_array($_SESSION['post_unlocked'])) {
    $_SESSION['post_unlocked'] = [];
}

// ─── 비공개 글 잠금 체크 (POST 처리보다 먼저) ───
$_pre_post = $po_id
    ? qOne("SELECT po_id, po_bo_id, po_is_private, po_pw_hash FROM {post} WHERE po_id = ?", [$po_id])
    : null;
$_is_locked = $_pre_post
    && !empty($_pre_post['po_is_private'])
    && !is_admin()
    && !in_array($po_id, $_SESSION['post_unlocked'], true);

$unlock_error = null;

if ($_is_locked) {
    // 잠금 해제 시도
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unlock_post') {
        csrf_check();
        $pw = $_POST['post_pw'] ?? '';
        if (!empty($_pre_post['po_pw_hash']) && $pw !== '' && password_verify($pw, $_pre_post['po_pw_hash'])) {
            $_SESSION['post_unlocked'][] = $po_id;
            hp_redirect(hp_url('view', ['po_id' => $po_id]));
        }
        $unlock_error = '비밀번호가 일치하지 않습니다.';
    }
    // 잠금 화면 렌더 후 종료 — 댓글 등 다른 POST 액션은 차단됨
    $post  = qOne("SELECT * FROM {post} WHERE po_id = ?", [$po_id]);
    $board = hp_board_by_id($post['po_bo_id']);
    include HP_PATH . '/inc/post-locked.php';
    return;
}

// ─── POST 처리 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $po_id) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // 댓글 등록
    if ($action === 'comment') {
        // 스팸 방지 — IP 단위 1분 5건 제한 (관리자는 면제)
        if (!is_admin()) {
            $rl_key = 'comment:' . ($_SERVER['REMOTE_ADDR'] ?? '');
            if (!hp_ratelimit_check($rl_key, 5, 60)) {
                $wait = hp_ratelimit_retry_after($rl_key, 60);
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => "잠시 후 다시 시도해주세요. ({$wait}초)"];
                hp_redirect(hp_url('view', ['po_id' => $po_id]));
            }
            hp_ratelimit_hit($rl_key);
        }

        $content   = trim($_POST['content'] ?? '');
        $parent_id = (int)($_POST['parent_id'] ?? 0);

        // parent_id 검증 — 같은 글의 댓글이어야 (없으면 0)
        if ($parent_id > 0) {
            $parent_ok = qVal("SELECT COUNT(*) FROM {comment} WHERE cm_id = ? AND cm_po_id = ?", [$parent_id, $po_id]);
            if (!$parent_ok) $parent_id = 0;
        }

        if ($content === '') {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '내용을 입력해주세요.'];
        }
        // 관리자 — 닉네임 자동 + 비공개 옵션 없음
        elseif (is_admin()) {
            $name = hp_config('admin_nickname', '') ?: hp_config('site_name', '주인장');
            qInsert(
                "INSERT INTO {comment} (cm_po_id, cm_name, cm_pw_hash, cm_content, cm_is_private, cm_parent_id, cm_created_at)
                 VALUES (?, ?, NULL, ?, 0, ?, NOW())",
                [$po_id, mb_substr($name, 0, 40), mb_substr($content, 0, 1000), $parent_id ?: null]
            );
        }
        // 방문자
        else {
            $name    = trim($_POST['name']    ?? '');
            $pw      = $_POST['password']     ?? '';
            $private = !empty($_POST['private']) ? 1 : 0;

            if ($name === '') {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '이름을 입력해주세요.'];
            } else {
                $err = hp_check_blocked($name . "\n" . $content);
                if ($err) {
                    $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err];
                } else {
                    // private + 비번 없음 → 일괄 비밀번호 hash 사용 (있다면)
                    if ($pw !== '') {
                        $hash = password_hash($pw, PASSWORD_DEFAULT);
                    } elseif ($private) {
                        $bulk = hp_config('bulk_private_pw_hash', '');
                        $hash = $bulk ?: null;
                    } else {
                        $hash = null;
                    }
                    qInsert(
                        "INSERT INTO {comment} (cm_po_id, cm_name, cm_pw_hash, cm_content, cm_is_private, cm_parent_id, cm_created_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $po_id,
                            mb_substr($name, 0, 40),
                            $hash,
                            mb_substr($content, 0, 1000),
                            $private,
                            $parent_id ?: null,
                        ]
                    );
                }
            }
        }
    }

    // 비공개 댓글 잠금 해제
    elseif ($action === 'unlock_comment') {
        $cm_id = (int)($_POST['cm_id'] ?? 0);
        $pw    = $_POST['password']    ?? '';
        $cm    = qOne("SELECT * FROM {comment} WHERE cm_id = ? AND cm_is_private = 1", [$cm_id]);

        if ($cm && $cm['cm_pw_hash'] && password_verify($pw, $cm['cm_pw_hash'])) {
            $_SESSION['cm_unlocked'][] = $cm_id;
            $_SESSION['cm_unlocked'] = array_unique($_SESSION['cm_unlocked']);
        } else {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '비밀번호가 일치하지 않아요.'];
        }
    }

    // 댓글 수정
    elseif ($action === 'edit_comment') {
        // 스팸 방지 — 같은 'comment:' 키 공유로 합산 (관리자는 면제)
        if (!is_admin()) {
            $rl_key = 'comment:' . ($_SERVER['REMOTE_ADDR'] ?? '');
            if (!hp_ratelimit_check($rl_key, 5, 60)) {
                $wait = hp_ratelimit_retry_after($rl_key, 60);
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => "잠시 후 다시 시도해주세요. ({$wait}초)"];
                hp_redirect(hp_url('view', ['po_id' => $po_id]));
            }
            hp_ratelimit_hit($rl_key);
        }

        $cm_id   = (int)($_POST['cm_id']  ?? 0);
        $pw      = $_POST['password']     ?? '';
        $content = trim($_POST['content'] ?? '');
        $cm      = qOne("SELECT * FROM {comment} WHERE cm_id = ?", [$cm_id]);

        $can_edit = false;
        if ($cm && $content !== '') {
            if (is_admin()) {
                $can_edit = true;
            } elseif ($cm['cm_pw_hash'] && password_verify($pw, $cm['cm_pw_hash'])) {
                $can_edit = true;
            }
        }

        if ($can_edit) {
            // 관리자가 아닐 때만 spam check
            $err = is_admin() ? null : hp_check_blocked($content);
            if ($err) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err];
            } else {
                qExec(
                    "UPDATE {comment} SET cm_content = ? WHERE cm_id = ?",
                    [mb_substr($content, 0, 1000), $cm_id]
                );
                $_SESSION['_flash'] = ['type' => 'success', 'msg' => '수정되었어요.'];
            }
        } elseif ($cm) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '비밀번호가 일치하지 않거나 내용이 비어있어요.'];
        }
    }

    // 댓글 삭제
    elseif ($action === 'delete_comment') {
        $cm_id = (int)($_POST['cm_id'] ?? 0);
        $pw    = $_POST['password']    ?? '';
        $cm    = qOne("SELECT * FROM {comment} WHERE cm_id = ?", [$cm_id]);

        $can_delete = false;
        if ($cm) {
            if (is_admin()) {
                $can_delete = true;
            } elseif ($cm['cm_pw_hash'] && password_verify($pw, $cm['cm_pw_hash'])) {
                $can_delete = true;
            }
        }

        if ($can_delete) {
            qExec("DELETE FROM {comment} WHERE cm_id = ?", [$cm_id]);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '삭제되었어요.'];
        } else {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '삭제 권한이 없거나 비밀번호가 일치하지 않아요.'];
        }
    }

    hp_redirect(hp_url('view', ['po_id' => $po_id]));
}

// ─── 게시글 로드 ───
$post = qOne("SELECT * FROM {post} WHERE po_id = ?", [$po_id]);
if (!$post) {
    include HP_PATH . '/pages/home.php';
    return;
}

$board = hp_board_by_id($post['po_bo_id']);
if (!$board) {
    include HP_PATH . '/pages/home.php';
    return;
}
// 자동 섹션 전환은 라우터(index.php) 에서 일괄 처리함

// 권한
if ($board['bo_admin_only'] && !is_admin()) {
    echo '<div class="crumb"><span class="path"><strong>접근 권한이 없습니다</strong></span></div>';
    return;
}
// 비밀번호 보호 게시판 — 잠금 해제 안 됐으면 게시판 페이지로 (거기서 비밀번호 입력)
if (hp_board_is_protected($board) && !hp_board_is_unlocked($board)) {
    $unlock_error = null;
    include HP_PATH . '/inc/board-locked.php';
    return;
}

// 조회수 +1
qExec("UPDATE {post} SET po_views = po_views + 1 WHERE po_id = ?", [$po_id]);

// 댓글
$comments = qAll(
    "SELECT * FROM {comment} WHERE cm_po_id = ? ORDER BY cm_created_at ASC",
    [$po_id]
);

$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

// 스킨 위임
$skin = $board['bo_skin'] ?: 'list';
if (!preg_match('/^[a-z][a-z0-9_-]*$/', $skin)) $skin = 'list';

$skin_dir = HP_PATH . "/skins/board/{$skin}";
if (!file_exists("{$skin_dir}/view.php")) {
    $skin_dir = HP_PATH . '/skins/board/list';
}

include "{$skin_dir}/view.php";
