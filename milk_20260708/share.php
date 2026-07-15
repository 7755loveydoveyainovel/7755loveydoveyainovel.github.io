<?php
/**
 * share.php — 게시글 공유 페이지 (OpenGraph 미리보기)
 *
 * 트위터/디스코드/카카오톡 등에 URL 을 붙여넣을 때 미리보기 카드가 뜨도록
 * og:* 메타 태그를 출력. 사람이 직접 열어도 보기 좋은 카드 형태로 렌더링.
 *
 * URL: share.php?po_id=N
 *
 * 이미지 fallback 체인:
 *   1. 첫 게시글 이미지 (po_thumbnail / po_extra.images)
 *   2. 사이트 배경 이미지 (background_image)
 *   3. 파비콘
 *   4. 없음
 */

require_once __DIR__ . '/config.php';

$po_id = (int)($_GET['po_id'] ?? 0);
if (!$po_id) {
    http_response_code(404);
    echo '잘못된 접근입니다.';
    exit;
}

$post = qOne("SELECT * FROM {post} WHERE po_id = ?", [$po_id]);
if (!$post) {
    http_response_code(404);
    echo '존재하지 않는 글입니다.';
    exit;
}

$board = hp_board_by_id($post['po_bo_id']);

// 로그 게시판 (_charlog, _pairlog) — admin_only 지만 share 는 허용 (부모 캐릭터/페어와 함께 공개)
$is_log_board = $board && in_array($board['bo_slug'] ?? '', ['_charlog', '_pairlog'], true);
$parent_post  = null;   // 로그면 부모 캐릭터/페어 post
$parent_board = null;
if ($is_log_board) {
    $parent_id = qVal("SELECT cp_char_po_id FROM {character_post} WHERE cp_po_id = ?", [$po_id]);
    if ($parent_id) {
        $parent_post = qOne("SELECT * FROM {post} WHERE po_id = ?", [(int)$parent_id]);
        if ($parent_post) {
            $parent_board = hp_board_by_id($parent_post['po_bo_id']);
        }
    }
}

// ─── 권한 체크 ───
// 일반 게시판: admin_only / 비공개 글이면 share 불가
// 로그 게시판: 부모 캐릭터/페어가 비공개면 share 불가 (admin_only 자체는 무시)
if (!$board) {
    http_response_code(404); echo '비공개 글입니다.'; exit;
}
if (!$is_log_board && !empty($board['bo_admin_only'])) {
    http_response_code(404); echo '비공개 글입니다.'; exit;
}
// 비밀번호 보호 게시판은 공유 불가
if (!$is_log_board && hp_board_is_protected($board)) {
    http_response_code(404); echo '비공개 글입니다.'; exit;
}
if (!empty($post['po_is_private'])) {
    http_response_code(404); echo '비공개 글입니다.'; exit;
}
if ($is_log_board && (!$parent_post || !empty($parent_post['po_is_private']))) {
    http_response_code(404); echo '비공개 글입니다.'; exit;
}

// ─── URL 구성 ───
$site_name = hp_config('site_name', 'My Page');
$favicon   = hp_config('favicon', '');
$scheme    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$site_url  = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$base_url  = $site_url . HP_BASE;
$share_url = $base_url . '/share.php?po_id=' . $po_id;
// 로그면 "사이트에서 보기" 링크는 부모 캐릭터/페어 view + #log-N (로그 위치로 자동 스크롤)
$view_url  = $is_log_board && $parent_post
    ? $base_url . '/?p=view&po_id=' . (int)$parent_post['po_id'] . '#log-' . $po_id
    : $base_url . '/?p=view&po_id=' . $po_id;

// ─── OG 이미지 결정 (fallback chain) ───
$post_images = hp_post_images($post);
$og_image = '';

if ($post_images) {
    // 1. 게시글 이미지 — 로그면 _charlog/_pairlog 폴더, 일반 글이면 data/posts/
    $first = $post_images[0];
    if (preg_match('#^https?://#i', $first)) {
        $og_image = $first;
    } else {
        // hp_post_image_url 은 슬러그 폴더 우선 / 옛 위치 폴백 처리
        $rel = hp_post_image_url($first, $board);
        // 절대 URL 로 변환 (HP_BASE 가 이미 포함되어 있음)
        $og_image = $site_url . $rel;
    }
} else {
    // 2. 사이트 배경 이미지
    $bg = hp_config('background_image', '');
    if ($bg) {
        $og_image = preg_match('#^https?://#i', $bg)
            ? $bg
            : ($base_url . '/data/uploads/' . $bg);
    } elseif ($favicon) {
        // 3. 파비콘
        $og_image = preg_match('#^https?://#i', $favicon)
            ? $favicon
            : (strpos($favicon, '/') === 0 ? ($site_url . $favicon) : ($base_url . '/' . $favicon));
    }
}

