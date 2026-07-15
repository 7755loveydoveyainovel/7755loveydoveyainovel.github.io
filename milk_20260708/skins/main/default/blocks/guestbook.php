<?php
/**
 * blocks/guestbook.php — 방명록 미리보기
 *
 * 옵션:
 *   - limit          (int, default 4)
 *   - show_more_link (bool, default true) — "더보기 →" 노출 여부
 *   - title          (string, default '방명록')
 */

$opts = $opts ?? [];
$limit     = max(1, min(20, (int)($opts['limit'] ?? 4)));
// 'guestbook' 라우트는 아직 미구현이므로 기본값 false.
// 향후 pages/guestbook.php 추가 시 admin 에서 옵션으로 켤 수 있음.
$show_more = $opts['show_more_link'] ?? false;
$title     = $opts['title'] ?? '방명록';

$entries = hp_get_recent_guestbook($limit);
?>
<div class="home-card guestbook">
  <?php if (is_admin()): ?>
    <span class="drag-handle">drag_indicator</span>
  <?php endif; ?>

  <div class="home-block-label">
    <?= h($title) ?>
    <?php if ($show_more): ?>
      <a href="<?= hp_url('guestbook') ?>" class="more">더보기 →</a>
    <?php endif; ?>
  </div>

  <?php if (!$entries): ?>
    <div class="empty-msg">아직 방명록이 비어있어요. 첫 번째 글을 남겨보세요!</div>
  <?php else: ?>
    <div class="gb-list">
      <?php foreach ($entries as $e): ?>
        <div class="gb-entry">
          <div class="head">
            <span class="who"><?= h($e['gb_name']) ?></span>
            <span class="when"><?= h(date('Y.m.d', strtotime($e['gb_created_at']))) ?></span>
          </div>
          <div class="body"><?= nl2br(h($e['gb_content'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
