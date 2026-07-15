<?php
/**
 * skins/board/dream/view.php — 드림 미니홈
 *
 * 레이아웃:
 *   [헤더 (배경 + 페어명만, 아이콘 없음)]
 *   [본문 영역]
 *     · 좌: 오너 레일 (메인·게시글·메모·이야기·커미션) — 고정
 *     · 중: 탭 콘텐츠 (메인=캐릭터 A/B 패널 + 페어설정, 나머지는 1-E)
 *     · 우: AI 레일 (하루·편지·기록·라운지·캐릭터 설정) — Pro·접이식
 *
 * 1-D 범위: 비주얼 골격 + 메인 탭(캐릭터) 완성. 오너 하위탭·AI 동작은 후속(1-E/1-F).
 *
 * 변수: $post, $board, $comments, $flash
 */
require_once __DIR__ . '/_helpers.php';
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

$d      = dr_data($post);
$accent = $d['color'] ?: '';
$is_pro = (defined('DREAM_TIER') && DREAM_TIER === 'pro');

$header_src = $d['header_image'] ? dr_img_src($d['header_image'], $board) : '';
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/board/dream/style.css?v=<?= filemtime(HP_PATH . '/skins/board/dream/style.css') ?>">

<div class="list-wrap dream-view"<?= $accent ? ' style="--dr-color:'.h($accent).'"' : '' ?>>
  <?php if ($flash): ?>
    <div class="dr-flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <?php
  $_scheme    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
  $_share_url = $_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . HP_BASE . '/share.php?po_id=' . (int)$post['po_id'];
  ?>
  <div class="list-actions">
    <a href="<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>" class="btn-list">목록</a>
    <?php if (empty($post['po_is_private'])): ?>
      <button type="button" class="btn-list" onclick="spaShare('<?= h($_share_url) ?>', this)">공유</button>
    <?php endif; ?>
    <?php if (is_admin()): ?>
      <a href="<?= hp_url('board', ['slug' => $board['bo_slug'], 'edit' => $post['po_id']]) ?>" class="btn-new">수정</a>
      <form method="post" action="<?= hp_url('board', ['slug' => $board['bo_slug']]) ?>" style="display:inline"
            onsubmit="if(!confirm('이 드림을 삭제할까요? 하위 게시글도 함께 삭제됩니다.')){event.preventDefault();event.stopPropagation();return false;}return true;">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="delete_dream">
        <input type="hidden" name="po_id"  value="<?= (int)$post['po_id'] ?>">
        <button type="submit" class="btn-del">삭제</button>
      </form>
    <?php endif; ?>
  </div>

  <article class="dr-mini">
    <!-- 헤더 (페어명만) -->
    <header class="dr-hero <?= $header_src ? 'has-bg' : 'no-bg' ?>"
            <?= $header_src ? 'style="background-image:url(\''.h($header_src).'\')"' : '' ?>>
      <div class="dr-hero-name">
        <?php if (!empty($post['po_category'])): ?>
          <span class="post-cat-chip"><?= h($post['po_category']) ?></span>
        <?php endif; ?>
        <h1 class="dr-hero-title"><?= h($post['po_title']) ?></h1>
        <?php if (($d['char_a']['name'] ?? '') || ($d['char_b']['name'] ?? '')): ?>
          <div class="dr-hero-cp"><?= h($d['char_a']['name'] ?? '?') ?> × <?= h($d['char_b']['name'] ?? '?') ?></div>
        <?php endif; ?>
      </div>

      <?php if ($is_pro): ?>
        <button type="button" class="dr-ai-toggle" id="drAiToggle">AI 활동</button>
      <?php endif; ?>
    </header>

    <!-- 본문: 오너 레일 + 콘텐츠 (+ AI 레일) -->
    <div class="dr-mini-body">
      <!-- 좌: 오너 레일 -->
      <nav class="dr-owner-rail">
        <div class="dr-rail-grp">홈</div>
        <a class="dr-rail-item is-active" data-tab="main">메인</a>
        <a class="dr-rail-item" data-tab="posts">게시글</a>
        <a class="dr-rail-item" data-tab="memo">메모</a>
        <a class="dr-rail-item" data-tab="story">이야기</a>
        <a class="dr-rail-item" data-tab="commission">커미션</a>
      </nav>

      <!-- 중: 콘텐츠 -->
      <div class="dr-content">
        <!-- 메인 탭: 캐릭터 A/B + 페어 설정 -->
        <section class="dr-tab-panel is-active" id="dr-tab-main">
          <div class="dr-chars">
            <?php foreach (['a', 'b'] as $side):
              $cd = $d['char_' . $side];
              $img_src = !empty($cd['main_img']) ? dr_img_src($cd['main_img'], $board) : '';
              $colors  = array_values(array_filter($cd['colors'] ?? [], function($c){ return $c !== ''; }));
              $main_c  = $colors[0] ?? '';
            ?>
              <div class="dr-char dr-char-<?= h($side) ?>"<?= $main_c ? ' style="--char-main:'.h($main_c).'"' : '' ?>>
                <div class="dr-char-face <?= $img_src ? 'has-img' : 'no-img' ?>">
                  <?php if ($img_src): ?><img src="<?= h($img_src) ?>" alt="<?= h($cd['name']) ?>"><?php endif; ?>
                </div>

                <?php if ($colors): ?>
                  <div class="dr-themebar">
                    <?php foreach ($colors as $i => $cc): ?>
                      <span class="dr-themebar-cell <?= $i === 0 ? 'is-main' : '' ?>" style="background-color:<?= h($cc) ?>"></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="dr-char-info">
                  <h2 class="dr-char-name">
                    <?= h($cd['name'] ?: $side) ?>
                    <?php if (!empty($cd['fullname'])): ?><small class="dr-char-name-en"><?= h($cd['fullname']) ?></small><?php endif; ?>
                  </h2>
                  <?php if (!empty($cd['subtitle'])): ?><p class="dr-char-keyword">"<?= h($cd['subtitle']) ?>"</p><?php endif; ?>
                </div>

                <?php
                // 신상 — 채워진 것만
                $info = [];
                if (!empty($cd['age']))      $info['나이'] = $cd['age'];
                if (!empty($cd['birthday'])) $info['생일'] = $cd['birthday'];
                if (!empty($cd['height']))   $info['키']   = $cd['height'];
                if (!empty($cd['mbti']))     $info['MBTI'] = $cd['mbti'];
                if (!empty($cd['likes']))    $info['좋아하는 것'] = $cd['likes'];
                if (!empty($cd['dislikes'])) $info['싫어하는 것'] = $cd['dislikes'];
                if ($info):
                ?>
                  <ul class="dr-extras-list">
                    <?php foreach ($info as $k => $v): ?>
                      <li><strong><?= h($k) ?></strong><span><?= h($v) ?></span></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

                <?php if (!empty($cd['tags'])): ?>
                  <div class="dr-char-tags"><?= h($cd['tags']) ?></div>
                <?php endif; ?>

                <?php if (!empty($cd['desc'])): ?>
                  <div class="dr-char-desc"><?= nl2br(h($cd['desc'])) ?></div>
                <?php endif; ?>

                <?php if (!empty($cd['bgm_id'])): ?>
                  <div class="dr-char-bgm">
                    <span class="dr-bgm-label">♪ <?= h($cd['bgm_title'] ?: 'BGM') ?></span>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (!empty($d['content'])): ?>
            <section class="dr-section">
              <h3 class="dr-section-title">페어 설정</h3>
              <div class="dr-relation-content markdown-body"><?= hp_render_markdown($d['content']) ?></div>
            </section>
          <?php endif; ?>

          <?php if (!empty($d['ext_images'])): ?>
            <section class="dr-section">
              <h3 class="dr-section-title">분위기 컷</h3>
              <div class="dr-sub-images">
                <?php foreach ($d['ext_images'] as $url): ?>
                  <a href="<?= h($url) ?>" target="_blank" rel="noopener"><img src="<?= h($url) ?>" alt="" loading="lazy"></a>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endif; ?>

          <div class="dr-meta">
            <span><?= h(date('Y년 m월 d일', strtotime($post['po_created_at']))) ?></span>
            <span class="dot">·</span>
            <span>조회 <?= number_format($post['po_views']) ?></span>
          </div>
        </section>

        <!-- 하위 탭: 서브보드 (게시글·메모·커미션) -->
        <section class="dr-tab-panel" id="dr-tab-posts">
          <?php $sb_kind = 'post'; $sb_label = '게시글'; include __DIR__ . '/_subboard.php'; ?>
        </section>
        <section class="dr-tab-panel" id="dr-tab-memo">
          <?php $sb_kind = 'memo'; $sb_label = '메모'; include __DIR__ . '/_subboard.php'; ?>
        </section>
        <section class="dr-tab-panel" id="dr-tab-story">
          <?php include __DIR__ . '/_story.php'; ?>
        </section>
        <section class="dr-tab-panel" id="dr-tab-commission">
          <?php $sb_kind = 'commission'; $sb_label = '커미션'; include __DIR__ . '/_subboard.php'; ?>
        </section>
      </div>

      <!-- 우: AI 레일 (Pro·접이식) -->
      <?php if ($is_pro): ?>
        <aside class="dr-ai-rail" id="drAiRail">
          <div class="dr-ai-rail-inner">
            <div class="dr-ai-rail-head">
              <span class="dr-ai-rail-title">AI 활동</span>
              <button type="button" class="dr-ai-rail-close">✕</button>
            </div>
            <a class="dr-ai-item is-active" data-ai="hari">하루</a>
            <a class="dr-ai-item" data-ai="letter">편지</a>
            <a class="dr-ai-item" data-ai="record">기록</a>
            <a class="dr-ai-item" data-ai="lounge">라운지</a>
            <div class="dr-ai-divider"></div>
            <a class="dr-ai-item" data-ai="persona">캐릭터 설정</a>
            <div class="dr-ai-todo">AI 기능은 다음 업데이트에서 제공됩니다.</div>
          </div>
        </aside>
      <?php endif; ?>
    </div>
  </article>

  <?php include HP_PATH . '/inc/post-comments.php'; ?>