// ─── 캐릭터 / 페어 스킨 감지 (전용 카드 표시) ───
$skin         = $board['bo_skin'] ?? '';
$is_character = ($skin === 'character');
$is_pair      = ($skin === 'pair');
$is_card      = $is_character || $is_pair;

$card_data = null;
if ($is_card) {
    $card_data = json_decode($post['po_content'] ?? '', true);
    if (!is_array($card_data)) $card_data = [];
}
// 하위 호환을 위한 alias
$char_data = $card_data;

// ─── OG 텍스트 ───
$og_title = $post['po_title'] !== '' ? $post['po_title'] : '(제목 없음)';
if ($is_card) {
    // 캐릭터/페어: 한마디(po_subtitle) 우선, 없으면 영문명 또는 사이트명
    $en_name = trim($card_data['name_en'] ?? '');
    $og_desc = trim($post['po_subtitle'] ?? '') !== ''
        ? trim($post['po_subtitle'])
        : ($en_name !== '' ? $en_name : $site_name);
} else {
    // 일반 글: 마크다운 텍스트 plain 추출
    $og_desc_raw = hp_render_markdown($post['po_content'] ?? '');
    $og_desc     = trim(preg_replace('/\s+/', ' ', strip_tags($og_desc_raw)));
    $og_desc     = $og_desc !== '' ? mb_strimwidth($og_desc, 0, 150, '...') : ($post['po_subtitle'] ?? $site_name);
}

// 본문 — 마크다운 렌더링한 HTML 그대로 사용
$content_html = $post['po_content'] !== '' ? hp_render_markdown($post['po_content']) : '';

// ─── 테마 색상 ───
$theme_vars = hp_theme_vars();
$_v = function ($k, $fallback) use ($theme_vars) {
    return $theme_vars[$k] ?? $fallback;
};
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($og_title) ?> | <?= h($site_name) ?></title>
<?php if ($favicon): ?><link rel="icon" href="<?= h($favicon) ?>"><?php endif; ?>

