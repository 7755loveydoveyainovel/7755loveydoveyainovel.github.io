<?php
/**
 * admin/sections/design.php — 테마 / 폰트 / 커스텀 색상 / 커스텀 CSS
 *
 * 두 가지 모드:
 *  - 프리셋 모드: 4종 중 하나 선택. theme_overrides 비움.
 *  - 커스텀 색상 모드: 12개 CSS 변수를 직접 색상 선택. theme_overrides 에 JSON 저장.
 *
 * theme.php 의 hp_theme_vars() 가 base + overrides 병합.
 * overrides 가 비어있으면 순수 프리셋, 채워져 있으면 그 색이 사용됨.
 */

/** 업로드 파일의 실제 MIME 이 이미지인지 검증 — polyglot(이미지+PHP) 차단 */
function _design_mime_ok($tmp_path) {
    if (!function_exists('finfo_open')) return true;  // finfo 없으면 통과
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) return true;
    $mime = @finfo_file($finfo, $tmp_path);
    finfo_close($finfo);
    if (!$mime) return true;
    return in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_preset') {
        $theme = $_POST['theme'] ?? 'paper';
        if (in_array($theme, ['paper', 'linen', 'ink', 'midnight'], true)) {
            hp_config_set('theme_preset', $theme);
            // 프리셋을 새로 고르면 커스텀 오버라이드는 비움
            hp_config_set('theme_overrides', '');
        }
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '프리셋이 적용되었습니다.'];
    }

    elseif ($action === 'save_custom_colors') {
        $vars = [
            '--bg', '--paper', '--paper-2',
            '--ink', '--ink-soft', '--ink-mute',
            '--hair', '--hair-soft',
            '--accent', '--accent-soft', '--accent-2',
        ];
        $overrides = [];
        foreach ($vars as $v) {
            $val = $_POST['colors'][$v] ?? '';
            // #RRGGBB 형식 검증
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                $overrides[$v] = strtolower($val);
            }
        }
        hp_config_set('theme_overrides', json_encode($overrides, JSON_UNESCAPED_SLASHES));
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '커스텀 색상이 저장되었습니다.'];
    }

    elseif ($action === 'reset_custom_colors') {
        hp_config_set('theme_overrides', '');
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '커스텀 색상이 초기화되었습니다. 프리셋 색이 사용됩니다.'];
    }

    elseif ($action === 'save_font') {
        $url_input = trim($_POST['font_import_url'] ?? '');
        if ($url_input !== '' && sanitize_css_url($url_input) === '') {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => '폰트 URL은 https:// 로 시작하고 허용된 폰트 CDN(Google Fonts, jsdelivr, Bunny Fonts, Adobe Fonts, 웹폰트월드 등)이어야 합니다.'];
        } else {
            hp_config_set('font_import_url',     $url_input);
            hp_config_set('font_display_family', trim($_POST['font_display_family'] ?? ''));
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '폰트 설정이 저장되었습니다.'];
        }
    }

    elseif ($action === 'save_layout') {
        hp_config_set('show_site_name', !empty($_POST['show_site_name']) ? '1' : '0');
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '레이아웃 옵션이 저장되었습니다.'];
    }

    elseif ($action === 'save_custom_css') {
        $css  = $_POST['custom_css'] ?? '';
        $path = HP_PATH . '/data/custom.css';
        if (@file_put_contents($path, $css) !== false) {
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '커스텀 CSS가 저장되었습니다.'];
        } else {
            $_SESSION['_flash'] = ['type' => 'error', 'msg' => 'data/ 폴더 쓰기 권한을 확인해주세요.'];
        }
    }

    elseif ($action === 'save_background') {
        // URL 우선, 비어있으면 업로드 처리
        $url = trim($_POST['bg_image_url'] ?? '');
        $existing = hp_config('background_image', '');

        if ($url !== '') {
            if (preg_match('#^https?://#i', $url)) {
                // 기존 로컬 파일 정리
                if ($existing && !preg_match('#^https?://#i', $existing)) {
                    @unlink(HP_PATH . '/data/uploads/' . $existing);
                }
                hp_config_set('background_image', $url);
            } else {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '이미지 URL은 http:// 또는 https:// 로 시작해야 합니다.'];
            }
        } elseif (!empty($_FILES['bg_image_file']) && $_FILES['bg_image_file']['error'] === UPLOAD_ERR_OK) {
            $f   = $_FILES['bg_image_file'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '지원 형식: JPG, PNG, WebP, GIF'];
            } elseif ($f['size'] > 5 * 1024 * 1024) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '파일 크기가 5MB 를 초과합니다.'];
            } elseif (!@getimagesize($f['tmp_name'])) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '이미지 파일이 손상되었습니다.'];
            } elseif (!_design_mime_ok($f['tmp_name'])) {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '이미지가 아닌 파일입니다.'];
            } else {
                $name = 'bg_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $dir  = HP_PATH . '/data/uploads';
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (move_uploaded_file($f['tmp_name'], "$dir/$name")) {
                    if ($existing && !preg_match('#^https?://#i', $existing)) {
                        @unlink("$dir/$existing");
                    }
                    hp_config_set('background_image', $name);
                } else {
                    $_SESSION['_flash'] = ['type' => 'error', 'msg' => '파일 저장 실패. data/uploads/ 권한을 확인해주세요.'];
                }
            }
        }

        // 옵션 저장
        $size = $_POST['bg_size'] ?? 'cover';
        $att  = $_POST['bg_attachment'] ?? 'fixed';
        $rep  = $_POST['bg_repeat'] ?? 'no-repeat';
        $pos  = $_POST['bg_position'] ?? 'center center';
        $ovr  = max(0, min(100, (int)($_POST['bg_overlay'] ?? 0)));
        $blr  = max(0, min(40,  (int)($_POST['bg_blur']    ?? 0)));

        if (in_array($size, ['cover', 'contain', 'auto'], true))                hp_config_set('background_size', $size);
        if (in_array($att, ['fixed', 'scroll'], true))                          hp_config_set('background_attachment', $att);
        if (in_array($rep, ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'], true)) hp_config_set('background_repeat', $rep);
        hp_config_set('background_position', $pos);
        hp_config_set('background_overlay', (string)$ovr);
        hp_config_set('background_blur',    (string)$blr);

        // 배경 저장과 함께 팔레트도 저장 (JS 가 dirty 플래그 + 12색 JSON 을 같이 보냈을 때)
        if (!empty($_POST['apply_palette']) && !empty($_POST['palette_json'])) {
            $palette = json_decode($_POST['palette_json'], true);
            if (is_array($palette)) {
                $valid_vars = [
                    '--bg', '--paper', '--paper-2',
                    '--ink', '--ink-soft', '--ink-mute',
                    '--hair', '--hair-soft',
                    '--accent', '--accent-soft', '--accent-2',
                ];
                $clean = [];
                foreach ($valid_vars as $v) {
                    if (isset($palette[$v]) && preg_match('/^#[0-9a-fA-F]{6}$/', $palette[$v])) {
                        $clean[$v] = strtolower($palette[$v]);
                    }
                }
                if ($clean) {
                    hp_config_set('theme_overrides', json_encode($clean, JSON_UNESCAPED_SLASHES));
                }
            }
        }

        if (empty($_SESSION['_flash'])) {
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '배경이 저장되었습니다.'];
        }
    }

    elseif ($action === 'remove_background') {
        $existing = hp_config('background_image', '');
        if ($existing && !preg_match('#^https?://#i', $existing)) {
            @unlink(HP_PATH . '/data/uploads/' . $existing);
        }
        hp_config_set('background_image', '');
        $_SESSION['_flash'] = ['type' => 'success', 'msg' => '배경이 제거되었습니다.'];
    }

    elseif ($action === 'save_cursor') {
        $static_input   = trim($_POST['cursor_static'] ?? '');
        $animated_input = trim($_POST['cursor_animated'] ?? '');

        // 정적 커서: CSS 스니펫 / URL / 빈 값
        $static_url = '';
        if ($static_input !== '') {
            // cursor: url('...') 0 0  형태에서 URL 추출
            if (preg_match('#cursor:\s*url\([\'"]?([^\'")]+)[\'"]?\)#i', $static_input, $m)) {
                $static_url = $m[1];
            } elseif (preg_match('#^https?://\S+$#i', $static_input)) {
                $static_url = $static_input;
            } else {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '정적 커서 입력에서 URL을 찾을 수 없어요.'];
            }
        }

        // 애니메이션 커서: <link> 태그 / URL / 빈 값
        $animated_url = '';
        if ($animated_input !== '') {
            if (preg_match('#href=["\']([^"\']+\.css[^"\']*)["\']#i', $animated_input, $m)) {
                $animated_url = $m[1];
            } elseif (preg_match('#^https?://\S+$#i', $animated_input)) {
                $animated_url = $animated_input;
            } else {
                $_SESSION['_flash'] = ['type' => 'error', 'msg' => '애니메이션 커서 입력에서 URL을 찾을 수 없어요.'];
            }
        }

        // URL 유효성: https로 시작해야 함
        if ($static_url   && !preg_match('#^https?://#i', $static_url))   $static_url   = '';
        if ($animated_url && !preg_match('#^https?://#i', $animated_url)) $animated_url = '';

        hp_config_set('cursor_static_url',   $static_url);
        hp_config_set('cursor_animated_url', $animated_url);

        if (empty($_SESSION['_flash'])) {
            $_SESSION['_flash'] = ['type' => 'success', 'msg' => '커서가 저장되었습니다.'];
        }
    }

    header('Location: ' . HP_BASE . '/admin/?section=design');
    exit;
}

