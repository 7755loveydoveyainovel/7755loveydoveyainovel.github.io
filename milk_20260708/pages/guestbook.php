<?php
/**
 * pages/guestbook.php — 방명록
 *
 * URL: /?page=guestbook
 *
 * 책임:
 *  - 새 글 등록 / 답글 등록 (관리자만) / 비공개 글 비밀번호 잠금 해제 / 삭제
 *  - 방명록 목록 + 답글 트리 렌더
 */

if (hp_config('guestbook_enabled', '0') !== '1') {
    // 비활성화 → 홈으로
    include HP_PATH . '/pages/home.php';
    return;
}

if (!isset($_SESSION['gb_unlocked']) || !is_array($_SESSION['gb_unlocked'])) {
    $_SESSION['gb_unlocked'] = [];
}

// ─── POST 처리 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // 새 글 등록
    if ($action === 'new_entry') {
        // 스팸 방지 — IP 단위 1분 5건 제한 (관리자는 면제)
        if (!is_admin()) {
            $rl_key = 'gb:' . ($_SERVER['REMOTE_ADDR'] ?? '');
            if (!hp_ratelimit_check($rl_key, 5, 60)) {
                $wait = hp_ratelimit_retry_after($rl_key, 60);
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => "잠시 후 다시 시도해주세요. ({$wait}초)"];
                hp_redirect(hp_url('guestbook'));
            }
            hp_ratelimit_hit($rl_key);
        }

        $content = trim($_POST['content'] ?? '');

        if ($content === '') {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '내용을 입력해주세요.'];
        } else {
            // 관리자 → 닉네임 자동 + 공지 옵션
            if (is_admin()) {
                $admin_name = hp_config('admin_nickname', '') ?: hp_config('site_name', '주인장');
                $is_pinned  = !empty($_POST['pinned']) ? 1 : 0;
                qInsert(
                    "INSERT INTO {guestbook} (gb_name, gb_pw_hash, gb_content, gb_is_private, gb_is_pinned, gb_parent_id, gb_created_at)
                     VALUES (?, NULL, ?, 0, ?, NULL, NOW())",
                    [
                        mb_substr($admin_name, 0, 40),
                        mb_substr($content, 0, 2000),
                        $is_pinned,
                    ]
                );
                $_SESSION['_flash'] = ['type' => 'success', 'msg' => $is_pinned ? '공지가 등록되었어요.' : '방명록이 등록되었어요.'];
            }
            // 방문자 → 이름·비번·비공개 가능
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
                        if ($pw !== '') {
                            $hash = password_hash($pw, PASSWORD_DEFAULT);
                        } elseif ($private) {
                            $bulk = hp_config('bulk_private_pw_hash', '');
                            $hash = $bulk ?: null;
                        } else {
                            $hash = null;
                        }
                        qInsert(
                            "INSERT INTO {guestbook} (gb_name, gb_pw_hash, gb_content, gb_is_private, gb_is_pinned, gb_parent_id, gb_created_at)
                             VALUES (?, ?, ?, ?, 0, NULL, NOW())",
                            [
                                mb_substr($name, 0, 40),
                                $hash,
                                mb_substr($content, 0, 2000),
                                $private,
                            ]
                        );
                        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '방명록이 등록되었어요.'];
                    }
                }
            }
        }
    }

    // 답글 등록 (관리자 + 방문자)
    elseif ($action === 'reply') {
        // 스팸 방지 — 같은 'gb:' 키 공유로 new_entry 와 합산
        if (!is_admin()) {
            $rl_key = 'gb:' . ($_SERVER['REMOTE_ADDR'] ?? '');
            if (!hp_ratelimit_check($rl_key, 5, 60)) {
                $wait = hp_ratelimit_retry_after($rl_key, 60);
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => "잠시 후 다시 시도해주세요. ({$wait}초)"];
                hp_redirect(hp_url('guestbook'));
            }
            hp_ratelimit_hit($rl_key);
        }

        $parent_id = (int)($_POST['parent_id'] ?? 0);
        $content   = trim($_POST['content']   ?? '');

        if ($parent_id <= 0 || $content === '') {
            // 무시
        } else {
            $parent = qOne("SELECT gb_id FROM {guestbook} WHERE gb_id = ? AND gb_parent_id IS NULL", [$parent_id]);
            if ($parent) {
                if (is_admin()) {
                    // 관리자 — 닉네임 자동
                    $admin_name = hp_config('admin_nickname', '') ?: hp_config('site_name', '주인장');
                    qInsert(
                        "INSERT INTO {guestbook} (gb_name, gb_content, gb_is_private, gb_parent_id, gb_created_at)
                         VALUES (?, ?, 0, ?, NOW())",
                        [
                            mb_substr($admin_name, 0, 40),
                            mb_substr($content, 0, 2000),
                            $parent_id,
                        ]
                    );
                    $_SESSION['_flash'] = ['type' => 'success', 'msg' => '답글이 등록되었어요.'];
                } else {
                    // 방문자 — 이름 필수, 비번 선택
                    $name = trim($_POST['name'] ?? '');
                    $pw   = $_POST['password']  ?? '';

                    if ($name === '') {
                        $_SESSION['_flash'] = ['type' => 'error', 'msg' => '이름을 입력해주세요.'];
                    } else {
                        $err = hp_check_blocked($name . "\n" . $content);
                        if ($err) {
                            $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err];
                        } else {
                            if ($pw !== '') {
                                $hash = password_hash($pw, PASSWORD_DEFAULT);
                            } else {
                                // 답글은 비공개 옵션이 없음 — 비번만 있으면 본인 수정/삭제용 hash
                                $hash = null;
                            }
                            qInsert(
                                "INSERT INTO {guestbook} (gb_name, gb_pw_hash, gb_content, gb_is_private, gb_parent_id, gb_created_at)
                                 VALUES (?, ?, ?, 0, ?, NOW())",
                                [
                                    mb_substr($name, 0, 40),
                                    $hash,
                                    mb_substr($content, 0, 2000),
                                    $parent_id,
                                ]
                            );
                            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '답글이 등록되었어요.'];
                        }
                    }
                }
            }
        }
    }

    // 글 수정
    elseif ($action === 'edit') {
        // 스팸 방지 — 같은 'gb:' 키 공유로 합산 (관리자는 면제)
        if (!is_admin()) {
            $rl_key = 'gb:' . ($_SERVER['REMOTE_ADDR'] ?? '');
            if (!hp_ratelimit_check($rl_key, 5, 60)) {
                $wait = hp_ratelimit_retry_after($rl_key, 60);
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => "잠시 후 다시 시도해주세요. ({$wait}초)"];
                hp_redirect(hp_url('guestbook'));
            }
            hp_ratelimit_hit($rl_key);
        }

        $gb_id   = (int)($_POST['gb_id']  ?? 0);
        $pw      = $_POST['password']     ?? '';
        $content = trim($_POST['content'] ?? '');
        $entry   = qOne("SELECT * FROM {guestbook} WHERE gb_id = ?", [$gb_id]);

        $can_edit = false;
        if ($entry && $content !== '') {
            if (is_admin()) {
                $can_edit = true;
            } elseif ($entry['gb_pw_hash'] && password_verify($pw, $entry['gb_pw_hash'])) {
                $can_edit = true;
            }
        }

        if ($can_edit) {
            // 관리자가 아닐 때만 spam check (이름은 변경 안 되므로 본문만)
            $err = is_admin() ? null : hp_check_blocked($content);
            if ($err) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => $err];
            } else {
                qExec(
                    "UPDATE {guestbook} SET gb_content = ? WHERE gb_id = ?",
                    [mb_substr($content, 0, 2000), $gb_id]
                );
                $_SESSION['_flash'] = ['type' => 'success', 'msg' => '수정되었어요.'];
            }
        } elseif ($entry) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '비밀번호가 일치하지 않거나 내용이 비어있어요.'];
        }
    }

    // 비공개 글 잠금 해제
    elseif ($action === 'unlock') {
        $gb_id = (int)($_POST['gb_id'] ?? 0);
        $pw    = $_POST['password']    ?? '';
        $entry = qOne("SELECT * FROM {guestbook} WHERE gb_id = ? AND gb_is_private = 1", [$gb_id]);

        if ($entry && $entry['gb_pw_hash'] && password_verify($pw, $entry['gb_pw_hash'])) {
            $_SESSION['gb_unlocked'][] = $gb_id;
            $_SESSION['gb_unlocked'] = array_unique($_SESSION['gb_unlocked']);
        } else {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '비밀번호가 일치하지 않아요.'];
        }
    }

    // 삭제
    elseif ($action === 'delete') {
        $gb_id = (int)($_POST['gb_id'] ?? 0);
        $pw    = $_POST['password']    ?? '';
        $entry = qOne("SELECT * FROM {guestbook} WHERE gb_id = ?", [$gb_id]);

        $can_delete = false;
        if ($entry) {
            if (is_admin()) {
                $can_delete = true;
            } elseif ($entry['gb_pw_hash'] && password_verify($pw, $entry['gb_pw_hash'])) {
                $can_delete = true;
            }
        }

        if ($can_delete) {
            // 답글까지 함께 삭제
            qExec("DELETE FROM {guestbook} WHERE gb_id = ? OR gb_parent_id = ?", [$gb_id, $gb_id]);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '삭제되었어요.'];
        } else {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '삭제 권한이 없거나 비밀번호가 일치하지 않아요.'];
        }
    }

    hp_redirect(hp_url('guestbook'));
}

