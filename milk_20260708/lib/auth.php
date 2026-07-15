<?php
/**
 * lib/auth.php — 관리자 인증
 *
 * 단일 관리자 모델 (개인 홈페이지이므로 다중 관리자 불필요)
 * 로그인은 ID + 비밀번호 방식.
 */

const ADMIN_SESSION_TIMEOUT = 86400 * 7;  // 7일

/**
 * 로그인 시도 (비밀번호만)
 *
 * 단일 관리자 사이트라 ID 는 의미 없음 → 비밀번호로만 인증.
 * DB 의 첫 번째 (사실상 유일한) 관리자 행과 비교.
 *
 * @return bool 성공 여부
 */
function admin_login($pw) {
    if (!$pw) return false;

    $row = qOne("SELECT * FROM {admin} ORDER BY ad_id LIMIT 1");
    if (!$row) return false;
    if (!password_verify($pw, $row['ad_pw_hash'])) return false;

    // 세션 고정 공격 방지 — 새 ID 발급.
    // delete_old_session=false: 공유 호스팅의 느린 디스크에서 옛 세션 파일을 즉시
    // 지우면 redirect 직후 새 세션을 못 찾는 레이스가 드물게 발생 → 옛 파일은 GC 에
    // 맡기고 부드럽게 전환. 새 ID 로 갈아타므로 고정 공격 방지 효과는 동일.
    session_regenerate_id(false);
    unset($_SESSION['_csrf']);

    $_SESSION['ad_id']         = (int)$row['ad_id'];
    $_SESSION['ad_login']      = $row['ad_login'];
    $_SESSION['ad_login_time'] = time();

    qExec("UPDATE {admin} SET ad_last_login = NOW() WHERE ad_id = ?", [$row['ad_id']]);
    return true;
}

function admin_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    session_destroy();
}

/**
 * 관리자 로그인 상태인지 확인 (+ 세션 타임아웃 처리)
 */
function is_admin() {
    if (empty($_SESSION['ad_id'])) return false;

    $login_time = $_SESSION['ad_login_time'] ?? 0;
    if (time() - $login_time > ADMIN_SESSION_TIMEOUT) {
        admin_logout();
        return false;
    }

    return true;
}

/**
 * 관리자 페이지에서 사용 — 로그인 안 돼 있으면 홈으로 강제 이동
 */
function require_admin() {
    if (!is_admin()) {
        header('Location: ' . HP_BASE . '/');
        exit;
    }
}

/**
 * 비밀번호 해싱 (install.php 와 비밀번호 변경 기능에서 사용)
 */
function admin_hash_password($pw) {
    return password_hash($pw, PASSWORD_DEFAULT);
}
