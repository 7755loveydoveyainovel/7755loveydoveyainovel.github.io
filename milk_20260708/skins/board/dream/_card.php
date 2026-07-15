<?php
/**
 * skins/board/dream/_card.php — 페어 카드 (갤러리용)
 *
 * 변수: $p (post row), $board
 * 헤더 아이콘 없이 페어명 + 캐릭터명만 (사용자 요청).
 * 페어 대표색(--dr-color)을 카드 액센트로 사용.
 */
$d        = dr_data($p);
$thumb    = dr_card_thumb($p);
$thumb_src = $thumb ? dr_img_src($thumb, $board) : '';
$color    = $d['color'] ?: '';
$a_name   = $d['char_a']['name'] ?? '';
$b_name   = $d['char_b']['name'] ?? '';
$private  = !empty($p['po_is_private']);
?>
<a class="dr-card<?= $color ? ' has-color' : '' ?>"
   href="<?= hp_url('view', ['po_id' => $p['po_id']]) ?>"
   <?= $color ? 'style="--dr-color:'.h($color).'"' : '' ?>>
  <div class="dr-card-header">
    <?php if ($private && !is_admin()): ?>
      <span class="dr-lock"><?= function_exists('hp_icon') ? hp_icon('fas fa-lock') : '🔒' ?></span>
    <?php elseif ($thumb_src): ?>
      <img class="dr-card-thumb" src="<?= h($thumb_src) ?>" alt="<?= h($p['po_title']) ?>" loading="lazy">
    <?php endif; ?>
    <?php if (!empty($p['po_category'])): ?>
      <span class="dr-card-badge"><?= h($p['po_category']) ?></span>
    <?php endif; ?>
    <?php if ($private): ?><span class="dr-badge-secret">S</span><?php endif; ?>
  </div>
  <div class="dr-card-info">
    <div class="dr-card-title"><?= h($p['po_title']) ?></div>
    <?php if ($a_name || $b_name): ?>
      <div class="dr-card-names"><?= h($a_name ?: '?') ?> × <?= h($b_name ?: '?') ?></div>
    <?php endif; ?>
  </div>
</a>
