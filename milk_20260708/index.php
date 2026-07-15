<?php
/**
 * index.php — 메인 라우터
 *
 * 책임:
 *  - config.php 로드 (DB·세션·라이브러리)
 *  - 관리자 로그인/로그아웃 POST 처리
 *  - 라우트 결정 (home / board / view / write / edit)
 *  - slug → bo_id 변환
 *  - 메뉴 데이터 로드 (사이드바용)
 *  - HTML 셸 + theme CSS + 사이드바 + 본문 출력
 */

require_once __DIR__ . '/config.php';

// ─── 관리자 로그인 ───
if (isset($_POST['admin_login'])) {
    csrf_check();

    // 무차별 대입 방지 — IP 단위 5분 윈도우 5회 제한
    $rl_key = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!hp_ratelimit_check($rl_key, 5, 300)) {
        $login_error      = true;
        $login_error_wait = hp_ratelimit_retry_after($rl_key, 300);
    } elseif (admin_login($_POST['ad_pw'] ?? '')) {
        hp_ratelimit_clear($rl_key);
        // 로그인 성공 → 같은 사이트 안의 referer 면 그쪽으로, 아니면 홈으로
        $back = HP_BASE . '/';
        $ref  = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref) {
            $ref_host  = parse_url($ref, PHP_URL_HOST);
            $self_host = $_SERVER['HTTP_HOST'] ?? '';
            if ($ref_host && $ref_host === $self_host) {
                $back = $ref;
            }
        }
        header('Location: ' . $back);
        exit;
    } else {
        hp_ratelimit_hit($rl_key);
        $login_error = true;
    }
}

// ─── 관리자 로그아웃 (POST + CSRF) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_logout'])) {
    csrf_check();
    admin_logout();
    header('Location: ' . HP_BASE . '/');
    exit;
}

// ─── 라우팅 ───
$page    = $_GET['p'] ?? 'home';
$allowed = ['home', 'board', 'view', 'write', 'edit', 'guestbook'];
if (!in_array($page, $allowed, true)) {
    $page = 'home';
}

// ─── 섹션 쿠키 설정 (?section=N 쿼리 있으면 업데이트) ───
if (isset($_GET['section'])) {
    hp_set_section_cookie((int)$_GET['section']);
}

// ─── slug → bo_id 변환 + 게시판이 속한 섹션 자동 활성화 ───
// board / write: slug 로부터 board 찾음
// view: po_id 로부터 post → board 찾음
// 모두 한 곳에서 처리해서 자동 섹션 전환 로직이 분산되지 않게 함.
if (in_array($page, ['board', 'write'], true) && isset($_GET['slug'])) {
    $b = hp_board_by_slug($_GET['slug']);
    if ($b) {
        $_GET['bo_id'] = $b['bo_id'];
        hp_set_section_from_board($b);
    }
} elseif ($page === 'view' && isset($_GET['po_id'])) {
    $_p = qOne("SELECT po_bo_id FROM {post} WHERE po_id = ?", [(int)$_GET['po_id']]);
    if ($_p) {
        $b = hp_board_by_id($_p['po_bo_id']);
        if ($b) hp_set_section_from_board($b);
    }
}

// ─── 메뉴 데이터 (사이드바에서 사용) ───
$menu = hp_load_menu();

// ─── 페이지 파일 실행 (POST 핸들러 + GET 렌더링 캡처) ───
// admin/index.php 와 같은 패턴.
// POST 면 페이지 파일 안에서 header()+exit 가 호출돼 여기에 도달하지 못함.
// GET 이면 $page_html 에 페이지의 렌더 HTML 이 담긴다.
ob_start();
include HP_PATH . "/pages/{$page}.php";
$page_html = ob_get_clean();

// ─── 사이트 메타 ───
$site_name = hp_config('site_name', 'My Page');
$favicon   = hp_config('favicon', '');
$theme_css  = hp_theme_css();
$theme_vars = hp_theme_vars();

// ─── SPA: ?ajax=1 요청은 layout 없이 fragment 만 응답 ───
$is_ajax = !empty($_GET['ajax']);

