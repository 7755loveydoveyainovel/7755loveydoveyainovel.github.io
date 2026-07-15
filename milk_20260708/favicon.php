<?php
/**
 * favicon.php — 파비콘 미업로드 시 자동 생성 SVG
 *
 * 사이트 이름의 첫 글자 + 테마 그라데이션 (--accent → --accent-2).
 * own-banner-auto 와 동일한 디자인 언어.
 */
require_once __DIR__ . '/config.php';
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$site_name  = hp_config('site_name', 'M');
$theme_vars = hp_theme_vars();
$accent     = $theme_vars['--accent']   ?? '#b8533a';
$accent_2   = $theme_vars['--accent-2'] ?? '#5a544a';

// SVG 안전 escape
$enc = function ($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8'); };
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="<?= $enc($accent) ?>"/>
      <stop offset="1" stop-color="<?= $enc($accent_2) ?>"/>
    </linearGradient>
    <radialGradient id="hl" cx="20" cy="18" r="30" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#ffffff" stop-opacity="0.35"/>
      <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="64" height="64" rx="14" fill="url(#g)"/>
  <rect width="64" height="64" rx="14" fill="url(#hl)"/>
</svg>
