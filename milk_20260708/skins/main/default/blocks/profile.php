<?php
/**
 * blocks/profile.php — 프로필 카드
 *
 * 출력: 아바타 + 인사 + 자기소개 + 본인 배너 + 소셜 링크
 * 옵션 (mb_options):
 *   - show_banner (bool, default true)
 *   - show_social (bool, default true)
 *   - show_note   (bool, default true)
 */

$opts = $opts ?? [];
$show_banner = $opts['show_banner'] ?? true;
$show_social = $opts['show_social'] ?? true;
$show_note   = $opts['show_note']   ?? true;

$site_name = hp_config('site_name', '나만의 홈페이지');
$greet     = hp_config('greeting', '');
$intro     = hp_config('intro', '');
$avatar    = hp_config('avatar_image', '');
$banner    = hp_get_self_banner();
$links     = hp_get_links();
?>
<div class="profile-card">
  <?php if (is_admin()): ?>
    <span class="drag-handle" title="드래그하여 순서 변경">drag_indicator</span>
  <?php endif; ?>

  <div class="avatar">
    <?php if ($avatar): ?>
      <img src="<?= h($avatar) ?>" alt="<?= h($site_name) ?>">
    <?php else: ?>
      <span class="ic">favorite</span>
    <?php endif; ?>
  </div>

  <?php if ($greet): ?>
    <div class="greet"><?= h($greet) ?></div>
  <?php endif; ?>

  <?php if ($intro): ?>
    <div class="intro"><?= nl2br(h($intro)) ?></div>
  <?php endif; ?>

  <?php
  // 배너 클릭 시 복사할 URL — 이미지가 있으면 이미지 URL, 없으면 사이트 주소
  $_site_url = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . HP_BASE, '/');
  $_banner_copy_url = $_site_url;
  if ($show_banner && $banner) {
      $bn_src = hp_banner_src($banner['bn_image']);
      // 절대 URL 로 변환 (상대면 site URL 앞에 붙이기)
      if (preg_match('#^https?://#i', $bn_src)) {
          $_banner_copy_url = $bn_src;
      } else {
          $_banner_copy_url = $_site_url . '/' . ltrim($bn_src, '/');
      }
  }
  ?>

  <?php if ($show_banner): ?>
    <?php if ($banner): ?>
      <a class="own-banner"
         href="#"
         data-url="<?= h($_banner_copy_url) ?>"
         title="클릭하면 배너 이미지 주소가 복사돼요">
        <img src="<?= h(hp_banner_src($banner['bn_image'])) ?>"
             alt="<?= h($banner['bn_title'] ?? $site_name) ?>">
      </a>
    <?php else: ?>
      <a class="own-banner own-banner-auto"
         href="#"
         data-url="<?= h($_site_url) ?>"
         title="클릭하면 사이트 주소가 복사돼요"></a>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($show_social && $links): ?>
    <div class="social-row">
      <?php foreach ($links as $lk): ?>
        <?php if (stripos($lk['lk_url'], 'mailto:') === 0): ?>
          <?php $_mail = preg_replace('/^mailto:/i', '', $lk['lk_url']); ?>
          <a href="#" class="social-mail" data-mail="<?= h($_mail) ?>"
             title="클릭하면 메일 주소가 복사돼요 (<?= h($_mail) ?>)">
            <span class="ic"><?= hp_icon($lk['lk_icon']) ?></span>
          </a>
        <?php else: ?>
          <a href="<?= h($lk['lk_url']) ?>" target="_blank" rel="noopener" title="<?= h($lk['lk_label']) ?>">
            <span class="ic"><?= hp_icon($lk['lk_icon']) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($show_note && $show_banner): ?>
    <div class="note">
      <?php if ($banner): ?>
        배너는 클릭 시 이미지 주소가 복사됩니다.
      <?php else: ?>
        배너는 클릭 시 사이트 주소가 복사됩니다.
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
