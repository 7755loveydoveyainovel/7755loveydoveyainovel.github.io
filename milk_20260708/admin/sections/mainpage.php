<?php
/**
 * admin/sections/mainpage.php — 메인 페이지 블록 관리
 *
 * 기능:
 *  - 활성 블록 목록 + 드래그 정렬 (Sortable.js)
 *  - 노출 on/off 토글 (AJAX)
 *  - 각 블록별 옵션 편집 (JSON)
 *  - 블록 추가 / 삭제
 */

$skin       = hp_config('main_skin', 'default');
$blocks_dir = HP_PATH . "/skins/main/{$skin}/blocks";

// 사용 가능한 메인 스킨 자동 탐지 (skins/main/* 폴더 중 index.php 가 있는 것)
$available_main_skins = [];
$main_skins_dir = HP_PATH . '/skins/main';
if (is_dir($main_skins_dir)) {
    foreach (scandir($main_skins_dir) as $d) {
        if ($d === '.' || $d === '..') continue;
        if (is_dir("$main_skins_dir/$d") && file_exists("$main_skins_dir/$d/index.php")) {
            $info_file = "$main_skins_dir/$d/info.php";
            $info = file_exists($info_file) ? @include $info_file : [];
            $available_main_skins[$d] = is_array($info) ? $info : [];
        }
    }
}

// 현재 스킨에서 사용 가능한 블록 타입 자동 탐지
$available_blocks = [];
if (is_dir($blocks_dir)) {
    foreach (scandir($blocks_dir) as $f) {
        if (preg_match('/^([a-z][a-z0-9_-]*)\.php$/', $f, $m)) {
            $available_blocks[] = $m[1];
        }
    }
}
sort($available_blocks);

// 블록 메타 (UI 표시용)
$block_meta = [
    'profile'     => ['프로필',    '아바타 + 인사 + 본인 배너 + 소셜 링크'],
    'recent'      => ['최근 글',   '최근 작성된 글 목록'],
    'guestbook'   => ['방명록',    '최근 방명록 미리보기'],
    'friends'     => ['지인 배너', '교환한 친구 배너 그리드'],
    'tags'        => ['태그',      '직접 작성하는 태그 클라우드'],
    'custom-html' => ['자유 HTML', '원하는 HTML 코드를 직접 입력'],
];

// ─── POST 처리 ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'reorder') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids)) {
            foreach ($ids as $i => $id) {
                qExec("UPDATE {main_block} SET mb_order = ? WHERE mb_id = ?", [(int)$i + 1, (int)$id]);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'change_skin') {
        $new_skin = trim($_POST['main_skin'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $new_skin)
            && is_dir(HP_PATH . "/skins/main/{$new_skin}")) {
            hp_config_set('main_skin', $new_skin);
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '메인 스킨이 변경되었습니다.'];
        } else {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '잘못된 스킨입니다.'];
        }
        header('Location: ' . HP_BASE . '/admin/?section=mainpage');
        exit;
    }

    if ($action === 'toggle_block') {
        $id = (int)($_POST['mb_id'] ?? 0);
        qExec("UPDATE {main_block} SET mb_is_active = 1 - mb_is_active WHERE mb_id = ?", [$id]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'add_block') {
        $type = $_POST['block_type'] ?? '';
        if (in_array($type, $available_blocks, true)) {
            $max_order = (int)qVal("SELECT COALESCE(MAX(mb_order), 0) FROM {main_block}");
            qInsert(
                "INSERT INTO {main_block} (mb_type, mb_order, mb_is_active, mb_options)
                 VALUES (?, ?, 1, '{}')",
                [$type, $max_order + 1]
            );
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '블록이 추가되었습니다.'];
        }
    }
    elseif ($action === 'delete_block') {
        $id = (int)($_POST['mb_id'] ?? 0);
        qExec("DELETE FROM {main_block} WHERE mb_id = ?", [$id]);
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '블록이 삭제되었습니다.'];
    }
    elseif ($action === 'save_options') {
        $id   = (int)($_POST['mb_id'] ?? 0);
        $json = $_POST['opts_json'] ?? '{}';
        $opts = json_decode($json, true);
        if (!is_array($opts)) {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'JSON 형식이 올바르지 않습니다.'];
        } else {
            qExec(
                "UPDATE {main_block} SET mb_options = ? WHERE mb_id = ?",
                [json_encode($opts, JSON_UNESCAPED_UNICODE), $id]
            );
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '옵션이 저장되었습니다.'];
        }
    }

    header('Location: ' . HP_BASE . '/admin/?section=mainpage');
    exit;
}

