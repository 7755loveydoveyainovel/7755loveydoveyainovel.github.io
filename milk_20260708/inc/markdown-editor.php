<?php
/**
 * inc/markdown-editor.php — Tiptap 마크다운 에디터
 *
 * write.php / edit.php 에서 include. textarea 를 hidden 으로 두고
 * Tiptap 인스턴스를 그 옆에 만든 후, form submit 직전에 에디터의
 * 마크다운을 추출해서 textarea 에 채워서 PHP 로 전송.
 *
 * 필요 변수:
 *   $editor_name  — textarea 의 name (예: 'content')
 *   $editor_value — 초기값 (마크다운 raw)
 *
 * 지원 기능:
 *   - 표준 마크다운 (StarterKit)
 *   - 정렬 4종 (TextAlign)
 *   - 커스텀 syntax: 블러 %% %%, 접기 || | ||, 루비 { | }
 */
$_eid = 'mde_' . substr(md5($editor_name . uniqid('', true)), 0, 8);
?>
<div class="md-editor" data-editor-id="<?= h($_eid) ?>" data-img-base="<?= h(HP_BASE) ?>/data/posts/">
  <div class="md-toolbar">
    <button type="button" data-cmd="bold" title="굵게 (Ctrl+B)"><i class="fas fa-bold"></i></button>
    <button type="button" data-cmd="italic" title="기울임 (Ctrl+I)"><i class="fas fa-italic"></i></button>
    <button type="button" data-cmd="strike" title="취소선"><i class="fas fa-strikethrough"></i></button>
    <span class="sep"></span>
    <button type="button" data-cmd="h1" title="제목"><i class="fas fa-heading"></i><sup>1</sup></button>
    <button type="button" data-cmd="h2" title="작은 제목"><i class="fas fa-heading"></i><sup>2</sup></button>
    <button type="button" data-cmd="h3" title="더 작은 제목"><i class="fas fa-heading"></i><sup>3</sup></button>
    <span class="sep"></span>
    <button type="button" data-cmd="alignLeft" title="좌측 정렬"><i class="fas fa-align-left"></i></button>
    <button type="button" data-cmd="alignCenter" title="가운데 정렬"><i class="fas fa-align-center"></i></button>
    <button type="button" data-cmd="alignRight" title="우측 정렬"><i class="fas fa-align-right"></i></button>
    <button type="button" data-cmd="alignJustify" title="양쪽 정렬"><i class="fas fa-align-justify"></i></button>
    <span class="sep"></span>
    <button type="button" data-cmd="bulletList" title="목록"><i class="fas fa-list-ul"></i></button>
    <button type="button" data-cmd="orderedList" title="번호 목록"><i class="fas fa-list-ol"></i></button>
    <button type="button" data-cmd="blockquote" title="인용"><i class="fas fa-quote-right"></i></button>
    <button type="button" data-cmd="codeBlock" title="코드 블록"><i class="fas fa-code"></i></button>
    <button type="button" data-cmd="hr" title="구분선"><i class="fas fa-minus"></i></button>
    <span class="sep"></span>
    <button type="button" data-cmd="blur" title="블러 (%% %%)"><i class="fas fa-eye-slash"></i></button>
    <button type="button" data-cmd="fold" title="접기 (|| | ||)"><i class="fas fa-caret-square-down"></i></button>
    <button type="button" data-cmd="ruby" title="루비 (한자 발음)"><i class="fas fa-language"></i></button>
  </div>
  <div class="md-content"></div>
</div>
<textarea name="<?= h($editor_name) ?>" id="<?= h($_eid) ?>_input" class="md-input"><?= h($editor_value) ?></textarea>

<script type="module">
import { Editor, Node } from 'https://esm.sh/@tiptap/core@2.10.0'
import { DOMSerializer } from 'https://esm.sh/@tiptap/pm@2.10.0/model'
import StarterKit from 'https://esm.sh/@tiptap/starter-kit@2.10.0'
import Paragraph from 'https://esm.sh/@tiptap/extension-paragraph@2.10.0'
import Heading from 'https://esm.sh/@tiptap/extension-heading@2.10.0'
import TextAlign from 'https://esm.sh/@tiptap/extension-text-align@2.10.0'
import { Markdown } from 'https://esm.sh/tiptap-markdown@0.8.10'

