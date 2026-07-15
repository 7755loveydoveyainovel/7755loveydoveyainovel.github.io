<?php
/**
 * pages/home.php — 메인 페이지
 *
 * 현재 선택된 main 스킨의 index.php 를 로드해서 렌더 위임.
 * 스킨이 hp_render_blocks_from(__DIR__) 한 줄로 블록을 찍어냄.
 */

$skin = hp_config('main_skin', 'default');

// 디렉터리 트래버설 방지
if (!preg_match('/^[a-z][a-z0-9_-]*$/', $skin)) {
    $skin = 'default';
}

$skin_dir = HP_PATH . "/skins/main/{$skin}";
if (!file_exists("{$skin_dir}/index.php")) {
    $skin_dir = HP_PATH . '/skins/main/default';
}

include "{$skin_dir}/index.php";