$blocks = qAll("SELECT * FROM {main_block} ORDER BY mb_order, mb_id");
?>

<div class="adm-section">
  <h2>메인 스킨</h2>
  <div class="sub">메인 페이지의 전체 레이아웃을 결정해요. 스킨마다 사용 가능한 블록 종류가 다를 수 있어요.</div>

  <form method="post" style="display:flex;gap:8px;align-items:center;">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="change_skin">
    <select name="main_skin" class="adm-input" style="flex:1">
      <?php foreach ($available_main_skins as $key => $info):
        $label = $info['kr_name'] ?? ($info['name'] ?? $key);
      ?>
        <option value="<?= h($key) ?>" <?= $skin === $key ? 'selected' : '' ?>>
          <?= h($label) ?> (<?= h($key) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="adm-btn adm-btn-primary">스킨 변경</button>
  </form>

  <?php
    $current_info = $available_main_skins[$skin] ?? [];
    if (!empty($current_info['description'])):
  ?>
    <div style="margin-top:14px;padding:12px 14px;background:var(--hair-soft);border-radius:8px;font-size:12px;color:var(--ink-soft);line-height:1.6;">
      <?= h($current_info['description']) ?>
    </div>
  <?php endif; ?>
</div>

<div class="adm-section">
  <h2>메인 페이지 블록</h2>
  <div class="sub">
    드래그 핸들로 순서를 바꿔보세요 (자동 저장). 노출 토글로 on/off, 옵션 버튼으로 각 블록의 세부 설정을 변경할 수 있어요.
    현재 스킨: <code><?= h($skin) ?></code>
  </div>

  <?php if (!$blocks): ?>
    <div style="padding:30px 20px;text-align:center;color:var(--ink-mute);font-size:13px;">
      아직 활성화된 블록이 없어요. 아래에서 추가해보세요.
    </div>
  <?php else: ?>
    <div class="sortable-list" id="blockList">
      <?php foreach ($blocks as $b):
        $meta = $block_meta[$b['mb_type']] ?? [$b['mb_type'], ''];
        $opts = json_decode($b['mb_options'] ?? '{}', true) ?: [];
      ?>
        <div class="sortable-item" data-id="<?= $b['mb_id'] ?>">
          <span class="handle">drag_indicator</span>
          <span class="ic">view_module</span>
          <div style="flex:1;min-width:120px">
            <div class="name"><?= h($meta[0]) ?></div>
            <div class="meta"><?= h($meta[1]) ?> · <code><?= h($b['mb_type']) ?></code></div>
          </div>
          <label class="toggle">
            <input type="checkbox" <?= $b['mb_is_active'] ? 'checked' : '' ?>
                   onchange="toggleBlock(<?= $b['mb_id'] ?>)">
            노출
          </label>
          <button type="button" class="adm-btn adm-btn-sm adm-btn-secondary"
                  onclick="toggleOptionsForm(<?= $b['mb_id'] ?>)">옵션</button>
          <form method="post" style="display:inline" onsubmit="return confirm('이 블록을 삭제할까요?')">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete_block">
            <input type="hidden" name="mb_id" value="<?= $b['mb_id'] ?>">
            <button type="submit" class="adm-btn adm-btn-sm adm-btn-danger">삭제</button>
          </form>
        </div>

        <div id="opts-<?= $b['mb_id'] ?>" class="opts-form" style="display:none">
          <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="save_options">
            <input type="hidden" name="mb_id" value="<?= $b['mb_id'] ?>">

            <div class="adm-field">
              <label>옵션 (JSON)</label>
              <span class="hint">예: <code>{"limit":6,"title":"최근 일기"}</code> — 블록마다 사용하는 옵션이 다릅니다. 자세한 건 README의 "메인 페이지 블록 옵션 가이드" 섹션 참고.</span>
              <textarea name="opts_json" class="code"><?= h(json_encode($opts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
            </div>

            <div class="adm-actions">
              <button type="submit" class="adm-btn adm-btn-sm adm-btn-primary">옵션 저장</button>
            </div>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h3>+ 새 블록 추가</h3>
  <form method="post" style="display:flex;gap:8px">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="add_block">
    <select name="block_type" class="adm-input" style="flex:1">
      <?php foreach ($available_blocks as $bt):
        $meta = $block_meta[$bt] ?? [$bt, ''];
      ?>
        <option value="<?= h($bt) ?>"><?= h($meta[0]) ?> (<?= h($bt) ?>)</option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="adm-btn adm-btn-primary">추가</button>
  </form>
</div>