$current_theme = hp_config('theme_preset', 'paper');

$presets = hp_theme_presets();
$theme_meta = [
    'paper'    => ['Paper',    'light'],
    'linen'    => ['Linen',    'light'],
    'ink'      => ['Ink',      'dark'],
    'midnight' => ['Midnight', 'dark'],
];

// 현재 적용 중인 색상 (프리셋 + 오버라이드 병합)
$current_vars = hp_theme_vars();

// 오버라이드가 비어있는지 = 순수 프리셋 사용 중인지
$overrides_json = hp_config('theme_overrides', '');
$has_custom = !empty($overrides_json);

// 색상 변수 라벨 (UI 표시용)
$var_labels = [
    '--bg'          => ['배경',          '사이트 전체 배경'],
    '--paper'       => ['용지',          '본문 카드의 베이스 색'],
    '--paper-2'     => ['용지 (보조)',   '강조 카드 / 패널 베이스'],
    '--ink'         => ['글자',          '본문 글자 색'],
    '--ink-soft'    => ['글자 (보조)',   '서브 텍스트'],
    '--ink-mute'    => ['글자 (희미)',   '레이블 / 메타 텍스트'],
    '--hair'        => ['선',            '카드 테두리, 구분선'],
    '--hair-soft'   => ['선 (희미)',     '내부 구분선'],
    '--accent'      => ['포인트',        '강조 색상 (링크, 활성)'],
    '--accent-soft' => ['포인트 (희미)', '활성 배경, 칩'],
    '--accent-2'    => ['포인트 보조',   '두 번째 강조 (삭제·경고 등)'],
];

