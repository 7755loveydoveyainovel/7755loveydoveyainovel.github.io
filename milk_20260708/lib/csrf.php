<?php
/**
 * lib/csrf.php — CSRF 토큰 발급/검증
 *
 * 사용:
 *  - 폼 안에  <?= csrf_input() ?>
 *  - POST 처리 시작에  csrf_check();
 *  - fetch() 호출에는 X-CSRF-Token 헤더를 자동 첨부 (index.php 의 인라인 스크립트)
 */

function csrf_token() {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_input() {
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

/**
 * POST/AJAX 요청에서 토큰 검증.
 * 실패 시 403 반환 후 종료.
 */
function csrf_check() {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('CSRF 검증 실패');
    }
}
