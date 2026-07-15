<?php
/**
 * admin/sections/profile.php — 프로필 카드 정보 + 소셜 링크
 *
 * 메인 페이지의 프로필 카드에 표시되는 모든 항목:
 *  - 인사말 / 자기소개 / 아바타 (사이트 정보에서 이동됨)
 *  - 소셜 링크 (트위터/인스타/메일 등)
 */

/**
 * 소셜 링크 라벨이 비어 있을 때 URL/아이콘에서 보기 좋은 라벨 자동 생성.
 *  - mailto:  → "Email"
 *  - https://twitter.com/...  → "twitter"
 *  - 그 외   → 아이콘 이름 또는 "Link"
 */
function _link_auto_label($url, $icon = '') {
    $url = trim($url);
    if ($url === '') return $icon !== '' ? $icon : 'Link';
    if (stripos($url, 'mailto:') === 0) return 'Email';
    $host = parse_url($url, PHP_URL_HOST);
    if ($host) {
        $host = preg_replace('/^www\./i', '', $host);          // www. 제거
        $parts = explode('.', $host);
        if (count($parts) >= 2) return $parts[count($parts) - 2]; // 도메인 본체 (twitter, instagram …)
        return $host;
    }
    return $icon !== '' ? $icon : 'Link';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_profile') {
        $fields = ['admin_nickname', 'greeting', 'intro', 'avatar_image'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                hp_config_set($f, trim($_POST[$f]));
            }
        }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '프로필이 저장되었습니다.'];
    }

    elseif ($action === 'save_links') {
        if (!empty($_POST['links']) && is_array($_POST['links'])) {
            foreach ($_POST['links'] as $id => $l) {
                $id    = (int)$id;
                $icon  = trim($l['icon']  ?? '');
                $label = trim($l['label'] ?? '');
                $url   = trim($l['url']   ?? '');
                // 라벨은 선택 — 비어 있으면 URL/아이콘에서 자동 생성
                if ($label === '') $label = _link_auto_label($url, $icon);
                if ($id > 0 && $url !== '') {
                    qExec(
                        "UPDATE {link} SET lk_icon = ?, lk_label = ?, lk_url = ? WHERE lk_id = ?",
                        [
                            mb_substr($icon ?: 'link', 0, 40),
                            mb_substr($label, 0, 40),
                            mb_substr($url, 0, 500),
                            $id,
                        ]
                    );
                }
            }
        }

        $new_label = trim($_POST['new_label'] ?? '');
        $new_url   = trim($_POST['new_url']   ?? '');
        $new_icon  = trim($_POST['new_icon']  ?? '');
        if ($new_label === '') $new_label = _link_auto_label($new_url, $new_icon);
        if ($new_url !== '') {
            $max_order = (int)qVal("SELECT COALESCE(MAX(lk_order), 0) FROM {link}");
            qInsert(
                "INSERT INTO {link} (lk_icon, lk_label, lk_url, lk_order) VALUES (?, ?, ?, ?)",
                [
                    mb_substr($new_icon ?: 'link', 0, 40),
                    mb_substr($new_label, 0, 40),
                    mb_substr($new_url, 0, 500),
                    $max_order + 1,
                ]
            );
        }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '링크가 저장되었습니다.'];
    }

    elseif ($action === 'delete_link') {
        $id = (int)($_POST['lk_id'] ?? 0);
        qExec("DELETE FROM {link} WHERE lk_id = ?", [$id]);
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '삭제되었습니다.'];
    }

    header('Location: ' . HP_BASE . '/admin/?section=profile');
    exit;
}

$links = qAll("SELECT * FROM {link} ORDER BY lk_order, lk_id");
?>