// 폰트 / 커스텀 CSS
$custom_css_path = HP_PATH . '/data/custom.css';
$custom_css      = file_exists($custom_css_path) ? file_get_contents($custom_css_path) : '';

// JS 가 사용할 프리셋 색상 데이터 (프리셋 클릭 시 color picker 자동 채움용)
$presets_for_js = [];
foreach ($presets as $key => $vars) {
    $presets_for_js[$key] = $vars;
}
?>

<!-- ─── 프리셋 ─── -->
<div class="adm-section">
  <h2>테마 프리셋</h2>
  <div class="sub">4가지 프리셋 중 하나를 선택해서 빠르게 적용하세요. 프리셋을 선택하면 아래의 커스텀 색상은 초기화돼요.</div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_preset">

    <div class="theme-grid">
      <?php foreach ($presets as $key => $vars):
        [$name, $mode] = $theme_meta[$key];
      ?>
        <label class="theme-opt <?= ($current_theme === $key && !$has_custom) ? 'active' : '' ?>">
          <input type="radio" name="theme" value="<?= $key ?>" <?= $current_theme === $key ? 'checked' : '' ?>>
          <div class="swatch">
            <span style="background:<?= h($vars['--bg']) ?>"></span>
            <span style="background:<?= h($vars['--paper']) ?>"></span>
            <span style="background:<?= h($vars['--accent']) ?>"></span>
          </div>
          <span class="name"><?= $name ?></span>
          <span class="label"><?= $mode ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">프리셋 적용</button>
    </div>
  </form>
</div>

<!-- ─── 레이아웃 옵션 ─── -->
<div class="adm-section">
  <h2>레이아웃</h2>
  <div class="sub">사이드바·상단바 의 표시 여부를 제어해요. 미니멀한 룩을 원하면 사이트 이름을 끌 수 있어요. (브라우저 탭 제목과 검색엔진 결과에는 그대로 사용됩니다)</div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_layout">

    <div class="adm-field">
      <label class="check-row">
        <input type="checkbox" name="show_site_name" value="1" <?= hp_config('show_site_name', '1') !== '0' ? 'checked' : '' ?>>
        <span>
          <strong>사이드바 / 상단바에 사이트 이름 표시</strong>
          <span class="hint" style="display:block;margin-top:2px;">
            끄면 PC 사이드바의 브랜드 영역과 모바일 상단바의 사이트명이 사라져요.
            아바타·프로필 카드가 이미 사이트의 정체성을 충분히 드러내는 경우 끄면 더 깔끔해져요.
          </span>
        </span>
      </label>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </form>
