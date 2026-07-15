<?php
/**
 * lib/markdown.php — 마크다운 → HTML 렌더
 *
 * Parsedown 표준 마크다운 + 커스텀 문법 3종:
 *   - 접기: ||제목|내용||
 *   - 블러: %%내용%%
 *   - 루비: {한자|발음}
 *
 * 처리 순서: 커스텀 syntax 를 placeholder 로 추출 → Parsedown 통과 → placeholder 복원.
 * 이 순서로 하면 마크다운 표/강조 등과 충돌이 없음.
 *
 * Parsedown safe mode = false. 어드민만 글을 쓸 수 있어서 (require_admin) HTML 통과를
 * 허용해도 안전. 정렬(<p style="text-align:...">) 등이 그대로 통과되어 view 에 적용됨.
 */

if (!class_exists('Parsedown')) {
    $parsedown_path = HP_PATH . '/vendor/Parsedown.php';
    if (file_exists($parsedown_path)) {
        require_once $parsedown_path;
    }
}

/**
 * 연속된 빈 줄을 시각적 여백으로 보존.
 *
 * 표준 markdown 동작은 "빈 줄 N개 = paragraph break 1번" 이라 사용자가
 * 엔터를 여러 번 쳐서 만든 의도적 여백이 렌더링 후 사라짐. 이걸 막기 위해
 * 두 번째부터의 빈 줄을 `&nbsp;` 단락으로 치환해서 빈 paragraph 를 만든다.
 *
 * 코드 블록(```...```) 안의 빈 줄은 그대로 보존되어야 하므로 먼저 추출 후 처리.
 */
function hp_preserve_blank_lines($text) {
    // 1. fenced code block 임시 추출 (빈 줄이 코드 일부일 수 있음)
    $code_blocks = [];
    $text = preg_replace_callback(
        '/```[\s\S]*?```/m',
        function ($m) use (&$code_blocks) {
            $key = "\x01HPCODE" . count($code_blocks) . "\x02";
            $code_blocks[$key] = $m[0];
            return $key;
        },
        $text
    );

    // 2. 줄 단위로 빈 줄 시퀀스 처리
    $lines = preg_split('/\R/u', $text);
    $result = [];
    $blank_streak = 0;
    foreach ($lines as $line) {
        if (trim($line) === '') {
            $blank_streak++;
            if ($blank_streak === 1) {
                // 첫 빈 줄은 정상 paragraph break
                $result[] = '';
            } else {
                // 두 번째부터: &nbsp; 단락으로 시각적 여백 추가
                $result[] = '&nbsp;';
                $result[] = '';
            }
        } else {
            $blank_streak = 0;
            $result[] = $line;
        }
    }
    $text = implode("\n", $result);

    // 3. 코드 블록 복원
    if ($code_blocks) {
        $text = strtr($text, $code_blocks);
    }
    return $text;
}

