<?php
/**
 * lib/spam.php — 차단 IP / 금지 단어 검증
 *
 * config 의 'blocked_ips' (한 줄에 하나, 와일드카드 * 지원) 와
 * 'blocked_words' (한 줄에 하나, 대소문자 무관) 를 검사.
 *
 * 사용:
 *   $err = hp_check_blocked($content);
 *   if ($err) { echo $err; exit; }
 */

/**
 * 현재 요청자 IP 가 차단 IP 목록에 포함되는지
 *
 * 와일드카드:
 *  - IPv4: 192.168.*  → 옥텟 단위
 *  - IPv6: 2001:db8:* → 헥스텟 단위
 *  - IPv4-mapped IPv6 (::ffff:1.2.3.4) 는 IPv4 형태로 정규화 후 매칭
 */
function hp_ip_blocked($ip = null) {
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!$ip) return false;

    // IPv4-mapped IPv6 → IPv4 로 정규화
    // 예: ::ffff:192.168.0.1 → 192.168.0.1
    if (stripos($ip, '::ffff:') === 0 && substr_count($ip, '.') === 3) {
        $ip = substr($ip, 7);
    }

    $is_v6 = strpos($ip, ':') !== false;
    // IPv6 는 헥스텟 구분자가 ':', IPv4 는 '.'
    $sep_class = $is_v6 ? '[^:]*' : '[^.]*';

    $list = hp_config('blocked_ips', '');
    if (!$list) return false;

    $patterns = preg_split('/\r\n|\r|\n/', $list);
    foreach ($patterns as $p) {
        $p = trim($p);
        if ($p === '' || $p[0] === '#') continue;

        // 패턴이 IPv6 형태면 IPv6 주소에만, IPv4 형태면 IPv4 주소에만 매칭
        $p_is_v6 = strpos($p, ':') !== false;
        if ($p_is_v6 !== $is_v6) continue;

        // 와일드카드를 정규식으로 변환
        $regex = '/^' . str_replace('\*', $sep_class, preg_quote($p, '/')) . '$/i';
        if (preg_match($regex, $ip)) return true;
    }
    return false;
}

/**
 * 본문에 금지 단어가 들어있는지 검사
 *
 * @return string|null 발견된 단어 (있으면) / null (없으면)
 */
function hp_content_has_blocked_word($content) {
    if (!$content) return null;
    $list = hp_config('blocked_words', '');
    if (!$list) return null;

    $words = preg_split('/\r\n|\r|\n/', $list);
    $haystack = mb_strtolower($content);

    foreach ($words as $w) {
        $w = trim($w);
        if ($w === '' || $w[0] === '#') continue;
        if (mb_stripos($haystack, mb_strtolower($w)) !== false) {
            return $w;
        }
    }
    return null;
}

/**
 * 통합 검사 — IP + 단어
 *
 * @return string|null 한국어 에러 메시지 (차단 시) / null (통과)
 */
function hp_check_blocked($content = '') {
    if (hp_ip_blocked()) {
        return '죄송합니다. 이 IP 에서는 작성이 제한되어 있어요.';
    }
    $word = hp_content_has_blocked_word($content);
    if ($word !== null) {
        return '글에 사용할 수 없는 단어가 포함되어 있어요.';
    }
    return null;
}
