<?php
/**
 * skins/board/dream/info.php — 드림 스킨 메타
 *
 * 드림/페어 아카이브 + AI 미니홈 (통합판).
 * 기존 흩어져 있던 skins/board/game + 루트 games/ 를 이 스킨으로 흡수.
 *
 * 데이터 모델 (milk 표준):
 *   {post}                드림 본체 — po_content 에 JSON(페어/캐릭터 A·B/일상)
 *   {dream_post}          드림 하위 게시글 매칭 (캐릭터 character_post 준용)
 *   _dreamlog 게시판      하위 게시글 저장소 (lazy 생성)
 *   {dream_persona/...}   AI 페르소나·로그·설정 (Pro)
 *
 * tier: _ai/ 폴더 유무로 pro/free 자동 감지. config.php 에서 DREAM_PRO 강제 가능.
 */

if (!defined('DREAM_TIER')) {
    if (defined('DREAM_PRO')) {
        define('DREAM_TIER', DREAM_PRO ? 'pro' : 'free');
    } else {
        define('DREAM_TIER', is_file(__DIR__ . '/_ai/persona.php') ? 'pro' : 'free');
    }
}

return [
    'name'    => '드림',
    'desc'    => '드림/페어 아카이브. 페어 카드 갤러리 + 캐릭터 A·B 프로필 미니홈 + (Pro) AI·게임.',
    'version' => '1.0',
    'author'  => 'milk',
];
