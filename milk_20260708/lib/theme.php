<?php
/**
 * lib/theme.php — 테마 프리셋 + 사용자 오버라이드 → CSS 변수 출력
 *
 * 4종 프리셋 (paper / linen / ink / midnight) 을 PHP 배열로 보유.
 * hp_config('theme_preset') 로 선택된 프리셋을 가져와서 :root 블록으로 출력.
 * hp_config('theme_overrides') 에 JSON 으로 일부 변수만 덮어쓸 수 있음.
 */

function hp_theme_presets() {
    return [
        'paper' => [
            '--bg'         => '#f5f1e8',
            '--paper'      => '#fffdf6',
            '--paper-2'    => '#faf5e8',
            '--ink'        => '#2a2520',
            '--ink-soft'   => '#5a544a',
            '--ink-mute'   => '#948c7e',
            '--hair'       => '#e6dfce',
            '--hair-soft'  => '#efe9d8',
            '--accent'     => '#b8533a',
            '--accent-soft'=> '#f5e8e0',
            '--accent-2'   => '#6b7c5b',
        ],
        'linen' => [
            '--bg'         => '#eef0f3',
            '--paper'      => '#ffffff',
            '--paper-2'    => '#f7f9fc',
            '--ink'        => '#1a1f2a',
            '--ink-soft'   => '#4a5260',
            '--ink-mute'   => '#8a93a3',
            '--hair'       => '#dde2ea',
            '--hair-soft'  => '#e9edf2',
            '--accent'     => '#3a6ea5',
            '--accent-soft'=> '#e3edf7',
            '--accent-2'   => '#5a8c7e',
        ],
        'ink' => [
            '--bg'         => '#1a1612',
            '--paper'      => '#221d18',
            '--paper-2'    => '#1e1914',
            '--ink'        => '#f0ebe0',
            '--ink-soft'   => '#b8b0a2',
            '--ink-mute'   => '#7a7468',
            '--hair'       => '#322c24',
            '--hair-soft'  => '#2a251f',
            '--accent'     => '#d4925a',
            '--accent-soft'=> '#2e251c',
            '--accent-2'   => '#9ab088',
        ],
        'midnight' => [
            '--bg'         => '#0f1218',
            '--paper'      => '#161a22',
            '--paper-2'    => '#131720',
            '--ink'        => '#e8ecf3',
            '--ink-soft'   => '#a8aebd',
            '--ink-mute'   => '#6a7080',
            '--hair'       => '#252a35',
            '--hair-soft'  => '#1d2129',
            '--accent'     => '#7eb3d4',
            '--accent-soft'=> '#1a2530',
            '--accent-2'   => '#b8a8d4',
        ],
    ];
}

function hp_theme_meta() {
    return [
        'paper'    => ['name' => 'Paper',    'mode' => 'light'],
        'linen'    => ['name' => 'Linen',    'mode' => 'light'],
        'ink'      => ['name' => 'Ink',      'mode' => 'dark'],
        'midnight' => ['name' => 'Midnight', 'mode' => 'dark'],
    ];
}

/**
 * 현재 선택된 테마의 변수 (오버라이드 병합 포함)
 */
function hp_theme_vars() {
    $preset_name = hp_config('theme_preset', 'paper');
    $presets     = hp_theme_presets();
    $vars        = $presets[$preset_name] ?? $presets['paper'];

    // 사용자 오버라이드 (JSON)
    $overrides_json = hp_config('theme_overrides', '');
    if ($overrides_json) {
        $overrides = json_decode($overrides_json, true);
        if (is_array($overrides)) {
            foreach ($overrides as $k => $v) {
                // CSS 변수 이름 검증 (--xxx 형식만)
                if (preg_match('/^--[a-z][a-z0-9-]*$/i', $k)) {
                    $vars[$k] = $v;
                }
            }
        }
    }

    return $vars;
}

/**
 * <style> 안에 들어갈 :root { ... } CSS 텍스트 생성
 */
function hp_theme_css() {
    $vars  = hp_theme_vars();
    $lines = [];
    foreach ($vars as $k => $v) {
        // 값에서 위험한 문자 제거
        $v = preg_replace('/[<>"\'\\\\;{}]/', '', (string)$v);
        $lines[] = "  {$k}: {$v};";
    }

    // 사용자 폰트 (있으면)
    $font_display = hp_config('font_display_family', '');
    if ($font_display) {
        $font_display = preg_replace('/[<>"\\\\;{}]/', '', $font_display);
        $lines[] = "  --font-display: {$font_display};";
    }

    return ":root {\n" . implode("\n", $lines) . "\n}";
}

/**
 * 사용자가 admin 에서 등록한 폰트 @import URL 의 <link> 태그 출력
 */
