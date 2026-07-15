<?php
/**
 * admin/sections/menu.php — 메뉴 그룹 + 게시판 관리
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ─── 그룹 순서 변경 (D&D AJAX) ───
    if ($action === 'reorder_groups') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            foreach ($ids as $i => $id) {
                qExec("UPDATE {menu_group} SET mg_order = ? WHERE mg_id = ?", [(int)$i + 1, (int)$id]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // ─── 섹션 순서 변경 (D&D AJAX) ───
    if ($action === 'reorder_sections') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            foreach ($ids as $i => $id) {
                qExec("UPDATE {section} SET sec_order = ? WHERE sec_id = ?", [(int)$i + 1, (int)$id]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // ─── 섹션 저장 (전체 일괄 + 새 섹션 추가) ───
    if ($action === 'save_sections') {
        if (!empty($_POST['sections']) && is_array($_POST['sections'])) {
            foreach ($_POST['sections'] as $id => $s) {
                $id   = (int)$id;
                $name = trim($s['name'] ?? '');
                $icon = trim($s['icon'] ?? '');
                if ($id > 0 && $name !== '') {
                    qExec(
                        "UPDATE {section} SET sec_name = ?, sec_icon = ? WHERE sec_id = ?",
                        [mb_substr($name, 0, 40), mb_substr($icon, 0, 40), $id]
                    );
                }
            }
        }
        $new_name = trim($_POST['new_section_name'] ?? '');
        if ($new_name !== '') {
            $max_order = (int)qVal("SELECT COALESCE(MAX(sec_order), 0) FROM {section}");
            qInsert(
                "INSERT INTO {section} (sec_name, sec_icon, sec_order, sec_created_at) VALUES (?, ?, ?, NOW())",
                [
                    mb_substr($new_name, 0, 40),
                    mb_substr(trim($_POST['new_section_icon'] ?? ''), 0, 40),
                    $max_order + 1,
                ]
            );
        }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '섹션이 저장되었습니다.'];
        header('Location: ' . HP_BASE . '/admin/?section=menu');
        exit;
    }

    // ─── 섹션 삭제 ───
    if ($action === 'delete_section') {
        $id = (int)($_POST['sec_id'] ?? 0);
        if ($id > 0) {
            // 이 섹션을 쓰던 그룹의 mg_sec_id 를 NULL 로 (공용으로 이동)
            qExec("UPDATE {menu_group} SET mg_sec_id = NULL WHERE mg_sec_id = ?", [$id]);
            qExec("DELETE FROM {section} WHERE sec_id = ?", [$id]);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '섹션이 삭제되었습니다. (소속 그룹은 공용으로 이동)'];
        }
        header('Location: ' . HP_BASE . '/admin/?section=menu');
        exit;
    }

    // ─── 게시판 순서 변경 (D&D AJAX) ───
    if ($action === 'reorder_boards') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            foreach ($ids as $i => $id) {
                qExec("UPDATE {board} SET bo_order = ? WHERE bo_id = ?", [(int)$i + 1, (int)$id]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    // ─── 구분선 추가 ───
    if ($action === 'add_divider') {
        $max_order = (int)qVal("SELECT COALESCE(MAX(mg_order), 0) FROM {menu_group}");
        qInsert(
            "INSERT INTO {menu_group} (mg_name, mg_icon, mg_order, mg_is_divider) VALUES ('---', '', ?, 1)",
            [$max_order + 1]
        );
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '구분선이 추가되었습니다. 드래그로 위치를 옮기세요.'];
        header('Location: ' . HP_BASE . '/admin/?section=menu');
        exit;
    }

    // ─── 그룹 저장 ───
    if ($action === 'save_groups' || $action === 'save_boards' || $action === 'save_menu') {
        // 통합 저장 — 그룹 + 게시판 한 번에
        if (!empty($_POST['groups']) && is_array($_POST['groups'])) {
            foreach ($_POST['groups'] as $id => $g) {
                $id     = (int)$id;
                $name   = trim($g['name'] ?? '');
                $icon   = trim($g['icon'] ?? '');
                $sec_id = (int)($g['sec_id'] ?? 0);
                if ($id > 0 && $name !== '') {
                    qExec(
                        "UPDATE {menu_group} SET mg_name = ?, mg_icon = ?, mg_mobile_pinned = ?, mg_sec_id = ? WHERE mg_id = ?",
                        [
                            mb_substr($name, 0, 60),
                            mb_substr($icon, 0, 40),
                            isset($g['pinned']) ? 1 : 0,
                            $sec_id > 0 ? $sec_id : null,
                            $id,
                        ]
                    );
                }
            }
        }

        // 기존 게시판 업데이트
        if (!empty($_POST['boards']) && is_array($_POST['boards'])) {
            foreach ($_POST['boards'] as $id => $b) {
                $id   = (int)$id;
                if ($id <= 0) continue;
                $name = trim($b['name'] ?? '');
                $slug = trim($b['slug'] ?? '');
                $icon = trim($b['icon'] ?? '');
                $skin = trim($b['skin'] ?? 'list');
                $mg   = ((int)($b['mg_id'] ?? 0)) ?: null;
                $pin  = isset($b['pinned']) ? 1 : 0;
                if ($name === '' || $slug === '') continue;
                if (!preg_match('/^[a-z0-9-]+$/', $slug)) continue;

                // 잠금 모드: open / admin / password
                $lock_mode   = $b['lock_mode'] ?? 'open';
                $admin_only  = ($lock_mode === 'admin') ? 1 : 0;
                $new_pw      = trim($b['password'] ?? '');
                // password 모드: 새 비밀번호 입력됐으면 hash, 입력 안 됐으면 기존 유지
                // open/admin 모드로 변경됐으면 비밀번호 삭제
                if ($lock_mode === 'password') {
                    if ($new_pw !== '') {
                        $pw_hash = password_hash($new_pw, PASSWORD_DEFAULT);
                    } else {
                        // 기존 비밀번호 유지 — UPDATE 에서 bo_password 건드리지 않기 위해 별도 처리
                        $pw_hash = '__KEEP__';
                    }
                } else {
                    $pw_hash = null;  // open/admin 이면 비밀번호 삭제
                }

                if ($pw_hash === '__KEEP__') {
                    qExec(
                        "UPDATE {board}
                         SET bo_name = ?, bo_slug = ?, bo_icon = ?, bo_skin = ?,
                             bo_mg_id = ?, bo_mobile_pinned = ?, bo_admin_only = ?,
                             bo_categories = ?
                         WHERE bo_id = ?",
                        [
                            mb_substr($name, 0, 60),
                            mb_substr($slug, 0, 60),
                            mb_substr($icon, 0, 40),
                            mb_substr($skin, 0, 40),
                            $mg, $pin, $admin_only,
                            trim($b['categories'] ?? '') ?: null,
                            $id,
                        ]
                    );
                } else {
                    qExec(
                        "UPDATE {board}
                         SET bo_name = ?, bo_slug = ?, bo_icon = ?, bo_skin = ?,
                             bo_mg_id = ?, bo_mobile_pinned = ?, bo_admin_only = ?,
                             bo_categories = ?, bo_password = ?
                         WHERE bo_id = ?",
                        [
                            mb_substr($name, 0, 60),
                            mb_substr($slug, 0, 60),
                            mb_substr($icon, 0, 40),
                            mb_substr($skin, 0, 40),
                            $mg, $pin, $admin_only,
                            trim($b['categories'] ?? '') ?: null,
                            $pw_hash,
                            $id,
                        ]
                    );
                }
            }
        }

        // 신규 게시판 추가 — 그룹별로 (new_boards[group_id][name/slug/icon/skin])
        // group_id = 0 → 그룹 미지정
        if (!empty($_POST['new_boards']) && is_array($_POST['new_boards'])) {
            foreach ($_POST['new_boards'] as $gid => $nb) {
                $gid       = (int)$gid;
                $new_name  = trim($nb['name'] ?? '');
                $new_slug  = trim($nb['slug'] ?? '');
                if ($new_name === '' && $new_slug === '') continue;  // 빈 행 skip
                if ($new_name === '' || $new_slug === '' || !preg_match('/^[a-z0-9-]+$/', $new_slug)) {
                    $_SESSION['_flash'] = ['type' => 'error', 'msg' => '게시판 추가 실패 — 이름/slug 입력 또는 형식 확인 (영문 소문자/숫자/하이픈)'];
                    continue;
                }
                $max_order = (int)qVal("SELECT COALESCE(MAX(bo_order), 0) FROM {board}");
                try {
                    qInsert(
                        "INSERT INTO {board}
                            (bo_name, bo_slug, bo_icon, bo_skin, bo_mg_id, bo_order, bo_created_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [
                            mb_substr($new_name, 0, 60),
                            mb_substr($new_slug, 0, 60),
                            mb_substr(trim($nb['icon'] ?? ''), 0, 40),
                            mb_substr(trim($nb['skin'] ?? 'list'), 0, 40),
                            $gid > 0 ? $gid : null,
                            $max_order + 1,
                        ]
                    );
                } catch (Exception $e) {
                    $_SESSION['_flash'] = ['type' => 'error', 'msg' => '게시판 추가 실패 — slug 가 중복되었거나 형식이 올바르지 않습니다.'];
                }
            }
        }

        // 신규 그룹 추가
        $new_group_name = trim($_POST['new_group_name'] ?? '');
        if ($new_group_name !== '') {
            $max_order = (int)qVal("SELECT COALESCE(MAX(mg_order), 0) FROM {menu_group}");
            qInsert(
                "INSERT INTO {menu_group} (mg_name, mg_icon, mg_order, mg_sec_id) VALUES (?, ?, ?, ?)",
                [
                    mb_substr($new_group_name, 0, 60),
                    mb_substr(trim($_POST['new_group_icon'] ?? ''), 0, 40),
                    $max_order + 1,
                    ((int)($_POST['new_group_sec_id'] ?? 0)) ?: null,
                ]
            );
        }

        if (empty($_SESSION['_flash'])) {
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '메뉴가 저장되었습니다.'];
        }
    }

    // ─── 게시판 삭제 ───
    elseif ($action === 'delete_board') {
        $id = (int)($_POST['bo_id'] ?? 0);
        qExec("DELETE FROM {board} WHERE bo_id = ?", [$id]);
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '게시판이 삭제되었습니다.'];
    }

    // ─── 그룹 삭제 ───
    elseif ($action === 'delete_group') {
        $id = (int)($_POST['mg_id'] ?? 0);
        qExec("DELETE FROM {menu_group} WHERE mg_id = ?", [$id]);
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '그룹이 삭제되었습니다. (소속 게시판은 그룹 미지정으로 이동)'];
    }

    elseif ($action === 'save_guestbook') {
        $was_enabled = hp_config('guestbook_enabled', '0') === '1';
        $now_enabled = !empty($_POST['guestbook_enabled']);
        hp_config_set('guestbook_enabled', $now_enabled ? '1' : '0');

        // 스킨 저장
        if (isset($_POST['guestbook_skin'])) {
            $sk = trim($_POST['guestbook_skin']);
            if (preg_match('/^[a-z][a-z0-9_-]*$/', $sk) && is_dir(HP_PATH . "/skins/board/{$sk}")) {
                hp_config_set('guestbook_skin', $sk);
            }
        }

        // 처음 켤 때 → 메뉴에 자동으로 방명록 링크 행 추가
        if ($now_enabled && !$was_enabled) {
            $exists = qVal("SELECT COUNT(*) FROM {menu_group} WHERE mg_special_link = 'guestbook'");
            if (!$exists) {
                $max_order = (int)qVal("SELECT COALESCE(MAX(mg_order), 0) FROM {menu_group}");
                qInsert(
                    "INSERT INTO {menu_group} (mg_name, mg_icon, mg_order, mg_special_link) VALUES (?, ?, ?, 'guestbook')",
                    ['방명록', 'edit_note', $max_order + 1]
                );
            }
        }

        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '방명록 설정이 저장되었습니다.'];
    }

    header('Location: ' . HP_BASE . '/admin/?section=menu');
    exit;
}

$groups = qAll("SELECT * FROM {menu_group} ORDER BY mg_order, mg_id");
// 내부 전용 게시판 (slug 가 _ 로 시작) 은 메뉴 관리에서도 숨김 — 사용자가 실수로 만지지 않게
$boards = qAll("SELECT * FROM {board} WHERE bo_slug NOT LIKE '\\_%' ESCAPE '\\\\' ORDER BY bo_order, bo_id");
try {
    $sections = qAll("SELECT * FROM {section} ORDER BY sec_order, sec_id");
} catch (Throwable $e) {
    $sections = [];
}

// 사용 가능한 게시판 스킨 자동 탐지
$available_skins = [];
$board_skins_dir = HP_PATH . '/skins/board';
if (is_dir($board_skins_dir)) {
    foreach (scandir($board_skins_dir) as $d) {
        if ($d === '.' || $d === '..') continue;
        if (is_dir("$board_skins_dir/$d") && file_exists("$board_skins_dir/$d/index.php")) {
            $available_skins[] = $d;
        }
    }
}
if (!$available_skins) $available_skins = ['list'];
?>

<!-- 섹션 -->
<div class="adm-section">
  <h2>섹션</h2>
  <div class="sub">
    그룹을 큰 덩어리로 묶는 단위. 예를 들어 "일상" / "취미" 처럼 만들면 사이드바·모바일 탭바 상단에 토글이 생겨서, 한 섹션의 그룹만 골라볼 수 있어요.<br>
    섹션이 0개면 토글 자체가 안 뜨고 모든 그룹이 항상 보입니다. 섹션이 있으면 첫 섹션이 기본으로 선택돼요.<br>
    그룹의 섹션을 <strong>공용</strong>으로 두면 어떤 섹션을 골라도 항상 보입니다 (홈·방명록 같은 공통 메뉴에 적합).
  </div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_sections">

    <?php if ($sections): ?>
      <div class="menu-tree sortable-list" data-reorder-section="menu" data-reorder-action="reorder_sections">
        <?php foreach ($sections as $sec): ?>
          <div class="group-row sortable-item" data-id="<?= $sec['sec_id'] ?>">
            <div class="group-header section-header">
              <span class="handle">drag_indicator</span>
              <span class="ic"><?= hp_icon($sec['sec_icon'] ?: 'folder') ?></span>
              <input type="text" name="sections[<?= $sec['sec_id'] ?>][name]" value="<?= h($sec['sec_name']) ?>" placeholder="섹션 이름">
              <input type="text" name="sections[<?= $sec['sec_id'] ?>][icon]" value="<?= h($sec['sec_icon']) ?>" placeholder="아이콘 (folder · fas fa-star)">
              <button type="button" class="adm-btn adm-btn-sm adm-btn-danger group-del-btn"
                      title="섹션 삭제"
                      onclick="if(confirm('섹션 [<?= h(addslashes($sec['sec_name'])) ?>] 을 삭제할까요?\n(소속 그룹들은 공용으로 이동됩니다)'))document.getElementById('del-sec-<?= $sec['sec_id'] ?>').submit()">✕</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="menu-tree-add">
      <h3>+ 새 섹션 추가</h3>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" name="new_section_name" placeholder="섹션 이름 (예: 일상)" class="adm-input" style="flex:1.5;min-width:160px">
        <input type="text" name="new_section_icon" placeholder="아이콘 (folder · fas fa-star · 선택)" class="adm-input" style="flex:1.2;min-width:160px">
      </div>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </form>

  <?php foreach ($sections as $sec): ?>
    <form id="del-sec-<?= $sec['sec_id'] ?>" method="post" style="display:none">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="delete_section">
      <input type="hidden" name="sec_id" value="<?= $sec['sec_id'] ?>">
    </form>
  <?php endforeach; ?>
</div>

<!-- 메뉴 (그룹 + 게시판 통합 트리) -->
<div class="adm-section">
  <h2>메뉴</h2>
  <div class="sub">
    그룹 → 게시판 트리. 그룹마다 섹션을 지정하고 (공용은 모든 섹션에서 표시), 그룹 안에 게시판을 인라인으로 추가·편집해요.<br>
    <strong>그룹 행</strong>은 드래그(≡)로 순서 변경. <strong>게시판 행</strong>은 같은 그룹 안에서만 드래그 이동 (다른 그룹으로 옮기려면 게시판의 그룹 드롭다운으로).<br>
    아래 [저장] 버튼 한 번 누르면 모든 변경사항(그룹+게시판+신규 추가)이 한 번에 저장됩니다.
  </div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_menu">

    <div class="menu-tree sortable-list" data-reorder-section="menu" data-reorder-action="reorder_groups">
      <?php
      // 그룹별로 트리 렌더 + 마지막에 가짜 "기타" 그룹 (그룹 미지정 게시판들)
      $boards_by_group = [];
      foreach ($boards as $b) {
          $gid = (int)($b['bo_mg_id'] ?? 0);
          $boards_by_group[$gid][] = $b;
      }
      ?>

      <?php foreach ($groups as $g): ?>
        <?php if ($g['mg_is_divider'] ?? 0): ?>
          <!-- 구분선 그룹 -->
          <div class="group-row group-row--divider sortable-item" data-id="<?= $g['mg_id'] ?>">
            <div class="group-header">
              <span class="handle">drag_indicator</span>
              <span class="divider-label">─────  구분선  ─────</span>
              <button type="button" class="adm-btn adm-btn-sm adm-btn-danger"
                      onclick="if(confirm('이 구분선을 삭제할까요?'))document.getElementById('del-grp-<?= $g['mg_id'] ?>').submit()">삭제</button>
            </div>
          </div>
        <?php else: ?>
          <!-- 일반 그룹 또는 special_link 그룹 -->
          <div class="group-row sortable-item" data-id="<?= $g['mg_id'] ?>">
            <div class="group-header">
              <span class="handle">drag_indicator</span>
              <span class="ic"><?= hp_icon($g['mg_icon'] ?: ($g['mg_special_link'] ? 'edit_note' : 'folder')) ?></span>
              <input type="text" name="groups[<?= $g['mg_id'] ?>][name]" value="<?= h($g['mg_name']) ?>" placeholder="그룹 이름">
              <input type="text" name="groups[<?= $g['mg_id'] ?>][icon]" value="<?= h($g['mg_icon']) ?>" placeholder="folder">
              <select name="groups[<?= $g['mg_id'] ?>][sec_id]" title="섹션">
                <option value="0">공용</option>
                <?php foreach ($sections as $sec): ?>
                  <option value="<?= $sec['sec_id'] ?>" <?= ($g['mg_sec_id'] ?? 0) == $sec['sec_id'] ? 'selected' : '' ?>><?= h($sec['sec_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <label class="toggle" title="모바일 핀">
                <input type="checkbox" name="groups[<?= $g['mg_id'] ?>][pinned]" value="1" <?= !empty($g['mg_mobile_pinned']) ? 'checked' : '' ?>>
                <i class="fas fa-thumbtack"></i>
              </label>
              <button type="button" class="adm-btn adm-btn-sm adm-btn-danger group-del-btn"
                      title="그룹 삭제"
                      onclick="if(confirm('그룹 [<?= h(addslashes($g['mg_name'])) ?>] 을 삭제할까요?\n(소속 게시판은 그룹 미지정으로 이동)'))document.getElementById('del-grp-<?= $g['mg_id'] ?>').submit()">✕</button>
            </div>

            <?php if (empty($g['mg_special_link'])): ?>
              <div class="group-boards sortable-list" data-reorder-section="menu" data-reorder-action="reorder_boards">
                <?php
                $gid_boards = $boards_by_group[$g['mg_id']] ?? [];
                foreach ($gid_boards as $b):
                ?>
                  <div class="board-item sortable-item" data-id="<?= $b['bo_id'] ?>">
                    <span class="handle">drag_indicator</span>
                    <span class="ic"><?= hp_icon($b['bo_icon'] ?: 'article') ?></span>
                    <input type="text" name="boards[<?= $b['bo_id'] ?>][name]" value="<?= h($b['bo_name']) ?>" placeholder="이름">
                    <input type="text" name="boards[<?= $b['bo_id'] ?>][slug]" value="<?= h($b['bo_slug']) ?>" placeholder="slug">
                    <input type="text" name="boards[<?= $b['bo_id'] ?>][icon]" value="<?= h($b['bo_icon']) ?>" placeholder="icon">
                    <select name="boards[<?= $b['bo_id'] ?>][skin]">
                      <?php foreach ($available_skins as $sk): ?>
                        <option value="<?= h($sk) ?>" <?= $b['bo_skin'] === $sk ? 'selected' : '' ?>><?= h($sk) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <select name="boards[<?= $b['bo_id'] ?>][mg_id]" title="이 게시판이 속할 그룹">
                      <option value="">미지정</option>
                      <?php foreach ($groups as $g2):
                        if (($g2['mg_is_divider'] ?? 0) || !empty($g2['mg_special_link'])) continue;
                      ?>
                        <option value="<?= $g2['mg_id'] ?>" <?= $b['bo_mg_id'] == $g2['mg_id'] ? 'selected' : '' ?>><?= h($g2['mg_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <label class="toggle" title="모바일 핀">
                      <input type="checkbox" name="boards[<?= $b['bo_id'] ?>][pinned]" value="1" <?= $b['bo_mobile_pinned'] ? 'checked' : '' ?>>
                      <i class="fas fa-thumbtack"></i>
                    </label>
                    <?php
                      $lock_mode = 'open';
                      if (!empty($b['bo_admin_only'])) $lock_mode = 'admin';
                      elseif (!empty($b['bo_password'])) $lock_mode = 'password';
                    ?>
                    <div class="board-meta-row">
                      <div class="lock-pills">
                        <label class="lock-pill <?= $lock_mode === 'open' ? 'is-on' : '' ?>" title="공개">
                          <input type="radio" name="boards[<?= $b['bo_id'] ?>][lock_mode]" value="open" <?= $lock_mode === 'open' ? 'checked' : '' ?>>
                          <i class="fas fa-lock-open"></i>
                        </label>
                        <label class="lock-pill <?= $lock_mode === 'admin' ? 'is-on' : '' ?>" title="관리자 전용">
                          <input type="radio" name="boards[<?= $b['bo_id'] ?>][lock_mode]" value="admin" <?= $lock_mode === 'admin' ? 'checked' : '' ?>>
                          <i class="fas fa-lock"></i>
                        </label>
                        <label class="lock-pill <?= $lock_mode === 'password' ? 'is-on' : '' ?>" title="비밀번호">
                          <input type="radio" name="boards[<?= $b['bo_id'] ?>][lock_mode]" value="password" <?= $lock_mode === 'password' ? 'checked' : '' ?>>
                          <i class="fas fa-key"></i>
                        </label>
                      </div>
                      <input type="text" class="board-password"
                             name="boards[<?= $b['bo_id'] ?>][password]"
                             value=""
                             placeholder="<?= !empty($b['bo_password']) ? '설정됨 · 변경하려면 입력' : '비밀번호' ?>"
                             style="<?= $lock_mode !== 'password' ? 'display:none' : '' ?>">
                      <input type="text" class="board-categories"
                             name="boards[<?= $b['bo_id'] ?>][categories]"
                             value="<?= h($b['bo_categories'] ?? '') ?>"
                             placeholder="카테고리 (쉼표 구분 · 비워두면 사용 안 함)">
                    </div>
                    <button type="button" class="adm-btn adm-btn-sm adm-btn-danger board-del-btn"
                            title="게시판 삭제"
                            onclick="if(confirm('게시판 [<?= h(addslashes($b['bo_name'])) ?>] 을 삭제할까요?\n모든 글과 댓글이 함께 삭제됩니다.'))document.getElementById('del-bd-<?= $b['bo_id'] ?>').submit()">✕</button>
                  </div>
                <?php endforeach; ?>

                <!-- 신규 게시판 인라인 추가 행 -->
                <div class="board-item board-item--new">
                  <input type="text" name="new_boards[<?= $g['mg_id'] ?>][name]" placeholder="새 게시판 이름">
                  <input type="text" name="new_boards[<?= $g['mg_id'] ?>][slug]" placeholder="slug (영문, 예: diary)">
                  <input type="text" name="new_boards[<?= $g['mg_id'] ?>][icon]" placeholder="아이콘 (선택)">
                  <select name="new_boards[<?= $g['mg_id'] ?>][skin]">
                    <?php foreach ($available_skins as $sk): ?>
                      <option value="<?= h($sk) ?>"><?= h($sk) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="adm-btn adm-btn-sm adm-btn-primary new-board-btn">+ 추가</button>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>

      <!-- 가짜 "기타" 그룹 — 그룹 미지정 게시판들 -->
      <?php $ungrouped = $boards_by_group[0] ?? []; ?>
      <div class="group-row group-row--ungrouped">
        <div class="group-header">
          <span class="ic"><i class="fas fa-folder-open"></i></span>
          <strong>기타 (그룹 미지정)</strong>
          <span class="muted">그룹에 속하지 않은 게시판들. 사이드바에선 "기타" 섹션으로 표시됩니다.</span>
        </div>
        <div class="group-boards sortable-list" data-reorder-section="menu" data-reorder-action="reorder_boards">
          <?php foreach ($ungrouped as $b): ?>
            <div class="board-item sortable-item" data-id="<?= $b['bo_id'] ?>">
              <span class="handle">drag_indicator</span>
              <span class="ic"><?= hp_icon($b['bo_icon'] ?: 'article') ?></span>
              <input type="text" name="boards[<?= $b['bo_id'] ?>][name]" value="<?= h($b['bo_name']) ?>" placeholder="이름">
              <input type="text" name="boards[<?= $b['bo_id'] ?>][slug]" value="<?= h($b['bo_slug']) ?>" placeholder="slug">
              <input type="text" name="boards[<?= $b['bo_id'] ?>][icon]" value="<?= h($b['bo_icon']) ?>" placeholder="icon">
              <select name="boards[<?= $b['bo_id'] ?>][skin]">
                <?php foreach ($available_skins as $sk): ?>
                  <option value="<?= h($sk) ?>" <?= $b['bo_skin'] === $sk ? 'selected' : '' ?>><?= h($sk) ?></option>
                <?php endforeach; ?>
              </select>
              <select name="boards[<?= $b['bo_id'] ?>][mg_id]" title="그룹">
                <option value="" selected>미지정</option>
                <?php foreach ($groups as $g2):
                  if (($g2['mg_is_divider'] ?? 0) || !empty($g2['mg_special_link'])) continue;
                ?>
                  <option value="<?= $g2['mg_id'] ?>"><?= h($g2['mg_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <label class="toggle" title="모바일 핀">
                <input type="checkbox" name="boards[<?= $b['bo_id'] ?>][pinned]" value="1" <?= $b['bo_mobile_pinned'] ? 'checked' : '' ?>>
                <i class="fas fa-thumbtack"></i>
              </label>
              <?php
                $lock_mode = 'open';
                if (!empty($b['bo_admin_only'])) $lock_mode = 'admin';
                elseif (!empty($b['bo_password'])) $lock_mode = 'password';
              ?>
              <div class="board-meta-row">
                <div class="lock-pills">
                  <label class="lock-pill <?= $lock_mode === 'open' ? 'is-on' : '' ?>" title="공개">
                    <input type="radio" name="boards[<?= $b['bo_id'] ?>][lock_mode]" value="open" <?= $lock_mode === 'open' ? 'checked' : '' ?>>
                    <i class="fas fa-lock-open"></i>
                  </label>
                  <label class="lock-pill <?= $lock_mode === 'admin' ? 'is-on' : '' ?>" title="관리자 전용">
                    <input type="radio" name="boards[<?= $b['bo_id'] ?>][lock_mode]" value="admin" <?= $lock_mode === 'admin' ? 'checked' : '' ?>>
                    <i class="fas fa-lock"></i>
                  </label>
                  <label class="lock-pill <?= $lock_mode === 'password' ? 'is-on' : '' ?>" title="비밀번호">
                    <input type="radio" name="boards[<?= $b['bo_id'] ?>][lock_mode]" value="password" <?= $lock_mode === 'password' ? 'checked' : '' ?>>
                    <i class="fas fa-key"></i>
                  </label>
                </div>
                <input type="text" class="board-password"
                       name="boards[<?= $b['bo_id'] ?>][password]"
                       value=""
                       placeholder="<?= !empty($b['bo_password']) ? '설정됨 · 변경하려면 입력' : '비밀번호' ?>"
                       style="<?= $lock_mode !== 'password' ? 'display:none' : '' ?>">
                <input type="text" class="board-categories"
                       name="boards[<?= $b['bo_id'] ?>][categories]"
                       value="<?= h($b['bo_categories'] ?? '') ?>"
                       placeholder="카테고리 (쉼표 구분 · 비워두면 사용 안 함)">
              </div>
              <button type="button" class="adm-btn adm-btn-sm adm-btn-danger board-del-btn"
                      title="게시판 삭제"
                      onclick="if(confirm('게시판 [<?= h(addslashes($b['bo_name'])) ?>] 을 삭제할까요?\n모든 글과 댓글이 함께 삭제됩니다.'))document.getElementById('del-bd-<?= $b['bo_id'] ?>').submit()">✕</button>
            </div>
          <?php endforeach; ?>

          <div class="board-item board-item--new">
            <input type="text" name="new_boards[0][name]" placeholder="새 게시판 이름">
            <input type="text" name="new_boards[0][slug]" placeholder="slug (영문, 예: misc)">
            <input type="text" name="new_boards[0][icon]" placeholder="아이콘 (선택)">
            <select name="new_boards[0][skin]">
              <?php foreach ($available_skins as $sk): ?>
                <option value="<?= h($sk) ?>"><?= h($sk) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="adm-btn adm-btn-sm adm-btn-primary new-board-btn">+ 추가</button>
          </div>
        </div>
      </div>
    </div>

    <!-- 신규 그룹 + 구분선 -->
    <div class="menu-tree-add">
      <h3>+ 새 그룹 추가</h3>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="text" name="new_group_name" placeholder="그룹 이름" class="adm-input">
        <input type="text" name="new_group_icon" placeholder="아이콘 (folder · fas fa-star 등)" class="adm-input">
        <select name="new_group_sec_id" class="adm-input">
          <option value="0">공용</option>
          <?php foreach ($sections as $sec): ?>
            <option value="<?= $sec['sec_id'] ?>"><?= h($sec['sec_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">전체 저장</button>
    </div>
  </form>

  <!-- 구분선 추가 (별도) -->
  <form method="post" style="margin-top:8px;">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="add_divider">
    <button type="submit" class="adm-btn adm-btn-secondary adm-btn-sm">+ 구분선 추가</button>
  </form>

  <!-- 게시판 / 그룹 삭제용 숨김 폼들 (메인 form 밖에 있어야 nested form 안 됨) -->
  <?php foreach ($boards as $b): ?>
    <form method="post" id="del-bd-<?= $b['bo_id'] ?>" style="display:none">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="delete_board">
      <input type="hidden" name="bo_id" value="<?= $b['bo_id'] ?>">
    </form>
  <?php endforeach; ?>
  <?php foreach ($groups as $g): ?>
    <form method="post" id="del-grp-<?= $g['mg_id'] ?>" style="display:none">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="delete_group">
      <input type="hidden" name="mg_id" value="<?= $g['mg_id'] ?>">
    </form>
  <?php endforeach; ?>
</div>

<!-- 방명록 -->
<?php
// 사용 가능한 방명록 스킨 스캔 — info.php 의 'special' 키가 'guestbook' 인 폴더만
$gb_skins = [];
foreach (glob(HP_PATH . '/skins/board/*/info.php') ?: [] as $info_file) {
    $info = @include $info_file;
    if (is_array($info) && (($info['special'] ?? '') === 'guestbook')) {
        $slug = basename(dirname($info_file));
        $gb_skins[$slug] = $info;
    }
}
$current_gb_skin = hp_config('guestbook_skin', 'guestbook');
?>
<div class="adm-section">
  <h2>방명록</h2>
  <div class="sub">
    방명록은 별도의 데이터 모델을 사용하는 특수 게시판이에요. 활성화하면 사이드바 메뉴에 자동으로 "방명록" 항목이 추가됩니다 (위쪽의 메뉴 그룹 영역에서 다른 항목들과 섞어 위치 조정 가능).
    방문자는 비밀번호를 설정해 비공개 글을 남길 수 있고, 비공개 글의 답글은 관리자와 작성자(비밀번호 인증)만 작성할 수 있어요.
  </div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_guestbook">

    <div class="adm-field">
      <label class="check-row">
        <input type="checkbox" name="guestbook_enabled" value="1" <?= hp_config('guestbook_enabled') === '1' ? 'checked' : '' ?>>
        <span><strong>방명록 사용</strong></span>
      </label>
    </div>

    <div class="adm-field">
      <label>방명록 스킨</label>
      <span class="hint">
        새 스킨은 <code>skins/board/{이름}/</code> 폴더에 <code>index.php</code>·<code>style.css</code>·<code>info.php</code> 를 두고 info 에 <code>'special' =&gt; 'guestbook'</code> 키를 추가하면 자동으로 목록에 나타나요.
      </span>
      <select name="guestbook_skin">
        <?php foreach ($gb_skins as $slug => $info): ?>
          <option value="<?= h($slug) ?>" <?= $current_gb_skin === $slug ? 'selected' : '' ?>>
            <?= h($info['kr_name'] ?? $info['name'] ?? $slug) ?>
          </option>
        <?php endforeach; ?>
        <?php if (!$gb_skins): ?>
          <option value="guestbook" selected>방명록 (기본)</option>
        <?php endif; ?>
      </select>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </form>
</div>

<script>
// 잠금 모드 라디오 변경 시 비밀번호 입력란 토글 + pill 활성 상태
document.addEventListener('change', function (e) {
  var radio = e.target;
  if (radio.type !== 'radio' || !radio.closest('.lock-pills')) return;
  var pills = radio.closest('.lock-pills');
  pills.querySelectorAll('.lock-pill').forEach(function (p) {
    p.classList.toggle('is-on', p.querySelector('input').checked);
  });
  var item = radio.closest('.board-item');
  if (!item) return;
  var pwInput = item.querySelector('.board-password');
  if (pwInput) pwInput.style.display = (radio.value === 'password') ? '' : 'none';
});
</script>
