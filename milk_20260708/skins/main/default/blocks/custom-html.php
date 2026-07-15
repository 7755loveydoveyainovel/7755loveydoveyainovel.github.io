<?php
/**
 * blocks/custom-html.php — 자유 HTML 블록
 *
 * 관리자가 직접 HTML 코드를 입력해서 자유롭게 꾸밀 수 있는 블록.
 * ⚠ 본인 입력이므로 의도적으로 escape 하지 않음.
 *
 * 옵션:
 *   - html  (string) — HTML 내용
 *   - title (string, optional) — 카드 위에 라벨 표시
 */

$opts = $opts ?? [];
$html  = $opts['html']  ?? '';
$title = $opts['title'] ?? '';

if ($html === '') {
    if (is_admin()) {
        echo '<div class="home-card custom-html"><div class="empty-msg">관리자 페이지에서 HTML 내용을 입력하세요.</div></div>';
    }
    return;
}
?>
<div class="home-card custom-html">
  <?php if (is_admin()): ?>
    <span class="drag-handle">drag_indicator</span>
  <?php endif; ?>

  <?php if ($title): ?>
    <div class="home-block-label"><?= h($title) ?></div>
  <?php endif; ?>

  <div class="custom-content"><?= $html /* 의도적 raw — 관리자 신뢰 */ ?></div>
</div>