if ($is_ajax) {
    // 메타 정보 (JS 가 sidebar active state, title 갱신용으로 사용)
    $slug = $_GET['slug'] ?? '';
    echo '<div data-spa-meta'
       . ' data-title="' . h($site_name) . '"'
       . ' data-page="'  . h($page) . '"'
       . ' data-slug="'  . h($slug) . '"></div>';
    echo $page_html;

    // sidebar / drawer / tabbar 도 함께 보냄 — SPA 가 자동 섹션 전환 결과 반영하도록
    // <template> 안에 넣어서 페이지 본문에 섞이지 않게 함 (innerHTML 으로도 실행 안 됨)
    ob_start();
    include HP_PATH . '/inc/sidebar.php';
    echo '<template data-spa-sidebar>' . ob_get_clean() . '</template>';

    ob_start();
    include HP_PATH . '/mobile/inc/drawer.php';
    echo '<template data-spa-drawer>' . ob_get_clean() . '</template>';

    ob_start();
    include HP_PATH . '/mobile/inc/tabbar.php';
    echo '<template data-spa-tabbar>' . ob_get_clean() . '</template>';

    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(csrf_token()) ?>">
<?php if (hp_config('noindex') === '1'): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<title><?= h($site_name) ?></title>
<?php if ($favicon): ?>
  <link rel="icon" href="<?= h($favicon) ?>">
  <link rel="apple-touch-icon" href="<?= h($favicon) ?>">
<?php else: ?>
  <link rel="icon" type="image/svg+xml" href="<?= HP_BASE ?>/favicon.php">
  <link rel="apple-touch-icon" href="<?= HP_BASE ?>/favicon.php">
<?php endif; ?>

<!-- PWA — 홈 화면에 추가 (온라인 전용) -->
<link rel="manifest" href="<?= HP_BASE ?>/manifest.php">
<meta name="theme-color" content="<?= h($theme_vars['--accent'] ?? '#b8533a') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= h($site_name) ?>">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght@20,400">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<?= hp_font_link() ?>
<link rel="stylesheet" href="<?= HP_BASE ?>/css/core.css?v=<?= filemtime(HP_PATH . '/css/core.css') ?>">
<link rel="stylesheet" href="<?= HP_BASE ?>/mobile/css/mobile.css?v=<?= filemtime(HP_PATH . '/mobile/css/mobile.css') ?>">
<style><?= $theme_css ?></style>
<?= hp_custom_css_link() ?>
<?= hp_extras_html() ?>
<script>
// 서버에서 자동 감지된 사이트 루트 URL — SPA / fetch 가 사용
window.HP_BASE = '<?= h(HP_BASE) ?>';

// CSRF 토큰 자동 첨부 (모든 fetch 호출)
(function(){
  var _f = window.fetch;
  window.fetch = function(u, o) {
    o = o || {};
    var m = document.querySelector('meta[name="csrf-token"]');
    if (m) {
      var t = m.content;
      if (!o.headers) o.headers = {};
      if (o.headers instanceof Headers) {
        if (!o.headers.has('X-CSRF-Token')) o.headers.set('X-CSRF-Token', t);
      } else {
        if (!o.headers['X-CSRF-Token']) o.headers['X-CSRF-Token'] = t;
      }
      if (o.body instanceof FormData && !o.body.has('_csrf')) o.body.append('_csrf', t);
    }
    return _f.call(this, u, o);
  };
})();
</script>
</head>
<body>

<?php include HP_PATH . '/mobile/inc/topbar.php'; ?>

<div class="layout">
  <?php include HP_PATH . '/inc/sidebar.php'; ?>

  <main class="content">
    <div class="content-inner">
      <?= $page_html ?>
    </div>
    <?php include HP_PATH . '/inc/footer.php'; ?>
  </main>
</div>

<?php include HP_PATH . '/inc/bgm-player.php'; ?>

<?php include HP_PATH . '/mobile/inc/tabbar.php'; ?>
<?php include HP_PATH . '/mobile/inc/drawer.php'; ?>

<script src="<?= HP_BASE ?>/mobile/js/mobile.js?v=<?= filemtime(HP_PATH . '/mobile/js/mobile.js') ?>"></script>
<script>
// 커스텀 툴팁 — [title] → [data-tip] 변환 + body 에 떠 있는 .hp-tooltip element
(function () {
  var tipEl = null;

  function getTipEl() {
    if (!tipEl) {
      tipEl = document.createElement('div');
      tipEl.className = 'hp-tooltip';
      document.body.appendChild(tipEl);
    }
    return tipEl;
  }

  function showTip(e) {
    var el = e.currentTarget;
    var text = el.getAttribute('data-tip');
    if (!text) return;

    var tip = getTipEl();
    tip.textContent = text;
    tip.setAttribute('data-wrap', text.length > 30 ? '1' : '0');

    // 위치 계산 — 일단 보이지 않게 놓고 size 측정
    tip.style.left = '0px';
    tip.style.top  = '0px';
    tip.classList.remove('show');

    requestAnimationFrame(function () {
      var rect    = el.getBoundingClientRect();
      var tipRect = tip.getBoundingClientRect();
      var top  = rect.top - tipRect.height - 8;
      var left = rect.left + (rect.width - tipRect.width) / 2;

      // 위에 자리 없으면 아래로
      if (top < 4) top = rect.bottom + 8;
      // 좌우 viewport 안으로
      if (left < 4) left = 4;
      if (left + tipRect.width > window.innerWidth - 4) {
        left = window.innerWidth - tipRect.width - 4;
      }

      tip.style.top  = top + 'px';
      tip.style.left = left + 'px';
      tip.classList.add('show');
    });
  }

  function hideTip() {
    if (tipEl) tipEl.classList.remove('show');
  }

  // SPA / scroll / resize 시 즉시 숨김
  window.addEventListener('scroll', hideTip, true);
  window.addEventListener('resize', hideTip);

  window.hpInitTooltips = function (root) {
    root = root || document;
    root.querySelectorAll('[title]').forEach(function (el) {
      var t = el.getAttribute('title');
      if (!t) return;
      el.setAttribute('data-tip', t);
      el.removeAttribute('title');
    });
    root.querySelectorAll('[data-tip]').forEach(function (el) {
      if (el._tipBound) return;
      el._tipBound = true;
      el.addEventListener('mouseenter', showTip);
      el.addEventListener('mouseleave', hideTip);
      el.addEventListener('focus', showTip);
      el.addEventListener('blur', hideTip);
    });
  };

  hpInitTooltips();
})();
</script>
<script src="<?= HP_BASE ?>/js/lightbox.js?v=<?= filemtime(HP_PATH . '/js/lightbox.js') ?>"></script>
<script src="<?= HP_BASE ?>/js/spa.js?v=<?= filemtime(HP_PATH . '/js/spa.js') ?>"></script>

</body>
</html>