</div>

<!-- ─── 커스텀 색상 ─── -->
<div class="adm-section">
  <h2>색상 직접 선택 <?php if ($has_custom): ?><span style="font-size:11px;color:var(--accent);font-weight:700;margin-left:6px;">CUSTOM 적용 중</span><?php endif; ?></h2>
  <div class="sub">
    프리셋이 마음에 안 들면 12개 색상을 직접 골라서 나만의 테마를 만들어보세요.
    위에서 베이스로 쓸 프리셋 라디오를 클릭하면 그 프리셋 색이 색상 칸에 자동으로 채워져요.
  </div>

  <!-- 팔레트 자동 생성기 -->
  <div class="palette-gen">
    <div class="palette-gen-row">
      <label>
        <span>🎨 기준색</span>
        <input type="color" id="paletteSeed" value="<?= h($current_vars['--accent'] ?? '#b8533a') ?>">
      </label>
      <div class="palette-modes">
        <label class="radio">
          <input type="radio" name="paletteMode" value="light" checked> 라이트
        </label>
        <label class="radio">
          <input type="radio" name="paletteMode" value="dark"> 다크
        </label>
      </div>
      <button type="button" id="paletteGenBtn" class="adm-btn adm-btn-secondary adm-btn-sm">
        팔레트 자동 생성
      </button>
    </div>
    <div class="palette-hint">
      기준 색상 하나만 정하면 나머지 11색을 자동으로 어울리게 만들어줘요. 생성 후 미리보기를 확인하고 마음에 들면 아래 "색상 저장" 으로 확정.
    </div>
  </div>

  <form method="post" id="customColorForm">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_custom_colors">

    <div class="color-grid">
      <?php foreach ($var_labels as $var => $info):
        [$label, $desc] = $info;
        $value = $current_vars[$var] ?? '#000000';
      ?>
        <div class="color-row">
          <label>
            <span class="lbl"><?= h($label) ?></span>
            <span class="desc"><?= h($desc) ?></span>
          </label>
          <input type="color" name="colors[<?= h($var) ?>]" value="<?= h($value) ?>" data-var="<?= h($var) ?>">
        </div>
      <?php endforeach; ?>
    </div>

    <div class="adm-actions">
      <?php if ($has_custom): ?>
        <button type="submit" form="resetColorForm" class="adm-btn adm-btn-danger">초기화 (프리셋으로 복귀)</button>
      <?php endif; ?>
      <button type="submit" class="adm-btn adm-btn-primary">색상 저장</button>
    </div>
  </form>

  <?php if ($has_custom): ?>
    <form method="post" id="resetColorForm" style="display:none">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="reset_custom_colors">
    </form>
  <?php endif; ?>
</div>

<!-- ─── 배경 이미지 ─── -->
<?php
$bg_image      = hp_config('background_image', '');
$bg_size       = hp_config('background_size', 'cover');
$bg_attachment = hp_config('background_attachment', 'fixed');
$bg_repeat     = hp_config('background_repeat', 'no-repeat');
$bg_position   = hp_config('background_position', 'center center');
$bg_overlay    = (int)hp_config('background_overlay', '0');