(function () {
  var eid   = '<?= $_eid ?>';
  var wrap  = document.querySelector('.md-editor[data-editor-id="' + eid + '"]');
  var input = document.getElementById(eid + '_input');
  if (!wrap || !input || wrap._init) return;
  wrap._init = true;

  var content = wrap.querySelector('.md-content');

  // ─── 정렬 보존 직렬화 ───
  // tiptap-markdown 은 textAlign 속성을 마크다운으로 표현하지 못해 저장 시 유실됨.
  // 정렬이 걸린 문단/제목만 HTML 로 직렬화하면 Parsedown(html 통과) 이
  // view 에 그대로 렌더하고, 재편집 시 markdown-it(html:true) 이 다시 파싱해 복원됨.
  function nodeInnerHTML(node) {
    var frag = DOMSerializer.fromSchema(node.type.schema)
      .serializeFragment(node.content, { document: document });
    var div = document.createElement('div');
    div.appendChild(frag);
    return div.innerHTML;
  }
  function alignedSerialize(defaultFn) {
    return function (state, node) {
      var a = node.attrs.textAlign;
      if (a && a !== 'left') {
        var tag = node.type.name === 'heading' ? 'h' + node.attrs.level : 'p';
        state.write('<' + tag + ' style="text-align:' + a + '">' + nodeInnerHTML(node) + '</' + tag + '>');
        state.closeBlock(node);
        return;
      }
      defaultFn(state, node);
    };
  }
  var AlignedParagraph = Paragraph.extend({
    addStorage: function () {
      return { markdown: { serialize: alignedSerialize(function (state, node) {
        state.renderInline(node);
        state.closeBlock(node);
      }), parse: {} } };
    },
  });
  var AlignedHeading = Heading.extend({
    addStorage: function () {
      return { markdown: { serialize: alignedSerialize(function (state, node) {
        state.write(state.repeat('#', node.attrs.level) + ' ');
        state.renderInline(node);
        state.closeBlock(node);
      }), parse: {} } };
    },
  });

  // ─── 인라인 이미지 노드 ───
  // 에디터 안에서는 실제 <img> 로 보이고, 마크다운 저장 시 [img:ref] 태그로 직렬화.
  // ref: 기존 첨부는 파일명, 저장 전 새 파일은 new:N (업로드 슬롯 번호 — 서버가 저장 시 파일명으로 치환)
  var HpImage = Node.create({
    name: 'hpImage',
    group: 'block',
    atom: true,
    draggable: true,
    addAttributes: function () {
      return { ref: { default: '' }, src: { default: '' } };
    },
    parseHTML: function () {
      return [{
        tag: 'img[data-hpimg]',
        getAttrs: function (el) {
          return { ref: el.getAttribute('data-hpimg') || '', src: el.getAttribute('src') || '' };
        },
      }];
    },
    renderHTML: function (props) {
      return ['img', {
        'data-hpimg': props.node.attrs.ref,
        src: props.node.attrs.src,
        class: 'mde-inline-img',
      }];
    },
    addStorage: function () {
      return {
        markdown: {
          serialize: function (state, node) {
            state.write('[img:' + node.attrs.ref + ']');
            state.closeBlock(node);
          },
          parse: {},
        },
      };
    },
  });

  // 저장된 마크다운의 [img:파일명] (escape 형태 \[img:...\] 포함) 을
  // 에디터가 이미지 노드로 파싱할 수 있게 <img data-hpimg> HTML 로 전처리.
  // new:N 임시 태그는 건드리지 않음 (정상 저장본엔 존재하지 않음).
  var imgBase = wrap.dataset.imgBase || '';
  function mdWithImages(md) {
    if (!md) return md;
    return md.replace(/\\?\[img:([^\[\]\n]+?)\\?\]/g, function (m, ref) {
      if (/^new:\d+$/.test(ref)) return m;
      return '<img data-hpimg="' + ref + '" src="' + imgBase + encodeURIComponent(ref) + '">';
    });
  }

  var editor = new Editor({
    element: content,
    extensions: [
      StarterKit.configure({ paragraph: false, heading: false }),
      AlignedParagraph,
      AlignedHeading,
      TextAlign.configure({
        types: ['heading', 'paragraph'],
      }),
      Markdown.configure({
        html: true,           // 정렬 HTML 통과 허용
        breaks: true,
        linkify: true,
        transformPastedText: true,
      }),
      HpImage,
    ],
    content: mdWithImages(input.value || ''),
  });

  // 외부(수정 버튼 등)에서 본문을 채울 수 있도록 인스턴스/헬퍼 노출
  wrap._tiptap = editor;
  wrap._setContent = function (md) {
    editor.commands.setContent(mdWithImages(md || ''));
    input.value = md || '';
  };

  // 커서 위치에 이미지 노드 삽입 (write/edit 의 "본문 삽입" 버튼용)
  wrap._insertImage = function (ref, src) {
    editor.chain().focus().insertContent({ type: 'hpImage', attrs: { ref: ref, src: src } }).run();
  };

  // 업로드 미리보기에서 슬롯 제거 시: 해당 new:N 노드 삭제 + 뒤 번호 당김
  wrap._retagNewImages = function (removedIdx) {
    var tr = editor.state.tr;
    var dels = [];
    editor.state.doc.descendants(function (node, pos) {
      if (node.type.name !== 'hpImage') return;
      var m = /^new:(\d+)$/.exec(node.attrs.ref || '');
      if (!m) return;
      var n = parseInt(m[1], 10);
      if (n === removedIdx) {
        dels.push(pos);
      } else if (n > removedIdx) {
        tr.setNodeMarkup(pos, undefined, Object.assign({}, node.attrs, { ref: 'new:' + (n - 1) }));
      }
    });
    dels.sort(function (a, b) { return b - a; }).forEach(function (pos) { tr.delete(pos, pos + 1); });
    if (tr.docChanged) editor.view.dispatch(tr);
  };

  // 선택된 텍스트를 syntax 로 감싸기 (또는 placeholder 삽입)
  function wrapSelection(before, after, placeholder) {
    var sel = editor.state.selection;
    var text = editor.state.doc.textBetween(sel.from, sel.to);
    if (text) {
      editor.chain().focus().insertContent(before + text + after).run();
    } else {
      editor.chain().focus().insertContent(before + (placeholder || '내용') + after).run();
    }
  }

  // 툴바 버튼
  wrap.querySelectorAll('.md-toolbar button').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var cmd = btn.dataset.cmd;
      var c = editor.chain().focus();
      switch (cmd) {
        case 'bold':         c.toggleBold().run(); break;
        case 'italic':       c.toggleItalic().run(); break;
        case 'strike':       c.toggleStrike().run(); break;
        case 'h1':           c.toggleHeading({ level: 1 }).run(); break;
        case 'h2':           c.toggleHeading({ level: 2 }).run(); break;
        case 'h3':           c.toggleHeading({ level: 3 }).run(); break;
        case 'alignLeft':    c.setTextAlign('left').run(); break;
        case 'alignCenter':  c.setTextAlign('center').run(); break;
        case 'alignRight':   c.setTextAlign('right').run(); break;
        case 'alignJustify': c.setTextAlign('justify').run(); break;
        case 'bulletList':   c.toggleBulletList().run(); break;
        case 'orderedList':  c.toggleOrderedList().run(); break;
        case 'blockquote':   c.toggleBlockquote().run(); break;
        case 'codeBlock':    c.toggleCodeBlock().run(); break;
        case 'hr':           c.setHorizontalRule().run(); break;
        case 'blur':         wrapSelection('%%', '%%', '블러 텍스트'); break;
        case 'fold': {
          var title = prompt('접기 제목:', '제목');
          if (title === null || title === '') break;
          var sel = editor.state.selection;
          var t = editor.state.doc.textBetween(sel.from, sel.to) || '내용';
          editor.chain().focus().insertContent('||' + title + '|' + t + '||').run();
          break;
        }
        case 'ruby': {
          var reading = prompt('발음 (루비):');
          if (reading === null || reading === '') break;
          var sel = editor.state.selection;
          var t = editor.state.doc.textBetween(sel.from, sel.to) || '한자';
          editor.chain().focus().insertContent('{' + t + '|' + reading + '}').run();
          break;
        }
      }
    });
  });

  // 활성 상태 표시 — selection 이동마다 갱신
  function updateActive() {
    var simpleMap = {
      bold: 'bold', italic: 'italic', strike: 'strike',
      bulletList: 'bulletList', orderedList: 'orderedList',
      blockquote: 'blockquote', codeBlock: 'codeBlock',
    };
    var alignMap = {
      alignLeft: 'left', alignCenter: 'center',
      alignRight: 'right', alignJustify: 'justify',
    };
    wrap.querySelectorAll('.md-toolbar button').forEach(function (btn) {
      var cmd = btn.dataset.cmd;
      if (cmd === 'h1' || cmd === 'h2' || cmd === 'h3') {
        var lvl = parseInt(cmd.slice(1), 10);
        btn.classList.toggle('active', editor.isActive('heading', { level: lvl }));
      } else if (simpleMap[cmd]) {
        btn.classList.toggle('active', editor.isActive(simpleMap[cmd]));
      } else if (alignMap[cmd]) {
        btn.classList.toggle('active', editor.isActive({ textAlign: alignMap[cmd] }));
      }
    });
  }
  // ─── 빈 단락 보존 헬퍼 ───
  // tiptap-markdown@0.8.10 한계: 연속된 빈 paragraph 들이 markdown export 시
  // 단일 paragraph break (\n\n) 으로 collapse 됨. 사용자가 의도한 시각적 여백이 사라짐.
  //
  // 우회: editor.getJSON() 으로 빈 paragraph 위치를 분석한 뒤
  //       markdown 출력의 해당 위치에 &nbsp; 단락을 직접 삽입.
  //       PHP 측 hp_preserve_blank_lines 와 같은 표현 (&nbsp; 단락) 을 만들어
  //       Parsedown 이 빈 <p> 로 렌더해서 시각적 여백이 살아남.
  function getMarkdownPreservingBlanks() {
    var md = editor.storage.markdown.getMarkdown();
    var json = editor.getJSON();
    var topNodes = (json && json.content) || [];

    // 빈 paragraph 없으면 즉시 통과 (fast path — 평소 키 입력마다 호출되니 부담 최소화)
    var hasEmpty = false;
    for (var i = 0; i < topNodes.length; i++) {
      var n = topNodes[i];
      if (n.type === 'paragraph' && (!n.content || n.content.length === 0)) { hasEmpty = true; break; }
    }
    if (!hasEmpty) return md;

    // 코드블록 보호 — 내부의 \n\n 이 split 영향 받지 않게 placeholder 로 추출
    var codeBlocks = [];
    var protectedMd = md.replace(/```[\s\S]*?```/g, function (m) {
      var key = '\x01CODE' + codeBlocks.length + '\x02';
      codeBlocks.push(m);
      return key;
    });

    // 빈 string split 결과는 [''] 이 되니 [] 로 정규화
    var parts = protectedMd === '' ? [] : protectedMd.split(/\n{2,}/);

    // JSON 분석: non-empty 블록 사이에 빈 paragraph 가 몇 개 있었는지 기록
    // blankCounts[k] = parts[k] 앞에 삽입할 빈 단락 갯수
    var blankCounts = {};
    var nonEmptyCount = 0;
    var blankRun = 0;
    for (var i = 0; i < topNodes.length; i++) {
      var n = topNodes[i];
      var isEmpty = n.type === 'paragraph' && (!n.content || n.content.length === 0);
      if (isEmpty) {
        blankRun++;
      } else {
        if (blankRun > 0) blankCounts[nonEmptyCount] = blankRun;
        nonEmptyCount++;
        blankRun = 0;
      }
    }
    // 문서 끝의 trailing blanks
    if (blankRun > 0) blankCounts[nonEmptyCount] = blankRun;

    // 재조립: 각 part 앞에 필요하면 &nbsp; 단락 삽입
    var newParts = [];
    for (var k = 0; k <= parts.length; k++) {
      if (blankCounts[k]) {
        for (var j = 0; j < blankCounts[k]; j++) newParts.push('&nbsp;');
      }
      if (k < parts.length) newParts.push(parts[k]);
    }

    // 코드블록 복원
    var result = newParts.join('\n\n');
    result = result.replace(/\x01CODE(\d+)\x02/g, function (_, idx) {
      return codeBlocks[parseInt(idx, 10)];
    });
    return result;
  }

  editor.on('selectionUpdate', updateActive);
  editor.on('update', function () {
    updateActive();
    // 에디터 내용 변경마다 textarea 에 실시간 동기화 —
    // SPA 가 capture phase 에서 form 을 가로채도 항상 최신 값이 들어 있음.
    // (SPA 는 document level capture, Tiptap submit listener 는 form level capture 이므로
    //  document → form 순서상 SPA 가 먼저 발사 → 여기서 미리 빈 단락 보존된 값이 들어가 있어야 함)
    input.value = getMarkdownPreservingBlanks();
  });

  // 초기 1회도 동기화 (사용자 입력 없이 바로 submit 하는 경우 대비)
  input.value = getMarkdownPreservingBlanks();

  // form submit 직전에 한 번 더 (안전망)
  var form = wrap.closest('form');
  if (form) {
    form.addEventListener('submit', function () {
      input.value = getMarkdownPreservingBlanks();
    }, true);
  }
})();
</script>