function hp_font_link() {
    $url = hp_config('font_import_url', '');
    $url = sanitize_css_url($url);
    if (!$url) return '';
    return '<link rel="stylesheet" href="' . h($url) . '">';
}

/**
 * 사용자 커스텀 CSS 파일 (data/custom.css) 의 <link> 태그
 */
function hp_custom_css_link() {
    $path = HP_PATH . '/data/custom.css';
    if (!file_exists($path)) return '';
    // 캐시 무력화용 mtime
    return '<link rel="stylesheet" href="' . HP_BASE . '/data/custom.css?v=' . filemtime($path) . '">';
}

/**
 * 디자인 탭의 배경 이미지 + 마우스 커서 → <head> 출력용
 *
 * 반환값: <style> 와 <link> 태그들이 합쳐진 HTML 문자열.
 * index.php 의 <head> 안에서 이 결과를 echo 하면 됨.
 */
function hp_extras_html() {
    $out = '';

    // ─── 배경 이미지 ───
    $bg_image = hp_config('background_image', '');
    if ($bg_image) {
        $src = preg_match('#^https?://#i', $bg_image)
            ? $bg_image
            : HP_BASE . '/data/uploads/' . $bg_image;
        $size       = hp_config('background_size',       'cover');
        $attachment = hp_config('background_attachment', 'fixed');
        $repeat     = hp_config('background_repeat',     'no-repeat');
        $position   = hp_config('background_position',   'center center');
        $overlay    = (int)hp_config('background_overlay', '0');
        $blur       = (int)hp_config('background_blur',    '0');

        // 안전성: 화이트리스트
        $size       = in_array($size,       ['cover','contain','auto'],                    true) ? $size       : 'cover';
        $attachment = in_array($attachment, ['fixed','scroll'],                            true) ? $attachment : 'fixed';
        $repeat     = in_array($repeat,     ['no-repeat','repeat','repeat-x','repeat-y'],  true) ? $repeat     : 'no-repeat';
        $position   = preg_match('/^[a-z ]+$/i', $position) ? $position : 'center center';
        $overlay    = max(0, min(80, $overlay));
        $blur       = max(0, min(40, $blur));

        // 블러 사용 시 가장자리에 blur 의 부드러운 끝이 보이지 않도록 inset 확장
        $inset = $blur > 0 ? -($blur * 3) : 0;
        $src_h = h($src);

        // html 에 fallback 색, html::before 가 배경, html::after 가 오버레이
        // body 와 layout/sidebar/content 는 코어 CSS 에서 글래스(반투명) 처리됨
        $css  = "html { background: var(--bg); }\n";
        $css .= "body { background: transparent; }\n";
        $css .= ".layout { background: transparent; }\n";
        $css .= "html::before { content: ''; position: fixed; inset: {$inset}px; background: url('$src_h') $position / $size $repeat; pointer-events: none; z-index: -2;";
        if ($blur > 0)               $css .= " filter: blur({$blur}px);";
        $css .= " }\n";

        if ($overlay > 0) {
            $alpha = $overlay / 100;
            $css .= "html::after { content: ''; position: fixed; inset: 0; background: rgba(0,0,0,$alpha); pointer-events: none; z-index: -1; }\n";
        }

        // 패널 글래스(backdrop-filter)는 core.css 에서 항상 켜져 있어, 배경 블러 슬라이더를
        // 0 으로 둬도 본문 패널 너머의 배경이 흐려 보인다. 사용자가 블러를 명시하지 않았을 때
        // (0px) 는 패널 backdrop-blur 를 끄고 saturate 만 남겨 "선명" 의도를 존중한다.
        if ($blur === 0) {
            $css .= ".content, .sidebar { backdrop-filter: saturate(180%); -webkit-backdrop-filter: saturate(180%); }\n";
        }

        $out .= "<style id=\"hp-bg\">\n$css</style>\n";
    }

    // ─── 마우스 커서 (정적) ───
    $cursor_static = hp_config('cursor_static_url', '');
    if ($cursor_static && preg_match('#^https?://#i', $cursor_static)) {
        $url = h($cursor_static);
        $out .= "<style id=\"hp-cursor\">* { cursor: url('$url'), auto !important; } a, button, input[type=submit] { cursor: url('$url'), pointer !important; }</style>\n";
    }

    // ─── 마우스 커서 (애니메이션 - link stylesheet) ───
    $cursor_animated = hp_config('cursor_animated_url', '');
    if ($cursor_animated && preg_match('#^https?://#i', $cursor_animated)) {
        $out .= '<link rel="stylesheet" href="' . h($cursor_animated) . '" id="hp-cursor-anim">' . "\n";
    }

    return $out;
}
