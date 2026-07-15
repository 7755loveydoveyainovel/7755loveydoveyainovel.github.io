<?php
/**
 * lib/ratelimit.php — 가벼운 파일 기반 시도 횟수 제한
 *
 * 무차별 대입(brute force) / 폼 도배 방지용. 키 단위로 슬라이딩 윈도우 안의
 * 시도 타임스탬프를 data/.ratelimit/{sha1(key)}.json 에 저장한다.
 *
 * 디렉터리는 점으로 시작하므로 data/.htaccess 의 ^\. 규칙에 의해
 * 외부에서 직접 접근이 차단된다.
 *
 * 사용 패턴:
 *   $key = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? '');
 *   if (!hp_ratelimit_check($key, 5, 300)) {
 *       // 5분 안에 5회 초과 → 거부
 *       $wait = hp_ratelimit_retry_after($key, 300);
 *       die("잠시 후 다시 시도해주세요. ({$wait}초)");
 *   }
 *   if (실패) hp_ratelimit_hit($key);
 *   if (성공) hp_ratelimit_clear($key);
 */

function _hp_ratelimit_path($key) {
    $dir = HP_PATH . '/data/.ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . '/' . sha1($key) . '.json';
}

/**
 * 윈도우 안 시도 횟수가 $max 미만이면 true (= 허용).
 * 호출 자체는 카운터를 올리지 않음 — 실패 시 hp_ratelimit_hit() 별도로 호출.
 *
 * @param string $key    임의 식별자 (보통 'action:ip' 형태)
 * @param int    $max    윈도우 안 허용 시도 횟수
 * @param int    $window 윈도우 길이(초)
 * @return bool 허용 여부
 */
function hp_ratelimit_check($key, $max, $window) {
    $path = _hp_ratelimit_path($key);
    if (!file_exists($path)) return true;
    $hits = json_decode(@file_get_contents($path), true);
    if (!is_array($hits)) return true;
    $cutoff = time() - $window;
    $recent = array_filter($hits, function ($t) use ($cutoff) { return $t >= $cutoff; });
    return count($recent) < $max;
}

/**
 * 시도 1회 기록 (실패한 액션 직후 호출)
 *
 * @param string $key
 * @param int    $window 이 윈도우 바깥의 오래된 시도는 정리
 */
function hp_ratelimit_hit($key, $window = 3600) {
    $path = _hp_ratelimit_path($key);
    $fp = @fopen($path, 'c+');
    if (!$fp) return;
    @flock($fp, LOCK_EX);
    $raw  = stream_get_contents($fp);
    $hits = $raw ? json_decode($raw, true) : [];
    if (!is_array($hits)) $hits = [];
    $cutoff = time() - $window;
    $hits = array_values(array_filter($hits, function ($t) use ($cutoff) { return $t >= $cutoff; }));
    $hits[] = time();
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($hits));
    @flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * 시도 카운터 초기화 (성공한 액션 직후 호출)
 */
function hp_ratelimit_clear($key) {
    $path = _hp_ratelimit_path($key);
    if (file_exists($path)) @unlink($path);
}

/**
 * 다음 시도까지 남은 초 (UI 안내용)
 */
function hp_ratelimit_retry_after($key, $window) {
    $path = _hp_ratelimit_path($key);
    if (!file_exists($path)) return 0;
    $hits = json_decode(@file_get_contents($path), true);
    if (!is_array($hits) || !$hits) return 0;
    $oldest = min($hits);
    return max(0, ($oldest + $window) - time());
}