$bg_src = '';
if ($bg_image) {
    $bg_src = preg_match('#^https?://#i', $bg_image)
        ? $bg_image
        : HP_BASE . '/data/uploads/' . $bg_image;
}
?>
<div class="adm-section">
  <h2>배경 이미지</h2>
  <div class="sub">사이트 전체에 깔리는 배경 이미지. 너무 강하면 가독성을 해치니 아래 "어둡게 덮기" 로 조절하세요.</div>

  <?php if ($bg_src): ?>
    <div class="bg-preview" style="background-image:url('<?= h($bg_src) ?>');background-size:<?= h($bg_size) ?>;background-position:<?= h($bg_position) ?>;background-repeat:<?= h($bg_repeat) ?>">
      <span>현재 배경</span>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="bgImageForm">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_background">
    <input type="hidden" name="apply_palette" id="bgApplyPalette" value="0">
    <input type="hidden" name="palette_json"  id="bgPaletteJson"  value="">

    <div class="adm-field">
      <label>이미지 URL <span style="color:var(--ink-mute);font-weight:500;">(우선)</span></label>
      <input type="url" name="bg_image_url" id="bgImageUrl" placeholder="https://...">
    </div>

    <div class="adm-field">
      <label>또는 파일 업로드</label>
      <span class="hint">JPG / PNG / WebP / GIF, 5MB 이하</span>
      <input type="file" name="bg_image_file" accept="image/*">
    </div>

    <div class="adm-row">
      <div class="adm-field">
        <label>크기</label>
        <select name="bg_size">
          <option value="cover"   <?= $bg_size === 'cover'   ? 'selected' : '' ?>>화면 채우기 (cover)</option>
          <option value="contain" <?= $bg_size === 'contain' ? 'selected' : '' ?>>비율 유지 (contain)</option>
          <option value="auto"    <?= $bg_size === 'auto'    ? 'selected' : '' ?>>원본 크기 (auto)</option>
        </select>
      </div>
      <div class="adm-field">
        <label>스크롤</label>
        <select name="bg_attachment">
          <option value="fixed"  <?= $bg_attachment === 'fixed'  ? 'selected' : '' ?>>고정 (fixed)</option>
          <option value="scroll" <?= $bg_attachment === 'scroll' ? 'selected' : '' ?>>같이 스크롤</option>
        </select>
      </div>
    </div>

    <div class="adm-row">
      <div class="adm-field">
        <label>반복</label>
        <select name="bg_repeat">
          <option value="no-repeat" <?= $bg_repeat === 'no-repeat' ? 'selected' : '' ?>>반복 안 함</option>
          <option value="repeat"    <?= $bg_repeat === 'repeat'    ? 'selected' : '' ?>>전체 반복 (패턴용)</option>
          <option value="repeat-x"  <?= $bg_repeat === 'repeat-x'  ? 'selected' : '' ?>>가로만 반복</option>
          <option value="repeat-y"  <?= $bg_repeat === 'repeat-y'  ? 'selected' : '' ?>>세로만 반복</option>
        </select>
      </div>
      <div class="adm-field">
        <label>위치</label>
        <select name="bg_position">
          <?php foreach ([
            'center center' => '가운데',
            'top center'    => '상단',
            'bottom center' => '하단',
            'top left'      => '좌측 상단',
            'top right'     => '우측 상단',
            'bottom left'   => '좌측 하단',
            'bottom right'  => '우측 하단',
          ] as $val => $lbl): ?>
            <option value="<?= h($val) ?>" <?= $bg_position === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

<?php $bg_blur = (int)hp_config('background_blur', '0'); ?>
    <div class="adm-field">
      <label>어둡게 덮기 (<?= $bg_overlay ?>%)</label>
      <span class="hint">배경이 너무 강할 때 검정 오버레이를 깔아 가독성 확보. 0% = 그대로, 50% = 절반 어둡게.</span>
      <input type="range" name="bg_overlay" min="0" max="80" value="<?= $bg_overlay ?>" oninput="this.previousElementSibling.previousElementSibling.textContent='어둡게 덮기 (' + this.value + '%)'">
    </div>

    <div class="adm-field">
      <label>블러 (<?= $bg_blur ?>px)</label>
      <span class="hint">배경 이미지를 흐리게 만들어 본문 가독성을 높여요. 0px = 선명, 20px = 부드러운 보케 느낌.</span>
      <input type="range" name="bg_blur" min="0" max="40" value="<?= $bg_blur ?>" oninput="this.previousElementSibling.previousElementSibling.textContent='블러 (' + this.value + 'px)'">
    </div>

    <div class="adm-actions">
      <?php if ($bg_image): ?>
        <button type="submit" form="removeBgForm" class="adm-btn adm-btn-danger">배경 제거</button>
      <?php endif; ?>
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </form>

  <?php if ($bg_image): ?>
    <form method="post" id="removeBgForm" style="display:none">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="remove_background">
    </form>

    <!-- 저장된 배경에서 팔레트 추출 (저장 후에만 보임) -->
    <div class="palette-gen" style="margin-top:18px;">
      <div class="palette-gen-row">
        <span style="font-size:12px;font-weight:700;color:var(--ink);">🎨 이 배경에서 팔레트 만들기</span>
        <div class="palette-modes">
          <label class="radio">
            <input type="radio" name="bgPaletteMode" value="light" checked> 라이트
          </label>
          <label class="radio">
            <input type="radio" name="bgPaletteMode" value="dark"> 다크
          </label>
        </div>
        <button type="button" id="bgPaletteBtn" class="adm-btn adm-btn-secondary adm-btn-sm">팔레트 생성</button>
      </div>
      <div class="palette-hint">
        저장된 배경 이미지에서 가장 자주 등장하는 색을 추출해 12색 팔레트를 만들어요.
        라이트/다크 라디오를 바꾸면 추출한 색을 재사용해서 즉시 다시 생성합니다 (재추출 없음).
        결과가 마음에 들면 위쪽 "색상 직접 선택" 섹션의 "색상 저장" 버튼으로 확정.
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ─── 마우스 커서 ─── -->
<?php
$cursor_static   = hp_config('cursor_static_url', '');
$cursor_animated = hp_config('cursor_animated_url', '');
?>
<div class="adm-section">
  <h2>마우스 커서</h2>
  <div class="sub">
    <a href="https://www.cursors-4u.com" target="_blank">cursors-4u.com</a> 같은 사이트에서 마음에 드는 커서를 골라
    "Get Code" 버튼으로 나오는 코드를 그대로 복사해서 아래에 붙여넣으면 돼요. URL만 붙여넣어도 동작.
  </div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_cursor">

    <div class="adm-field">
      <label>정적 커서 (Static)</label>
      <span class="hint">
        예시 입력 형식:<br>
        · CSS 스니펫: <code>* { cursor: url('https://....webp') 0 0, auto !important; }</code><br>
        · 또는 URL 만: <code>https://....webp</code>
      </span>
      <textarea name="cursor_static" class="code" rows="3" placeholder="* { cursor: url('https://...'), auto !important; }"><?= h($cursor_static) ?></textarea>
    </div>

    <div class="adm-field">
      <label>애니메이션 커서 (Animated, experimental)</label>
      <span class="hint">
        애니메이션 커서는 stylesheet 형태로 제공돼요. 예시 입력 형식:<br>
        · &lt;link&gt; 태그: <code>&lt;link rel="stylesheet" href="https://....css"&gt;</code><br>
        · 또는 URL 만: <code>https://....css</code>
      </span>
      <textarea name="cursor_animated" class="code" rows="3" placeholder='<link rel="stylesheet" href="https://....css">'><?= h($cursor_animated) ?></textarea>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">커서 저장</button>
    </div>
  </form>