</div>

<script>
// SPA 안전: document 위임 + 중복 등록 가드 (인라인 onclick 미사용)
(function(){
  if (window.__drViewBound) return;
  window.__drViewBound = true;

  document.addEventListener('click', function(e){
    // 오너 탭 전환
    var tabEl = e.target.closest('.dr-owner-rail .dr-rail-item');
    if (tabEl) {
      var tab = tabEl.getAttribute('data-tab');
      var rail = tabEl.closest('.dr-owner-rail');
      rail.querySelectorAll('.dr-rail-item').forEach(function(a){ a.classList.remove('is-active'); });
      tabEl.classList.add('is-active');
      var mini = tabEl.closest('.dr-mini');
      mini.querySelectorAll('.dr-tab-panel').forEach(function(p){
        p.classList.toggle('is-active', p.id === 'dr-tab-' + tab);
      });
      return;
    }

    // AI 레일 토글 (열기 버튼 / 닫기 ×)
    if (e.target.closest('.dr-ai-toggle') || e.target.closest('.dr-ai-rail-close')) {
      var r = document.getElementById('drAiRail');
      if (r) {
        var open = r.classList.toggle('open');
        var btn = document.getElementById('drAiToggle');
        if (btn) btn.textContent = open ? '닫기' : 'AI 활동';
      }
      return;
    }

    // AI 항목 선택
    var aiEl = e.target.closest('.dr-ai-rail .dr-ai-item');
    if (aiEl) {
      var aiRail = aiEl.closest('.dr-ai-rail');
      aiRail.querySelectorAll('.dr-ai-item').forEach(function(a){ a.classList.remove('is-active'); });
      aiEl.classList.add('is-active');
      return;
    }

    // 서브보드 수정 버튼 → 폼 열고 기존 내용 채우기 (dp_update 모드)
    var editBtn = e.target.closest('.dr-sub-edit');
    if (editBtn) {
      var uid = editBtn.dataset.kind;
      var form = document.getElementById('drSubForm_' + uid);
      if (form) {
        var actEl = document.getElementById('drSubAction_' + uid);
        var subIdEl = document.getElementById('drSubPoId_' + uid);
        if (actEl)   actEl.value = 'dp_update';
        if (subIdEl) subIdEl.value = editBtn.dataset.subId;
        var titleEl = document.getElementById('drSubTitle_' + uid);
        if (titleEl) titleEl.value = editBtn.dataset.title || '';
        var content = editBtn.dataset.content || '';
        var mdEditor = form.querySelector('.md-editor');
        var ta = form.querySelector('.md-input');
        if (mdEditor && mdEditor._setContent) {
          mdEditor._setContent(content);
        } else if (ta) {
          ta.value = content;
        }
        var formTitle = document.getElementById('drSubFormTitle_' + uid);
        if (formTitle) formTitle.textContent = '수정';
        var submitBtn = document.getElementById('drSubSubmit_' + uid);
        if (submitBtn) submitBtn.textContent = '수정 완료';
        form.classList.add('open');
        if (titleEl) setTimeout(function(){ titleEl.focus(); }, 50);
      }
      return;
    }

    // 서브보드 폼 열기
    var toggleBtn = e.target.closest('.dr-sub-toggle');
    if (toggleBtn) {
      var form = document.getElementById('drSubForm_' + toggleBtn.dataset.uid);
      if (form) {
        // 새 글 모드로 리셋 (수정 모드였을 수 있으니)
        var actEl = document.getElementById('drSubAction_' + toggleBtn.dataset.uid);
        var subIdEl = document.getElementById('drSubPoId_' + toggleBtn.dataset.uid);
        if (actEl)   actEl.value = 'dp_add';
        if (subIdEl) subIdEl.value = '0';
        var submitBtn = document.getElementById('drSubSubmit_' + toggleBtn.dataset.uid);
        if (submitBtn) submitBtn.textContent = '추가';
        form.classList.toggle('open');
        if (form.classList.contains('open')) {
          var t = document.getElementById('drSubTitle_' + toggleBtn.dataset.uid);
          if (t) setTimeout(function(){ t.focus(); }, 50);
        }
      }
      return;
    }
    // 서브보드 폼 닫기
    var closeBtn = e.target.closest('.dr-sub-close');
    if (closeBtn) {
      var f2 = document.getElementById('drSubForm_' + closeBtn.dataset.uid);
      if (f2) f2.classList.remove('open');
      return;
    }

    // 게시글/커미션: 카드 클릭 → 인라인 상세 전환
    var openable = e.target.closest('.dr-sub-openable, .dr-comm-card');
    if (openable && !e.target.closest('.dr-sub-del-form')) {
      var id = openable.getAttribute('data-sub-id');
      var sub = openable.closest('.dr-sub');
      var detail = sub && sub.querySelector('#dr-detail-' + id);
      if (detail) {
        sub.querySelectorAll('.dr-sub-listview > .dr-sub-grid, .dr-sub-listview > .dr-comm-grid')
           .forEach(function(g){ g.style.display = 'none'; });
        sub.querySelectorAll('.dr-sub-detail').forEach(function(d){ d.style.display = 'none'; });
        detail.style.display = 'block';
      }
      return;
    }
    // 상세 → 목록 복귀 (게시글/커미션 전용 — 이야기 dr-story-back 제외)
    var backBtn = e.target.closest('.dr-detail-back');
    if (backBtn && !backBtn.classList.contains('dr-story-back')) {
      var sub2 = backBtn.closest('.dr-sub');
      var det = backBtn.closest('.dr-sub-detail');
      if (det) det.style.display = 'none';
      if (sub2) sub2.querySelectorAll('.dr-sub-listview > .dr-sub-grid, .dr-sub-listview > .dr-comm-grid')
          .forEach(function(g){ g.style.display = ''; });
      return;
    }

    // 메모: 아이템 클릭 → 모달
    var memoItem = e.target.closest('.dr-memo-item');
    if (memoItem && !e.target.closest('.dr-sub-del-form')) {
      var msub = memoItem.closest('.dr-sub');
      var uid = msub.id.replace('dr-sub-', '');
      var modal = document.getElementById('drMemoModal_' + uid);
      if (modal) {
        modal.querySelector('.dr-memo-modal-title').textContent = memoItem.dataset.memoTitle || '';
        modal.querySelector('.dr-memo-modal-date').textContent  = memoItem.dataset.memoDate || '';
        var body = memoItem.querySelector('.dr-memo-body');
        modal.querySelector('.dr-memo-modal-body').innerHTML = body ? body.innerHTML : '';
        modal.style.display = 'flex';
      }
      return;
    }
    // 메모 모달 닫기 (× 또는 배경)
    if (e.target.closest('.dr-memo-modal-close') || e.target.classList.contains('dr-memo-modal-backdrop')) {
      var mm = e.target.closest('.dr-memo-modal');
      if (mm) mm.style.display = 'none';
      return;
    }

    // ─── 이야기(소설) ───
    // 작품 클릭 → 회차 영역 전환
    var novelOpen = e.target.closest('.dr-novel-open');
    if (novelOpen) {
      var dnId = novelOpen.getAttribute('data-dn-id');
      var story = novelOpen.closest('.dr-story');
      if (story) {
        var lv = story.querySelector('.dr-story-listview');
        if (lv) lv.style.display = 'none';
        story.querySelectorAll('.dr-chapters').forEach(function(c){ c.style.display = 'none'; });
        var target = story.querySelector('#story-' + dnId);
        if (target) target.style.display = 'block';
      }
      return;
    }
    // 회차 영역 → 작품 목록 복귀
    if (e.target.closest('.dr-story-back')) {
      var story2 = e.target.closest('.dr-story');
      if (story2) {
        story2.querySelectorAll('.dr-chapters').forEach(function(c){ c.style.display = 'none'; });
        var lv2 = story2.querySelector('.dr-story-listview');
        if (lv2) lv2.style.display = '';
      }
      return;
    }
    // 회차 클릭 → 본문 펼침/접기
    var chOpen = e.target.closest('.dr-chapter-open');
    if (chOpen) {
      var item = chOpen.closest('.dr-chapter-item');
      var body = item && item.querySelector('.dr-chapter-body');
      if (body) body.style.display = (body.style.display === 'none' || !body.style.display) ? 'block' : 'none';
      return;
    }
    // 작품 수정 버튼 → 폼 채우기
    var nEdit = e.target.closest('.dr-novel-edit');
    if (nEdit) {
      var f = document.getElementById('drSubForm_novel');
      if (f) {
        document.getElementById('drNovelId').value       = nEdit.dataset.dnId;
        document.getElementById('drNovelTitle').value    = nEdit.dataset.title || '';
        document.getElementById('drNovelSubtitle').value = nEdit.dataset.subtitle || '';
        document.getElementById('drNovelStatus').value   = nEdit.dataset.status || 'ongoing';
        document.getElementById('drNovelRating').value   = nEdit.dataset.rating || 'all';
        document.getElementById('drNovelCategory').value = nEdit.dataset.category || '';
        document.getElementById('drNovelDesc').value     = nEdit.dataset.desc || '';
        document.getElementById('drNovelSecret').checked = nEdit.dataset.secret === '1';
        document.getElementById('drNovelFormTitle').textContent = '작품 수정';
        document.getElementById('drNovelSubmit').textContent = '수정 완료';
        f.classList.add('open');
        f.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      return;
    }
    // 회차 추가 버튼 → 폼 열기 (새 회차)
    var chAddBtn = e.target.closest('.dr-chapter-add-btn');
    if (chAddBtn) {
      var dn = chAddBtn.dataset.dnId;
      var cf = document.getElementById('drChForm_' + dn);
      if (cf) {
        document.getElementById('drChId_' + dn).value = '0';
        document.getElementById('drChNum_' + dn).value = '';
        document.getElementById('drChTitle_' + dn).value = '';
        document.getElementById('drChFormTitle_' + dn).textContent = '새 회차';
        cf.classList.add('open');
      }
      return;
    }
    // 회차 폼 닫기
    var chClose = e.target.closest('.dr-ch-close');
    if (chClose) {
      var cf2 = document.getElementById('drChForm_' + chClose.dataset.dn);
      if (cf2) cf2.classList.remove('open');
      return;
    }
    // 회차 수정 버튼 → 폼 채우기 (본문은 폼 에디터 한계로 제목/화수/비밀만)
    var chEdit = e.target.closest('.dr-chapter-edit');
    if (chEdit) {
      var dn2 = chEdit.dataset.dn;
      var cf3 = document.getElementById('drChForm_' + dn2);
      if (cf3) {
        document.getElementById('drChId_' + dn2).value = chEdit.dataset.dcId;
        document.getElementById('drChNum_' + dn2).value = chEdit.dataset.number || '';
        document.getElementById('drChTitle_' + dn2).value = chEdit.dataset.title || '';
        document.getElementById('drChFormTitle_' + dn2).textContent = chEdit.dataset.title + ' 수정';
        cf3.classList.add('open');
        cf3.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      return;
    }
  });

  // 파일명 표시 (위임)
  document.addEventListener('change', function(e){
    var inp = e.target.closest('.dr-file-hidden');
    if (!inp) return;
    var t = document.getElementById(inp.dataset.nameTarget);
    if (t) t.textContent = inp.files.length ? inp.files[0].name : '선택된 파일 없음';
  });
})();
</script>
