<?php
/**
 * manifest.php — PWA 매니페스트 (동적)
 *
 * 모바일 브라우저에서 "홈 화면에 추가" 시 사용. 사이트 이름·테마 색상·파비콘을
 * 자동으로 끌어와서 매니페스트 JSON 으로 출력. 정적 manifest.json 보다 유연.
 *
 * <link rel="manifest" href="manifest.php"> 로 head 에 등록.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');

$site_name = hp_config('site_name', 'My Page');
$favicon   = hp_config('favicon', '');

$theme_vars = hp_theme_vars();
$accent = $theme_vars['--accent'] ?? '#b8533a';
$bg     = $theme_vars['--bg']     ?? '#f5f1e8';

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . HP_BASE;

// 아이콘 URL — 파비콘이 절대 URL 이면 그대로, 상대면 base 에 붙임. 없으면 동적 SVG fallback
$icons = [];
if ($favicon) {
    $icon_url = preg_match('#^https?://#i', $favicon)
        ? $favicon
        : ($base . '/' . ltrim($favicon, '/'));
    $icons[] = [
        'src'     => $icon_url,
        'sizes'   => 'any',
        'type'    => 'image/png',
        'purpose' => 'any maskable',
    ];
} else {
    // 자동 생성 SVG 파비콘 — 사이트 이름 첫 글자 + 테마 그라데이션
    $icons[] = [
        'src'     => $base . '/favicon.php',
        'sizes'   => 'any',
        'type'    => 'image/svg+xml',
        'purpose' => 'any maskable',
    ];
}

$manifest = [
    'name'             => $site_name,
    'short_name'       => mb_substr($site_name, 0, 12),
    'description'      => $site_name,
    'start_url'        => $base . '/',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'background_color' => $bg,
    'theme_color'      => $accent,
    'orientation'      => 'portrait-primary',
    'lang'             => 'ko',
];
if ($icons) $manifest['icons'] = $icons;

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