</div>

<!-- ─── 폰트 ─── -->
<div class="adm-section">
  <h2>제목용 폰트</h2>
  <div class="sub">제목·강조 텍스트에만 적용. 본문은 가독성 위해 Pretendard 가 그대로 유지돼요.</div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_font">

    <div class="adm-field">
      <label>@import URL</label>
      <span class="hint">예: <code>https://fonts.googleapis.com/css2?family=Gowun+Batang&display=swap</code></span>
      <input type="url" name="font_import_url" value="<?= h(hp_config('font_import_url')) ?>">
    </div>

    <div class="adm-field">
      <label>제목 폰트 family</label>
      <span class="hint">예: <code>"Gowun Batang", serif</code></span>
      <input type="text" name="font_display_family" value="<?= h(hp_config('font_display_family')) ?>">
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">저장</button>
    </div>
  </form>
</div>

<!-- ─── 커스텀 CSS ─── -->
<div class="adm-section">
  <h2>커스텀 CSS</h2>
  <div class="sub">테마·색상·폰트로 부족하다면 직접 CSS 를 추가할 수 있어요. <code>data/custom.css</code> 에 저장돼서 모든 페이지에 마지막으로 로드됩니다.</div>

  <form method="post">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="save_custom_css">

    <div class="adm-field">
      <textarea name="custom_css" class="code" placeholder="/* 여기에 CSS를 입력하세요 */"><?= h($custom_css) ?></textarea>
    </div>

    <div class="adm-actions">
      <button type="submit" class="adm-btn adm-btn-primary">CSS 저장</button>
    </div>
  </form>
</div>

<!-- 프리셋 데이터 → JS (프리셋 클릭 시 color input 자동 채움) -->
<script>
window.themePresets = <?= json_encode($presets_for_js, JSON_UNESCAPED_SLASHES) ?>;

// ═══════════ HSL ↔ HEX 변환 ═══════════
function hexToHsl(hex) {
  hex = hex.replace('#', '');
  const r = parseInt(hex.slice(0, 2), 16) / 255;
  const g = parseInt(hex.slice(2, 4), 16) / 255;
  const b = parseInt(hex.slice(4, 6), 16) / 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  let h = 0, s = 0, l = (max + min) / 2;
  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 0); break;
      case g: h = (b - r) / d + 2; break;
      case b: h = (r - g) / d + 4; break;
    }
    h *= 60;
  }
  return { h, s: s * 100, l: l * 100 };
}
function hslToHex(h, s, l) {
  s /= 100; l /= 100;
  const k = n => (n + h / 30) % 12;
  const a = s * Math.min(l, 1 - l);
  const f = n => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
  const toHex = x => Math.round(x * 255).toString(16).padStart(2, '0');
  return '#' + toHex(f(0)) + toHex(f(8)) + toHex(f(4));
}