function hp_render_markdown($text) {
    if (!is_string($text) || $text === '') return '';

    static $pd = null;
    if ($pd === null && class_exists('Parsedown')) {
        $pd = new Parsedown();
        $pd->setSafeMode(false);     // 어드민만 글 쓰므로 HTML 통과 (정렬 등에 필요)
        $pd->setBreaksEnabled(true); // 단일 줄바꿈 → <br>
    }

    if (!$pd) {
        // Parsedown 미설치 폴백
        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    // ─── 0. 빈 줄 시각적 여백 보존 ───
    $text = hp_preserve_blank_lines($text);

    // ─── 1. 커스텀 syntax 를 placeholder 로 미리 추출 ───
    $placeholders = [];
    $token = function ($html) use (&$placeholders) {
        // \x01 / \x02 는 일반 텍스트에 절대 안 나오는 control char
        $key = "\x01HPMD" . count($placeholders) . "\x02";
        $placeholders[$key] = $html;
        return $key;
    };
    $esc = function ($s) {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    };

    // 접기 — ||제목|내용||  (가장 specific 하므로 먼저)
    // 제목 안엔 | 와 newline 없음, 내용은 newline 가능
    $text = preg_replace_callback(
        '/\|\|([^|\n]+?)\|((?:[^|]|\|(?!\|))+?)\|\|/s',
        function ($m) use ($token, $esc) {
            return $token(
                '<details class="md-fold"><summary>' . $esc($m[1]) . '</summary>'
                . '<div class="md-fold-body">' . nl2br($esc($m[2])) . '</div>'
                . '</details>'
            );
        },
        $text
    );

    // 블러 — %%내용%%
    $text = preg_replace_callback(
        '/%%(.+?)%%/s',
        function ($m) use ($token, $esc) {
            return $token('<span class="md-blur" title="클릭하여 보기">' . $esc($m[1]) . '</span>');
        },
        $text
    );

    // 루비 — {한자|발음}  내용/발음 둘 다 | 와 {} 와 newline 없음
    $text = preg_replace_callback(
        '/\{([^|{}\n]+?)\|([^|{}\n]+?)\}/',
        function ($m) use ($token, $esc) {
            return $token('<ruby>' . $esc($m[1]) . '<rt>' . $esc($m[2]) . '</rt></ruby>');
        },
        $text
    );

    // ─── 2. Parsedown 통과 ───
    $html = $pd->text($text);

    // ─── 3. placeholder 복원 ───
    if ($placeholders) {
        $html = strtr($html, $placeholders);
    }

    return $html;
}

/**
 * 본문 HTML 내 [img:파일명] 태그를 <img> 로 치환.
 *
 * write/edit 에서 "본문 삽입" 버튼으로 넣은 태그를 실제 이미지로 렌더한다.
 * 반드시 hp_render_markdown() 을 통과한 "후" 의 HTML 에 적용할 것.
 * (Parsedown 이 \[ 를 [ 로 되돌려주므로 escape 형태 걱정 없음)
 *
 * @param string     $html   렌더링이 끝난 본문 HTML
 * @param array      $images hp_post_images($post) 결과 (파일명 배열)
 * @param array|null $board  게시판 행 (hp_post_image_url 에 전달)
 * @return array [치환된 HTML, 본문에 사용되지 않은 이미지 배열]
 */
function hp_render_inline_images($html, $images, $board = null) {
    $used = array();

    foreach ($images as $img) {
        $tag = '[img:' . $img . ']';
        if (strpos($html, $tag) === false) continue;

        $src = function_exists('hp_post_image_url')
            ? hp_post_image_url($img, $board)
            : HP_BASE . '/data/posts/' . rawurlencode($img);
        $imgTag = '<img src="' . h($src) . '" class="body-inline-img" alt="" loading="lazy">';

        // 태그가 단독 문단(<p>[img:...]</p>)이면 문단째 치환해 여백 정리
        $pTag = '<p>' . $tag . '</p>';
        if (strpos($html, $pTag) !== false) {
            $html = str_replace($pTag, '<p class="body-inline-img-p">' . $imgTag . '</p>', $html);
        }
        // 텍스트와 섞인 인라인 형태도 치환
        $html = str_replace($tag, $imgTag, $html);

        $used[$img] = true;
    }

    // 본문에 안 쓰인 이미지 = 기존 상단 갤러리용
    $rest = array();
    foreach ($images as $img) {
        if (!isset($used[$img])) $rest[] = $img;
    }
    return array($html, $rest);
}

/**
 * 게시글 본문 렌더링 + 인라인 이미지 치환 원스톱.
 *
 * 사용 (스킨 view):
 *   list($body_html, $top_images) = hp_render_post_body($post, $board);
 *
 * @param array      $post  {post} 행
 * @param array|null $board 게시판 행
 * @return array [본문 HTML, 상단 갤러리에 출력할 이미지 배열]
 */
function hp_render_post_body($post, $board = null) {
    $html = hp_render_markdown(isset($post['po_content']) ? $post['po_content'] : '');
    $images = function_exists('hp_post_images') ? hp_post_images($post) : array();
    if (!$images) return array($html, array());
    return hp_render_inline_images($html, $images, $board);
}
