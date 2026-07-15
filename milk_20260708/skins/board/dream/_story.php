<?php
/**
 * skins/board/dream/_story.php — 이야기 탭 (소설: 작품 + 회차)
 *
 * 구조:
 *   [작품 그리드]  ← 표지/제목/상태/회차수
 *     └ 작품 클릭 → [회차 영역] 전환 (목록 + 회차 뷰어 + 관리자 작성)
 *
 * 변수: $post, $board (view.php 스코프)
 * 저장: dn_save/dn_delete/dc_save/dc_delete → dr_handle_post
 */
$dr_id    = (int)$post['po_id'];
$post_url = hp_url('board', ['slug' => $board['bo_slug']]);
$novels   = [];
$st_err   = '';
try {
    $novels = dr_novels_list($dr_id);
} catch (Throwable $e) {
    $st_err = $e->getMessage();
}
?>
<div class="dr-story" id="dr-story">
  <?php if ($st_err && is_admin()): ?>
    <div class="dr-flash error" style="white-space:pre-wrap">이야기 오류: <?= h($st_err) ?></div>
  <?php endif; ?>

  <!-- ═══ 작품 목록 ═══ -->
  <div class="dr-story-listview">
    <div class="dr-story-head">
      <h3>이야기 <small>(<?= number_format(count($novels)) ?>)</small></h3>
      <?php if (is_admin()): ?>
        <button type="button" class="dr-sub-toggle" data-uid="novel">+ 새 작품</button>
      <?php endif; ?>
    </div>

    <?php if (is_admin()): ?>
      <!-- 작품 작성/수정 폼 -->
      <form class="dr-sub-form" id="drSubForm_novel" method="post" enctype="multipart/form-data" action="<?= h($post_url) ?>">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="dn_save">
        <input type="hidden" name="po_id"  value="<?= $dr_id ?>">
        <input type="hidden" name="dn_id"  value="0" id="drNovelId">

        <div class="dr-sub-form-head">
          <strong id="drNovelFormTitle">새 작품</strong>
          <button type="button" class="dr-sub-close" data-uid="novel">×</button>
        </div>

        <input type="text" name="dn_title" id="drNovelTitle" placeholder="작품 제목" maxlength="200" required class="dr-sub-title-input">
        <input type="text" name="dn_subtitle" id="drNovelSubtitle" placeholder="부제 (선택)" maxlength="200" class="dr-sub-title-input">

        <div class="dr-story-form-row">
          <select name="dn_status" id="drNovelStatus" class="dr-story-select">
            <option value="ongoing">연재중</option>
            <option value="completed">완결</option>
            <option value="hiatus">휴재</option>
            <option value="draft">초안</option>
          </select>
          <select name="dn_rating" id="drNovelRating" class="dr-story-select">
            <option value="all">전체</option>
            <option value="teen">15+</option>
            <option value="adult">19+</option>
          </select>
          <input type="text" name="dn_category" id="drNovelCategory" placeholder="장르(선택)" maxlength="50" class="dr-story-cat">
        </div>

        <div class="dr-story-form-row">
          <input type="text" name="dn_cover_url" id="drNovelCover" placeholder="표지 이미지 URL 또는 ↓ 파일" class="dr-story-cover-url">
          <label class="dr-file-input">
            <input type="file" name="dn_cover_file" accept="image/*" class="dr-file-hidden" data-name-target="drNovelCoverName">
            <span class="dr-file-input-btn">파일</span>
            <span class="dr-file-input-name" id="drNovelCoverName">선택 없음</span>
          </label>
        </div>

        <textarea name="dn_description" id="drNovelDesc" rows="3" placeholder="작품 소개 (선택)" class="dr-story-desc"></textarea>

        <label class="dr-story-secret">
          <input type="checkbox" name="dn_secret" value="1" id="drNovelSecret"> 비밀 작품 (관리자만)
        </label>

        <div class="dr-sub-form-actions">
          <button type="submit" class="dr-submit" id="drNovelSubmit">작품 등록</button>
        </div>
      </form>
    <?php endif; ?>

    <?php if (empty($novels)): ?>
      <div class="dr-sub-empty">
        <?= is_admin() ? '아직 작품이 없어요. [+ 새 작품] 으로 시작해보세요.' : '아직 등록된 작품이 없어요.' ?>
      </div>
    <?php else: ?>
      <div class="dr-novel-grid">
        <?php foreach ($novels as $n):
          if (!empty($n['dn_secret']) && !is_admin()) continue;
          $cover = $n['dn_cover'] ?? '';
          $cover_src = $cover ? (preg_match('#^https?://#i', $cover) ? $cover : hp_post_image_url($cover, '_dreamlog')) : '';
        ?>
          <div class="dr-novel-card" data-dn-id="<?= (int)$n['dn_id'] ?>">
            <div class="dr-novel-open" data-dn-id="<?= (int)$n['dn_id'] ?>">
              <div class="dr-novel-cover <?= $cover_src ? 'has' : 'no' ?>">
                <?php if ($cover_src): ?>
                  <img src="<?= h($cover_src) ?>" alt="<?= h($n['dn_title']) ?>" loading="lazy">
                <?php else: ?>
                  <span class="dr-novel-cover-ph"><?= function_exists('hp_icon') ? hp_icon('fas fa-book') : '📖' ?></span>
                <?php endif; ?>
                <span class="dr-novel-status st-<?= h($n['dn_status']) ?>"><?= h(dr_novel_status_label($n['dn_status'])) ?></span>
                <?php if (!empty($n['dn_secret'])): ?><span class="dr-novel-secret">🔒</span><?php endif; ?>
              </div>
              <div class="dr-novel-meta">
                <div class="dr-novel-title"><?= h($n['dn_title']) ?></div>
                <?php if (!empty($n['dn_subtitle'])): ?><div class="dr-novel-subtitle"><?= h($n['dn_subtitle']) ?></div><?php endif; ?>
                <div class="dr-novel-info">
                  <?php if (!empty($n['dn_category'])): ?><span class="dr-novel-cat"><?= h($n['dn_category']) ?></span><?php endif; ?>
                  <span class="dr-novel-chcount"><?= (int)$n['chapter_count'] ?>화</span>
                  <?php if ($n['dn_rating'] !== 'all'): ?><span class="dr-novel-rating"><?= h(dr_novel_rating_label($n['dn_rating'])) ?></span><?php endif; ?>
                </div>
              </div>
            </div>
            <?php if (is_admin()): ?>
              <div class="dr-novel-actions">
                <button type="button" class="dr-novel-edit"
                        data-dn-id="<?= (int)$n['dn_id'] ?>"
                        data-title="<?= h($n['dn_title']) ?>" data-subtitle="<?= h($n['dn_subtitle']) ?>"
                        data-status="<?= h($n['dn_status']) ?>" data-rating="<?= h($n['dn_rating']) ?>"
                        data-category="<?= h($n['dn_category']) ?>" data-desc="<?= h($n['dn_description']) ?>"
                        data-secret="<?= !empty($n['dn_secret']) ? 1 : 0 ?>" title="수정">✎</button>
                <form method="post" action="<?= h($post_url) ?>" class="dr-novel-del-form"
                      onsubmit="if(!confirm('작품과 모든 회차를 삭제할까요?')){event.preventDefault();return false;}return true;">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="dn_delete">
                  <input type="hidden" name="po_id"  value="<?= $dr_id ?>">
                  <input type="hidden" name="dn_id"  value="<?= (int)$n['dn_id'] ?>">
                  <button type="submit" class="dr-novel-del" title="삭제">×</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ═══ 회차 영역 (작품별, 클릭 시 표시) ═══ -->
  <?php foreach ($novels as $n):
    if (!empty($n['dn_secret']) && !is_admin()) continue;
    $dn_id    = (int)$n['dn_id'];
    $chapters = [];
    try { $chapters = dr_chapters_list($dn_id); } catch (Throwable $e) {}
  ?>
    <div class="dr-chapters" id="story-<?= $dn_id ?>" style="display:none">
      <button type="button" class="dr-detail-back dr-story-back">← 작품 목록</button>
      <div class="dr-chapters-head">
        <h3><?= h($n['dn_title']) ?> <small><?= count($chapters) ?>화</small></h3>
        <?php if (is_admin()): ?>
          <button type="button" class="dr-chapter-add-btn" data-dn-id="<?= $dn_id ?>">+ 새 회차</button>
        <?php endif; ?>
      </div>

      <?php if (!empty($n['dn_description'])): ?>
        <div class="dr-chapters-desc"><?= nl2br(h($n['dn_description'])) ?></div>
      <?php endif; ?>

      <?php if (is_admin()): ?>
        <!-- 회차 작성 폼 -->
        <form class="dr-sub-form dr-chapter-form" id="drChForm_<?= $dn_id ?>" method="post" action="<?= h($post_url) ?>">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="dc_save">
          <input type="hidden" name="po_id"  value="<?= $dr_id ?>">
          <input type="hidden" name="dn_id"  value="<?= $dn_id ?>">
          <input type="hidden" name="dc_id"  value="0" id="drChId_<?= $dn_id ?>">
          <div class="dr-sub-form-head">
            <strong id="drChFormTitle_<?= $dn_id ?>">새 회차</strong>
            <button type="button" class="dr-ch-close" data-dn="<?= $dn_id ?>">×</button>
          </div>
          <div class="dr-story-form-row">
            <input type="number" name="dc_number" id="drChNum_<?= $dn_id ?>" placeholder="화수(자동)" class="dr-ch-num" min="0">
            <input type="text" name="dc_title" id="drChTitle_<?= $dn_id ?>" placeholder="회차 제목" maxlength="200" required class="dr-sub-title-input" style="margin:0">
          </div>
          <?php
            $editor_name  = 'dc_content';
            $editor_value = '';
            include HP_PATH . '/inc/markdown-editor.php';
          ?>
          <label class="dr-story-secret">
            <input type="checkbox" name="dc_secret" value="1" id="drChSecret_<?= $dn_id ?>"> 비밀 회차
          </label>
          <div class="dr-sub-form-actions">
            <button type="submit" class="dr-submit">회차 저장</button>
          </div>
        </form>
      <?php endif; ?>

      <?php if (empty($chapters)): ?>
        <div class="dr-sub-empty">아직 회차가 없어요.</div>
      <?php else: ?>
        <div class="dr-chapter-list">
          <?php foreach ($chapters as $ch):
            if (!empty($ch['dc_secret']) && !is_admin()) continue;
            $full = dr_chapter_get($dn_id, $ch['dc_id']);
          ?>
            <div class="dr-chapter-item" data-dc-id="<?= (int)$ch['dc_id'] ?>">
              <div class="dr-chapter-row dr-chapter-open" data-dc-id="<?= (int)$ch['dc_id'] ?>">
                <span class="dr-chapter-num"><?= (int)$ch['dc_number'] ?>화</span>
                <span class="dr-chapter-title"><?= h($ch['dc_title']) ?><?= !empty($ch['dc_secret']) ? ' 🔒' : '' ?></span>
                <span class="dr-chapter-wc"><?= number_format($ch['dc_word_count']) ?>자</span>
              </div>
              <!-- 회차 본문 (펼침) -->
              <div class="dr-chapter-body" style="display:none">
                <div class="dr-chapter-content markdown-body"><?= hp_render_markdown($full['dc_content'] ?? '') ?></div>
                <?php if (is_admin()): ?>
                  <div class="dr-chapter-edit-bar">
                    <button type="button" class="dr-chapter-edit"
                            data-dn="<?= $dn_id ?>" data-dc-id="<?= (int)$ch['dc_id'] ?>"
                            data-number="<?= (int)$ch['dc_number'] ?>" data-title="<?= h($ch['dc_title']) ?>"
                            data-secret="<?= !empty($ch['dc_secret']) ? 1 : 0 ?>">수정</button>
                    <form method="post" action="<?= h($post_url) ?>" style="display:inline"
                          onsubmit="if(!confirm('이 회차를 삭제할까요?')){event.preventDefault();return false;}return true;">
                      <?= csrf_input() ?>
                      <input type="hidden" name="action" value="dc_delete">
                      <input type="hidden" name="po_id"  value="<?= $dr_id ?>">
                      <input type="hidden" name="dn_id"  value="<?= $dn_id ?>">
                      <input type="hidden" name="dc_id"  value="<?= (int)$ch['dc_id'] ?>">
                      <button type="submit" class="dr-chapter-del">삭제</button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
