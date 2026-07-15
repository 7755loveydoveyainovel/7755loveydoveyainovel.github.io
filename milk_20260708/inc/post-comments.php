<?php
/**
 * inc/post-comments.php — 댓글 영역 partial
 *
 * 모든 게시판 스킨이 공유하는 댓글 UI. 디자인은 css/core.css 의 .comments
 * 클래스를 사용하므로 스킨이 따로 신경 쓸 필요 없음.
 *
 * 사용법: 스킨의 view.php 에서
 *   <?php include HP_PATH . '/inc/post-comments.php'; ?>
 *
 * 필요 변수 (스코프에서 자동 인식): $post, $comments, $flash
 */
?>
<section class="comments">
  <h2 class="comments-head">댓글 <span class="count"><?= count($comments) ?></span></h2>

  <?php if (!empty($flash)): ?>
    <div class="cm-flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <?php if ($comments):
    // 트리 빌드 — 1단계 깊이만 (top + replies)
    $top_comments = [];
    $children_map = [];
    foreach ($comments as $_c) {
        if (!empty($_c['cm_parent_id'])) {
            $children_map[(int)$_c['cm_parent_id']][] = $_c;
        } else {
            $top_comments[] = $_c;
        }
    }
  ?>
    <div class="comment-list">
      <?php foreach ($top_comments as $top):
        $c = $top;       // partial 이 $c 를 기대함
        $is_top = true;
        $kids = $children_map[(int)$top['cm_id']] ?? [];
      ?>
        <div class="comment-thread">
          <?php include HP_PATH . '/inc/comment-item.php'; ?>

          <?php if ($kids): ?>
            <div class="comment-replies">
              <?php foreach ($kids as $c): $is_top = false; ?>
                <?php include HP_PATH . '/inc/comment-item.php'; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- 댓글 폼 — 관리자 / 방문자 분기 -->
  <form method="post" class="comment-form <?= is_admin() ? 'admin' : '' ?>">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="comment">

    <?php if (is_admin()): ?>
      <div class="row">
        <span class="admin-name">
          <?= h(hp_config('admin_nickname', '') ?: hp_config('site_name', '주인장')) ?>
          <span class="admin-tag">관리자</span>
        </span>
      </div>
    <?php else: ?>
      <div class="row">
        <input type="text" name="name" placeholder="이름" required maxlength="40" class="cm-name">
        <input type="password" name="password" placeholder="비밀번호 (선택, 비공개용)" maxlength="40" class="cm-pw">
        <label class="private-toggle">
          <input type="checkbox" name="private" value="1">
          <span class="ico"><?= hp_icon('fas fa-lock') ?></span><span class="lbl">비공개</span>
        </label>
      </div>
    <?php endif; ?>

    <textarea name="content" placeholder="댓글을 남겨보세요" required maxlength="1000"></textarea>
    <button type="submit">댓글 등록</button>
  </form>
</section>