<!-- ─── 프로필 카드 정보 ─── -->
<div class="adm-section">
  <h2>프로필 카드</h2>
  <div class="sub">메인 페이지의 프로필 블록에 표시되는 인사말, 자기소개, 아바타 이미지.</div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_profile">

    <div class="adm-field">
      <label>관리자 닉네임</label>
      <span class="hint">방명록에 답글을 남기거나 글을 쓸 때 자동으로 사용되는 이름이에요. 비워두면 사이트 이름이 사용됩니다.</span>
      <input type="text" name="admin_nickname" value="<?= h(hp_config('admin_nickname')) ?>" maxlength="40" placeholder="<?= h(hp_config('site_name', '주인장')) ?>">
    </div>

    <div class="adm-field">
      <label>인사말</label>
      <span class="hint">프로필 카드 상단의 강조 문장.</span>
      <input type="text" name="greeting" value="<?= h(hp_config('greeting')) ?>" maxlength="100">
    </div>

    <div class="adm-field">
      <label>자기 소개</label>
      <span class="hint">프로필 카드 본문. 줄바꿈 가능.</span>
      <textarea name="intro" rows="4"><?= h(hp_config('intro')) ?></textarea>
    </div>

    <div class="adm-field">
      <label>아바타 이미지 URL</label>
      <span class="hint">프로필 카드 상단의 원형 이미지. 비워두면 하트 아이콘이 표시됩니다.</span>
      <input type="url" name="avatar_image" value="<?= h(hp_config('avatar_image')) ?>">
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </form>
</div>

<!-- ─── 소셜 링크 ─── -->
<div class="adm-section">
  <h2>소셜 링크</h2>
  <div class="sub">
    프로필 카드 하단의 작은 아이콘들. 트위터·인스타·메일 등을 등록해요.<br>
    아이콘은 두 가지 시스템 지원:<br>
    · <strong>Material Symbols</strong> — 글리프 이름만. 예: <code>alternate_email</code>, <code>mail</code>, <code>chat</code>, <code>rss_feed</code> (<a href="https://fonts.google.com/icons" target="_blank">아이콘 찾기</a>)<br>
    · <strong>FontAwesome 6</strong> — fa- 클래스 포함. 예: <code>fab fa-twitter</code>, <code>fab fa-instagram</code>, <code>fab fa-x-twitter</code>, <code>fas fa-envelope</code> (<a href="https://fontawesome.com/icons" target="_blank">아이콘 찾기</a>)<br>
    · <strong>이메일</strong>을 등록하려면 URL 칸에 <code>mailto:you@example.com</code> 형식으로 입력하세요. 방문자가 아이콘을 클릭하면 메일 앱 대신 <strong>주소가 복사</strong>됩니다.<br>
    · 라벨은 비워둬도 돼요 — URL이나 아이콘에서 자동으로 채워집니다.
  </div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_links">

    <?php if ($links): ?>
      <div class="sortable-list">
        <?php foreach ($links as $l): ?>
          <div class="sortable-item">
            <span class="ic"><?= hp_icon($l['lk_icon']) ?></span>
            <input type="text" name="links[<?= $l['lk_id'] ?>][icon]"  value="<?= h($l['lk_icon']) ?>"  placeholder="icon"  style="width:160px">
            <input type="text" name="links[<?= $l['lk_id'] ?>][label]" value="<?= h($l['lk_label']) ?>" placeholder="라벨"  style="flex:1;min-width:120px">
            <input type="text" inputmode="url"  name="links[<?= $l['lk_id'] ?>][url]"   value="<?= h($l['lk_url']) ?>"   placeholder="https:// 또는 mailto:" style="flex:1.8;min-width:180px">
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h3>+ 새 링크 추가</h3>
    <div style="display:grid;grid-template-columns:160px 1fr 1.8fr;gap:8px">
      <input type="text" name="new_icon"  placeholder="alternate_email" class="adm-input">
      <input type="text" name="new_label" placeholder="라벨 (선택)" class="adm-input">
      <input type="text" inputmode="url"  name="new_url"   placeholder="https:// 또는 mailto:" class="adm-input">
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </form>

  <?php if ($links): ?>
    <div class="delete-chips">
      <span style="font-size:11px;color:var(--ink-mute);font-weight:600;align-self:center;margin-right:4px;">삭제 →</span>
      <?php foreach ($links as $l): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('[<?= h($l['lk_label']) ?>] 링크를 삭제할까요?')">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="delete_link">
          <input type="hidden" name="lk_id" value="<?= $l['lk_id'] ?>">
          <button type="submit" class="adm-btn adm-btn-sm adm-btn-danger"><?= h($l['lk_label']) ?> ✕</button>
        </form>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