<!-- Open Graph -->
<meta property="og:type" content="article">
<meta property="og:title" content="<?= h($og_title) ?>">
<meta property="og:description" content="<?= h($og_desc) ?>">
<?php if ($og_image): ?>
<meta property="og:image" content="<?= h($og_image) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<?php endif; ?>
<meta property="og:url" content="<?= h($share_url) ?>">
<meta property="og:site_name" content="<?= h($site_name) ?>">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($og_title) ?>">
<meta name="twitter:description" content="<?= h($og_desc) ?>">
<?php if ($og_image): ?><meta name="twitter:image" content="<?= h($og_image) ?>"><?php endif; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.min.css">
<style>
:root {
  --bg:       <?= h($_v('--bg',       '#f5f1e8')) ?>;
  --paper:    <?= h($_v('--paper',    '#fffdf6')) ?>;
  --paper-2:  <?= h($_v('--paper-2',  '#faf5e8')) ?>;
  --ink:      <?= h($_v('--ink',      '#2a2520')) ?>;
  --ink-soft: <?= h($_v('--ink-soft', '#5a544a')) ?>;
  --ink-mute: <?= h($_v('--ink-mute', '#948c7e')) ?>;
  --hair:     <?= h($_v('--hair',     '#e6dfce')) ?>;
  --accent:   <?= h($_v('--accent',   '#b8533a')) ?>;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: Pretendard, -apple-system, sans-serif;
  background: var(--bg);
  min-height: 100vh;
  color: var(--ink);
  padding: 40px 20px;
  line-height: 1.6;
}
.share-wrap { max-width: 640px; margin: 0 auto; }
.sh-card {
  background: var(--paper);
  border: 1px solid var(--hair);
  border-radius: 16px;
  overflow: hidden;
}
.sh-images { display: flex; flex-direction: column; }
.sh-images img {
  width: 100%;
  max-height: 480px;
  object-fit: cover;
  display: block;
}
.sh-images.multi img { object-fit: contain; max-height: none; }
.sh-header { padding: 28px 32px 16px; }
.sh-board {
  display: inline-block;
  font-size: 11px;
  font-weight: 700;
  color: var(--accent);
  background: color-mix(in srgb, var(--accent) 14%, var(--paper));
  padding: 4px 12px;
  border-radius: 999px;
  margin-bottom: 14px;
  letter-spacing: .02em;
}
.sh-title {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
  line-height: 1.4;
}
.sh-subtitle {
  font-size: 14px;
  color: var(--ink-soft);
  margin-top: 6px;
  font-weight: 500;
}
.sh-meta {
  font-size: 11px;
  color: var(--ink-mute);
  margin-top: 14px;
  font-weight: 500;
}
.sh-body {
  padding: 4px 32px 28px;
  font-size: 14px;
  line-height: 1.85;
  color: var(--ink-soft);
  word-break: break-word;
}
/* 마크다운 결과물 — 핵심 스타일만 (core.css 의 .markdown-body 와 동일 톤) */
.sh-body p { margin-bottom: 1em; }
.sh-body > :first-child { margin-top: 0; }
.sh-body > :last-child  { margin-bottom: 0; }
.sh-body h1, .sh-body h2, .sh-body h3, .sh-body h4, .sh-body h5, .sh-body h6 {
  color: var(--ink);
  font-weight: 800;
  line-height: 1.4;
  margin: 1.4em 0 .5em;
  letter-spacing: -.02em;
}
.sh-body h1 { font-size: 20px; }
.sh-body h2 { font-size: 17px; }
.sh-body h3 { font-size: 15px; }
.sh-body strong { color: var(--ink); font-weight: 800; }
.sh-body em { font-style: italic; }
.sh-body del { color: var(--ink-mute); }
.sh-body a {
  color: var(--accent);
  border-bottom: 1px solid color-mix(in srgb, var(--accent) 35%, transparent);
  text-decoration: none;
}
.sh-body blockquote {
  border-left: 3px solid var(--accent);
  padding: 4px 0 4px 14px;
  margin: 1em 0;
  color: var(--ink-soft);
  background: color-mix(in srgb, var(--accent) 5%, transparent);
  border-radius: 0 6px 6px 0;
}
.sh-body code {
  background: var(--paper-2);
  border: 1px solid var(--hair);
  padding: 1px 6px;
  border-radius: 4px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: .9em;
  color: var(--accent);
}
.sh-body pre {
  background: var(--paper-2);
  border: 1px solid var(--hair);
  padding: 12px 16px;
  border-radius: 8px;
  overflow-x: auto;
  margin: 1em 0;
  line-height: 1.65;
}
.sh-body pre code { background: none; border: none; padding: 0; color: var(--ink); }
.sh-body ul, .sh-body ol { margin: .6em 0 1em; padding-left: 1.6em; }
.sh-body li { margin-bottom: .3em; }
.sh-body hr { border: none; border-top: 1px solid var(--hair); margin: 1.6em 0; }
.sh-body img { max-width: 100%; height: auto; border-radius: 6px; margin: .6em 0; }
.sh-body ruby rt { font-size: .55em; color: var(--ink-mute); }
.sh-body .md-blur {
  background: var(--ink);
  color: transparent;
  border-radius: 3px;
  padding: 0 4px;
  cursor: pointer;
  user-select: none;
  -webkit-user-select: none;
}
.sh-body .md-blur:hover { background: transparent; color: var(--ink); user-select: text; -webkit-user-select: text; }
.sh-body .md-fold {
  margin: 1em 0;
  border: 1px solid var(--hair);
  border-radius: 8px;
  background: var(--paper-2);
}
.sh-body .md-fold > summary {
  padding: 10px 16px;
  cursor: pointer;
  font-weight: 700;
  color: var(--ink);
  background: var(--paper);
  list-style: none;
}
.sh-body .md-fold > summary::-webkit-details-marker { display: none; }
.sh-body .md-fold-body { padding: 14px 18px; color: var(--ink-soft); line-height: 1.85; }
.sh-footer {
  text-align: center;
  margin-top: 24px;
  padding: 16px 0 20px;
}
.sh-visit {
  display: inline-block;
  padding: 12px 36px;
  background: var(--accent);
  color: var(--paper);
  border-radius: 999px;
  text-decoration: none;
  font-weight: 700;
  font-size: 14px;
  transition: transform .15s, box-shadow .15s;
}
.sh-visit:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0,0,0,.15);
}
.sh-site {
  font-size: 11px;
  color: var(--ink-mute);
  margin-top: 14px;
}
@media (max-width: 600px) {
  body { padding: 20px 14px; }
  .sh-header { padding: 22px 22px 14px; }
  .sh-body { padding: 4px 22px 22px; }
  .sh-title { font-size: 20px; }
}

