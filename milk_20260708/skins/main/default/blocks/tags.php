<?php
/**
 * blocks/tags.php — 사용자 직접 작성 태그 클라우드
 *
 * 옵션:
 *   - tags  (array of {label, size, url})
 *           size: 'sm' | 'md' | 'lg' (default 'md')
 *   - title (string, default '태그')
 */

$opts = $opts ?? [];
$tags  = (!empty($opts['tags']) && is_array($opts['tags'])) ? $opts['tags'] : [];
$title = $opts['title'] ?? '태그';
?>
<div class="home-card tags">
  <?php if (is_admin()): ?>
    <span class="drag-handle">drag_indicator</span>
  <?php endif; ?>

  <div class="home-block-label"><?= h($title) ?></div>

  <?php if (!$tags): ?>
    <div class="empty-msg">관리자 페이지에서 태그를 추가해보세요.</div>
  <?php else: ?>
    <div class="tag-cloud">
      <?php foreach ($tags as $t):
        $label = $t['label'] ?? '';
        if ($label === '') continue;
        $size  = $t['size'] ?? 'md';
        $url   = $t['url']  ?? '#';
        $cls   = ($size === 'lg') ? 'lg' : (($size === 'sm') ? 'sm' : '');
      ?>
        <a class="tag-chip <?= $cls ?>" href="<?= h($url) ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