// ═══════════ 팔레트 생성 알고리즘 ═══════════
function generatePalette(seedHex, mode) {
  const { h, s, l } = hexToHsl(seedHex);

  // 중성색은 기준색 hue 를 그대로 따라감 → 폭이 넓어짐
  // (예: 빨강 시드 → 따뜻한 분홍빛 베이지, 청록 시드 → 청록빛 회색)
  const nh = h;

  // 중성색 채도: 기준 채도에 비례 (높으면 더 tinted, 낮으면 거의 무채색)
  const ns = Math.min(s * 0.35, 24);

  // accent-2 는 split-complementary (보색보다 자연스러움)
  const a2h = (h + 130) % 360;

  // 명도 범위 안전장치
  const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));

  if (mode === 'light') {
    return {
      '--bg':          hslToHex(nh, ns,                          93),
      '--paper':       hslToHex(nh, Math.min(ns * 1.7, 32),      98),
      '--paper-2':     hslToHex(nh, Math.min(ns * 1.4, 28),      95),
      '--ink':         hslToHex(nh, Math.min(s  * 0.18, 14),     14),
      '--ink-soft':    hslToHex(nh, Math.min(s  * 0.15, 12),     32),
      '--ink-mute':    hslToHex(nh, Math.min(s  * 0.12, 10),     56),
      '--hair':        hslToHex(nh, Math.min(ns * 1.2,  26),     87),
      '--hair-soft':   hslToHex(nh, Math.min(ns * 1.4,  28),     91),
      '--accent':      seedHex,
      '--accent-soft': hslToHex(h,  Math.max(s  * 0.45, 25),     92),
      '--accent-2':    hslToHex(a2h, Math.max(s * 0.55, 28),     clamp(l, 35, 50))
    };
  } else {
    return {
      '--bg':          hslToHex(nh, Math.min(ns,        18),     8),
      '--paper':       hslToHex(nh, Math.min(ns * 1.5,  22),     13),
      '--paper-2':     hslToHex(nh, Math.min(ns * 1.3,  20),     11),
      '--ink':         hslToHex(nh, Math.min(s  * 0.1,  10),     92),
      '--ink-soft':    hslToHex(nh, Math.min(s  * 0.12, 12),     70),
      '--ink-mute':    hslToHex(nh, Math.min(s  * 0.1,  10),     48),
      '--hair':        hslToHex(nh, Math.min(ns * 1.2,  22),     19),
      '--hair-soft':   hslToHex(nh, Math.min(ns,        18),     14),
      '--accent':      seedHex,
      '--accent-soft': hslToHex(h,  Math.max(s  * 0.4,  25),     17),
      '--accent-2':    hslToHex(a2h, Math.max(s * 0.55, 32),     clamp(l + 12, 55, 72))
    };
  }
}

// ═══════════ 12개 color input 채움 + 라이브 프리뷰 ═══════════
function applyPalette(palette) {
  document.querySelectorAll('input[type="color"][data-var]').forEach(input => {
    const v = input.dataset.var;
    if (palette[v]) {
      input.value = palette[v];
      // 어드민 페이지 자체에 라이브 반영 (저장 전 미리보기)
      document.documentElement.style.setProperty(v, palette[v]);
    }
  });
}

// ═══════════ 버튼 핸들러 ═══════════
const paletteBtn = document.getElementById('paletteGenBtn');
if (paletteBtn) {
  paletteBtn.addEventListener('click', () => {
    const seed = document.getElementById('paletteSeed').value;
    const mode = document.querySelector('input[name="paletteMode"]:checked').value;
    applyPalette(generatePalette(seed, mode));
    if (window.toast) window.toast('팔레트가 생성되었어요. 마음에 들면 "색상 저장" 버튼을 누르세요.');
  });
}

// 기준색이 바뀌면 paletteSeed 와 동기화 (선택 사항)
document.querySelectorAll('input[type="color"][data-var="--accent"]').forEach(input => {
  input.addEventListener('input', () => {
    const seedInput = document.getElementById('paletteSeed');
    if (seedInput) seedInput.value = input.value;
  });
});

