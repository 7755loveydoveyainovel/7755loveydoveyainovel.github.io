<?php
/**
 * mobile/inc/topbar.php — 모바일 상단 바
 *
 * - 좌측: 사이트명 + 한 줄 설명
 * - 데스크탑에서는 CSS 미디어쿼리로 숨겨짐
 */
?>
<?php if (hp_config('show_site_name', '1') !== '0'): ?>
<header class="m-topbar">
  <a href="<?= HP_BASE ?>/" class="m-brand">
    <span class="name"><?= h(hp_config('site_name', 'My Page')) ?></span>
  </a>
</header>
<?php endif; ?>
