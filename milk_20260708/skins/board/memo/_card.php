<?php
/**
 * skins/board/memo/_card.php — 카드 한 장 렌더 partial
 *
 * 변수: $p (post 행), $board, $type 자동 결정
 *
 * link 카드 안에서 트위터/X, 인스타그램 URL 은 자동으로 임베드 카드로 표시.
 * 그 외 link 는 일반 썸네일 + 제목 카드.
 */
$type = memo_card_type($p);
$user_cat  = memo_user_category($p);
$thumb_src = memo_img_src($p['po_thumbnail'] ?? '', $board);
$yt_id     = ($type === 'video') ? memo_youtube_id($p['po_content'] ?? '') : null;

// link 임베드 감지
$tw_id    = null;
$tw_url   = null;
$ig_match = null;
if ($type === 'link') {
    $url = $p['po_content'] ?? '';
    if (preg_match('#(?:twitter\.com|x\.com)/[\w]+/status/(\d+)#', $url, $m)) {
        $tw_id  = $m[1];
        $tw_url = $url;
    } elseif (preg_match('#instagram\.com/(?:p|reel|tv)/[\w-]+#', $url)) {
        $ig_match = $url;
    }
}
?>
<div class="memo-card memo-card--<?= h($type) ?>">
  <?php if ($user_cat !== ''): ?>
    <div class="memo-card-cat-row"><span class="post-cat-chip"><?= h($user_cat) ?></span></div>
  <?php endif; ?>
  <?php if ($type === 'quote'): ?>
    <div class="memo-card-quote">
      <div class="quote-text"><?= nl2br(h($p['po_content'])) ?></div>
      <?php if (!empty($p['po_title'])): ?>
        <div class="quote-source">— <?= h($p['po_title']) ?></div>
      <?php endif; ?>
    </div>

  <?php elseif ($type === 'image' && $thumb_src): ?>
    <img class="memo-card-image" src="<?= h($thumb_src) ?>" alt="<?= h($p['po_title'] ?? '') ?>" loading="lazy">
    <?php if (!empty($p['po_title'])): ?>
      <div class="memo-card-caption"><?= h($p['po_title']) ?></div>
    <?php endif; ?>

  <?php elseif ($type === 'video' && $yt_id): ?>
    <div class="memo-card-video">
      <iframe src="https://www.youtube.com/embed/<?= h($yt_id) ?>"
              title="YouTube"
              frameborder="0"
              allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
              loading="lazy"></iframe>
    </div>
    <?php if (!empty($p['po_title'])): ?>
      <div class="memo-card-caption"><?= h($p['po_title']) ?></div>
    <?php endif; ?>

  <?php elseif ($type === 'link' && $tw_id): ?>
    <div class="memo-card-embed memo-tweet"
         data-tweet-id="<?= h($tw_id) ?>"
         data-tweet-url="<?= h($tw_url) ?>">
      <div class="memo-tweet-loading">트윗 불러오는 중...</div>
    </div>

  <?php elseif ($type === 'link' && $ig_match): ?>
    <div class="memo-card-embed">
      <blockquote class="instagram-media" data-instgrm-permalink="<?= h($ig_match) ?>" data-instgrm-version="14">
        <a href="<?= h($ig_match) ?>">View on Instagram</a>
      </blockquote>
    </div>

  <?php elseif ($type === 'link'): ?>
    <a class="memo-card-link" href="<?= h($p['po_content']) ?>" target="_blank" rel="noopener noreferrer">
      <?php if ($thumb_src): ?>
        <img src="<?= h($thumb_src) ?>" alt="" loading="lazy">
      <?php endif; ?>
      <div class="link-body">
        <?php if (!empty($p['po_title'])): ?>
          <div class="link-title"><?= h($p['po_title']) ?></div>
        <?php endif; ?>
        <?php if (!empty($p['po_subtitle'])): ?>
          <div class="link-desc"><?= h($p['po_subtitle']) ?></div>
        <?php endif; ?>
        <div class="link-host"><?= h(parse_url($p['po_content'], PHP_URL_HOST) ?: $p['po_content']) ?></div>
      </div>
    </a>
  <?php endif; ?>

  <div class="memo-card-meta">
    <time><?= h(date('Y.m.d', strtotime($p['po_created_at']))) ?></time>
    <?php if (is_admin()): ?>
      <span class="memo-card-actions">
        <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'edit' => $p['po_id']]) ?>" class="entry-action-btn">수정</a>
        <form method="post" class="memo-card-del">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="delete_memo">
          <input type="hidden" name="po_id" value="<?= $p['po_id'] ?>">
          <button type="submit" class="entry-action-btn"
                  onclick="return confirm('이 메모를 삭제할까요?')">삭제</button>
        </form>
      </span>
    <?php endif; ?>
  </div>
</div>