// ═══════════ 이미지에서 dominant color 추출 ═══════════
// canvas 로 픽셀 데이터를 읽어서 가장 많이 나오는 색상(채도 있는 색 우선)을 찾음
function extractDominantColor(imgUrl) {
  return new Promise((resolve) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      try {
        const W = 80, H = 80;
        const c = document.createElement('canvas');
        c.width = W; c.height = H;
        const ctx = c.getContext('2d');
        ctx.drawImage(img, 0, 0, W, H);
        const data = ctx.getImageData(0, 0, W, H).data;

        // 4비트 양자화 → 4096개 버킷에 카운트
        const buckets = {};
        for (let i = 0; i < data.length; i += 4) {
          const r = data[i], g = data[i+1], b = data[i+2], a = data[i+3];
          if (a < 128) continue;                          // 투명 픽셀 skip
          const max = Math.max(r,g,b), min = Math.min(r,g,b);
          if (max < 25 || min > 230) continue;            // 너무 어둡거나 밝은 것 skip
          if (max - min < 18) continue;                   // 채도 없는 회색 skip
          const key = (r >> 4) + ',' + (g >> 4) + ',' + (b >> 4);
          buckets[key] = (buckets[key] || 0) + 1;
        }
        let bestKey = null, bestCount = 0;
        for (const k in buckets) {
          if (buckets[k] > bestCount) { bestCount = buckets[k]; bestKey = k; }
        }
        if (!bestKey) { resolve(null); return; }
        const [r, g, b] = bestKey.split(',').map(x => (parseInt(x) << 4) + 8);
        const toHex = x => x.toString(16).padStart(2, '0');
        resolve('#' + toHex(r) + toHex(g) + toHex(b));
      } catch (e) {
        resolve(null);  // CORS 에러 등
      }
    };
    img.onerror = () => resolve(null);
    img.src = imgUrl;
  });
}

// 배경에서 추출한 dominant color 캐시 (한 번 추출하면 모드만 바꿔도 즉시 재생성)
let bgDominantColor = null;
let bgPaletteDirty  = false;  // 팔레트가 이번 세션에 수정됐는지

// 배경 폼 submit 시 dirty 면 현재 12색을 hidden input 에 담아 같이 전송
const bgImageForm = document.getElementById('bgImageForm');
if (bgImageForm) {
  bgImageForm.addEventListener('submit', () => {
    if (bgPaletteDirty) {
      const colors = {};
      document.querySelectorAll('input[type="color"][data-var]').forEach(input => {
        colors[input.dataset.var] = input.value;
      });
      document.getElementById('bgApplyPalette').value = '1';
      document.getElementById('bgPaletteJson').value  = JSON.stringify(colors);
    }
  });
}

const bgPaletteBtn = document.getElementById('bgPaletteBtn');
if (bgPaletteBtn) {
  // 라이트/다크 라디오 변경 → 캐시된 색으로 즉시 재생성 (라이브 프리뷰)
  document.querySelectorAll('input[name="bgPaletteMode"]').forEach(radio => {
    radio.addEventListener('change', () => {
      if (!bgDominantColor) return;
      applyPalette(generatePalette(bgDominantColor, radio.value));
      bgPaletteDirty = true;
      const seedInput = document.getElementById('paletteSeed');
      if (seedInput) seedInput.value = bgDominantColor;
      const mainRadio = document.querySelector('input[name="paletteMode"][value="' + radio.value + '"]');
      if (mainRadio) mainRadio.checked = true;
    });
  });

  // 버튼 클릭 → 추출 (캐시 없으면) + 생성
  bgPaletteBtn.addEventListener('click', async () => {
    const mode = document.querySelector('input[name="bgPaletteMode"]:checked').value;

    if (bgDominantColor) {
      applyPalette(generatePalette(bgDominantColor, mode));
      bgPaletteDirty = true;
      const seedInput = document.getElementById('paletteSeed');
      if (seedInput) seedInput.value = bgDominantColor;
      if (window.toast) window.toast('팔레트가 다시 생성되었어요.');
      return;
    }

    const preview = document.querySelector('.bg-preview');
    if (!preview) return;
    const m = getComputedStyle(preview).backgroundImage.match(/url\(['"]?([^'")]+)['"]?\)/);
    if (!m) return;

    bgPaletteBtn.disabled = true;
    bgPaletteBtn.textContent = '분석 중...';
    bgDominantColor = await extractDominantColor(m[1]);
    bgPaletteBtn.disabled = false;
    bgPaletteBtn.textContent = '팔레트 생성';

    if (!bgDominantColor) {
      alert('이미지에서 색상을 추출하지 못했어요.\n외부 URL의 경우 CORS 정책으로 막힐 수 있어요.\n파일을 직접 업로드한 뒤 다시 시도해보세요.');
      return;
    }

    applyPalette(generatePalette(bgDominantColor, mode));
    bgPaletteDirty = true;
    const seedInput = document.getElementById('paletteSeed');
    if (seedInput) seedInput.value = bgDominantColor;
    const mainRadio = document.querySelector('input[name="paletteMode"][value="' + mode + '"]');
    if (mainRadio) mainRadio.checked = true;
    if (window.toast) window.toast('이미지에서 색을 추출해서 팔레트를 만들었어요. 배경 "저장" 누르면 팔레트도 함께 저장돼요.');
  });
}
</script>
