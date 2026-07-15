<?php
/**
 * admin/index.php — 관리자 페이지 라우터 + 레이아웃
 *
 * URL: /admin/?section=site|design|menu|mainpage|banner|profile
 *
 * 구조:
 *  1. require_admin
 *  2. 섹션 파일을 ob_start 안에서 include
 *     - POST 면 섹션 파일이 핸들러 실행 후 header()+exit (헤더 정상 작동)
 *     - GET 면 섹션 파일이 HTML 을 출력하는데, 이를 버퍼에 캡처
 *  3. 캡처한 HTML 을 admin 레이아웃 안 <main> 에 끼워넣어 출력
 */

require_once __DIR__ . '/../config.php';
require_admin();

// ─── 스키마 자동 업그레이드 (어드민 진입 시 1회 체크, 빠름) ───

$section  = $_GET['section'] ?? 'site';
$allowed  = ['site', 'design', 'menu', 'mainpage', 'banner', 'profile'];
if (!in_array($section, $allowed, true)) {
    $section = 'site';
}

$section_file = __DIR__ . "/sections/{$section}.php";

// flash 메시지 (POST 후 redirect 시 사용)
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

// ─── 섹션 파일 실행 (POST 핸들러 + GET 렌더링 캡처) ───
ob_start();
include $section_file;
$section_html = ob_get_clean();
// POST 였다면 위 include 안에서 header()+exit 가 호출돼 여기에 도달하지 못함
// GET 이었다면 $section_html 에 섹션의 렌더 HTML 이 담겨 있음

$site_name = hp_config('site_name', 'My Page');
$theme_css = hp_theme_css();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<title>관리자 — <?= h($site_name) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght@20,400">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= HP_BASE ?>/css/core.css">
<link rel="stylesheet" href="<?= HP_BASE ?>/admin/style.css">
<style><?= $theme_css ?></style>
</head>
<body class="admin-body">

<header class="admin-header">
  <a href="<?= HP_BASE ?>/" class="back">← 사이트로</a>
  <div class="title">관리자 페이지</div>
  <form method="post" action="<?= HP_BASE ?>/" style="margin:0">
    <?= csrf_input() ?>
    <input type="hidden" name="admin_logout" value="1">
    <button type="submit" class="logout">
      <span class="ic"><i class="fas fa-right-from-bracket"></i></span>
      로그아웃
    </button>
  </form>
</header>

<nav class="admin-nav">
  <a href="?section=site"     class="<?= $section==='site'    ?'active':'' ?>"><span class="ic">info</span>사이트 정보</a>
  <a href="?section=mainpage" class="<?= $section==='mainpage'?'active':'' ?>"><span class="ic">view_quilt</span>메인 페이지</a>
  <a href="?section=profile"  class="<?= $section==='profile' ?'active':'' ?>"><span class="ic">person</span>프로필 & 링크</a>
  <a href="?section=design"   class="<?= $section==='design'  ?'active':'' ?>"><span class="ic">palette</span>디자인</a>
  <a href="?section=menu"     class="<?= $section==='menu'    ?'active':'' ?>"><span class="ic">menu</span>메뉴/게시판</a>
  <a href="?section=banner"   class="<?= $section==='banner'  ?'active':'' ?>"><span class="ic">image</span>배너</a>
</nav>

<main class="admin-main">
  <?php if ($flash): ?>
    <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <?= $section_html ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="<?= HP_BASE ?>/admin/script.js"></script>

</body>
</html>