// ─── 데이터 로드 ───
$page_num    = max(1, (int)($_GET['page_num'] ?? 1));
$per_page    = 10;
$offset      = ($page_num - 1) * $per_page;

// 공지 (모든 페이지에 항상 표시)
$pinned_entries = qAll(
    "SELECT * FROM {guestbook}
     WHERE gb_parent_id IS NULL AND gb_is_pinned = 1
     ORDER BY gb_created_at DESC"
);

// 일반 글 (페이지네이션 적용)
$regular_total = (int)qVal("SELECT COUNT(*) FROM {guestbook} WHERE gb_parent_id IS NULL AND gb_is_pinned = 0");
$total       = $regular_total + count($pinned_entries);
$total_pages = max(1, (int)ceil($regular_total / $per_page));

$regular_entries = qAll(
    "SELECT * FROM {guestbook}
     WHERE gb_parent_id IS NULL AND gb_is_pinned = 0
     ORDER BY gb_created_at DESC
     LIMIT $per_page OFFSET $offset"
);

// 공지 + 일반 결합 (1페이지에는 공지 전부, 다음 페이지부터는 공지 없음)
$entries = $page_num === 1
    ? array_merge($pinned_entries, $regular_entries)
    : $regular_entries;

// 답글 한 번에 로드 후 부모별 그룹화
$parent_ids = array_column($entries, 'gb_id');
$replies_by_parent = [];
if ($parent_ids) {
    $placeholders = implode(',', array_fill(0, count($parent_ids), '?'));
    $replies = qAll(
        "SELECT * FROM {guestbook}
         WHERE gb_parent_id IN ($placeholders)
         ORDER BY gb_created_at ASC",
        $parent_ids
    );
    foreach ($replies as $r) {
        $replies_by_parent[$r['gb_parent_id']][] = $r;
    }
}

$flash = $_SESSION['_flash'] ?? null;

// ─── 스킨 위임 ───
// 기본 스킨: 'guestbook'. 사용자가 다른 스킨을 만들면 config 에서 변경.
$skin = hp_config('guestbook_skin', 'guestbook');
$skin_path = HP_PATH . "/skins/board/{$skin}/index.php";

// 스킨이 없거나 잘못된 경로면 기본 fallback
if (!preg_match('/^[a-z][a-z0-9_-]*$/', $skin) || !file_exists($skin_path)) {
    $skin_path = HP_PATH . '/skins/board/guestbook/index.php';
}

include $skin_path;
