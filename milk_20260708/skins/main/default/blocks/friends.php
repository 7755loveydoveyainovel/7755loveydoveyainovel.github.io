<?php
/**
 * blocks/friends.php — 지인 배너
 *
 * 옵션:
 *   - limit (int, default 50)
 *   - title (string, default 'Links')
 */

$opts = $opts ?? [];
$limit = max(1, min(100, (int)($opts['limit'] ?? 50)));
$title = $opts['title'] ?? 'Links';

$banners = hp_get_friend_banners($limit);
?>
<div class="home-card friends">
  <?php if (is_admin()): ?>
    <span class="drag-handle">drag_indicator</span>
  <?php endif; ?>

  <div class="home-block-label"><?= h($title) ?></div>

  <?php if (!$banners): ?>
    <div class="empty-msg">아직 등록된 지인 배너가 없어요.</div>
  <?php else: ?>
    <div class="friends-grid">
      <?php foreach ($banners as $bn): ?>
        <a class="friend-banner"
           href="<?= h($bn['bn_url'] ?? '#') ?>"
           target="_blank"
           rel="noopener"
           title="<?= h($bn['bn_title'] ?? '') ?>">
          <img src="<?= h(hp_banner_src($bn['bn_image'])) ?>"
               alt="<?= h($bn['bn_title'] ?? '') ?>">
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