/* 캐릭터 스킨 전용 */
.sh-card-char .sh-images {
  background: var(--paper-2);
}
.sh-card-char .sh-images img {
  max-height: 560px;
  object-fit: contain;
}
.sh-name-en {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: var(--ink-mute);
  margin-top: 4px;
  letter-spacing: .02em;
}
.sh-cat {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  color: var(--accent);
  background: color-mix(in srgb, var(--accent) 10%, var(--paper));
  padding: 2px 8px;
  border-radius: 999px;
  margin-right: 4px;
  letter-spacing: .02em;
}
.sh-meta .dot { margin: 0 6px; opacity: .5; }
</style>
</head>
<body>
<div class="share-wrap">
  <div class="sh-card<?= $is_card ? ' sh-card-char' : '' ?>">
    <?php if ($is_card):
      // 캐릭터/페어: po_thumbnail 단일 이미지 (둘 다 카드 자동 폴백 적용됨)
      $card_img = $post['po_thumbnail'] ?? '';
      $card_img_url = $card_img
        ? (preg_match('#^https?://#i', $card_img)
            ? $card_img
            : ($base_url . '/data/posts/' . $card_img))
        : '';
    ?>
      <?php if ($card_img_url): ?>
        <div class="sh-images">
          <img src="<?= h($card_img_url) ?>" alt="<?= h($og_title) ?>">
        </div>
      <?php endif; ?>
    <?php elseif ($post_images): ?>
      <div class="sh-images <?= count($post_images) > 1 ? 'multi' : '' ?>">
        <?php foreach (array_slice($post_images, 0, 4) as $img): ?>
          <img src="<?= h(hp_post_image_url($img, $board)) ?>" alt="">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="sh-header">
      <span class="sh-board"><?= h($board['bo_name']) ?></span>
      <h1 class="sh-title">
        <?= h($og_title) ?>
        <?php if ($is_character && !empty($card_data['name_en'])): ?>
          <small class="sh-name-en"><?= h($card_data['name_en']) ?></small>
        <?php elseif ($is_pair):
          $a_name = trim($card_data['char_a']['name'] ?? '');
          $b_name = trim($card_data['char_b']['name'] ?? '');
          if ($a_name || $b_name):
        ?>
          <small class="sh-name-en"><?= h($a_name) ?> × <?= h($b_name) ?></small>
        <?php
          endif;
        endif;
        ?>
      </h1>
      <?php if (!empty($post['po_subtitle'])): ?>
        <div class="sh-subtitle"><?= h($is_card ? '"'.$post['po_subtitle'].'"' : $post['po_subtitle']) ?></div>
      <?php endif; ?>
      <div class="sh-meta">
        <?php if ($is_card && !empty($post['po_category'])): ?>
          <span class="sh-cat"><?= h($post['po_category']) ?></span>
          <span class="dot">·</span>
        <?php endif; ?>
        <?= h(date('Y년 m월 d일', strtotime($post['po_created_at']))) ?>
      </div>
    </div>
    <?php if (!$is_card && $content_html !== ''): ?>
      <div class="sh-body markdown-body"<?= $is_log_board ? ' data-lightbox' : '' ?>><?= $content_html ?></div>
    <?php endif; ?>
  </div>
  <div class="sh-footer">
    <a href="<?= h($view_url) ?>" class="sh-visit">
      <?php if ($is_log_board): ?>
        <?= $parent_board && ($parent_board['bo_skin'] ?? '') === 'pair' ? '페어 페이지에서 보기 →' : '캐릭터 페이지에서 보기 →' ?>
      <?php elseif ($is_character): ?>캐릭터 페이지로 →
      <?php elseif ($is_pair): ?>페어 페이지로 →
      <?php else: ?>사이트에서 보기 →
      <?php endif; ?>
    </a>
    <div class="sh-site"><?= h($site_name) ?></div>
  </div>
</div>
<?php if ($is_log_board): ?>
  <!-- 로그 글 본문 이미지 모달 (lightbox) -->
  <style>
    .hp-img-modal {
      position: fixed; inset: 0;
      background: rgba(0, 0, 0, .85);
      display: none; align-items: center; justify-content: center;
      z-index: 9999; padding: 32px 20px; cursor: zoom-out;
      animation: hp-img-modal-fade 160ms ease;
    }
    .hp-img-modal.open { display: flex; }
    .hp-img-modal img {
      max-width: 100%; max-height: 100%; object-fit: contain;
      cursor: default; border-radius: 4px;
      box-shadow: 0 8px 40px rgba(0, 0, 0, .5);
    }
    .hp-img-modal-close {
      position: absolute; top: 14px; right: 18px;
      width: 40px; height: 40px;
      background: rgba(255, 255, 255, .12); border: none; color: #fff;
      font-size: 28px; line-height: 1; border-radius: 50%; cursor: pointer;
      padding: 0; display: flex; align-items: center; justify-content: center;
      transition: background .15s;
    }
    .hp-img-modal-close:hover { background: rgba(255, 255, 255, .25); }
    @keyframes hp-img-modal-fade { from { opacity: 0; } to { opacity: 1; } }
    [data-lightbox] img { cursor: zoom-in; }
  </style>
  <script src="<?= h(HP_BASE) ?>/js/lightbox.js?v=<?= filemtime(HP_PATH . '/js/lightbox.js') ?>"></script>
<?php endif; ?>
</body>
</html>
