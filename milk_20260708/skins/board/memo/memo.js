/**
 * skins/board/memo/memo.js — 인라인 작성/수정 폼 + 임베드 widget 동적 로드
 */
(function () {
  // ─── 트위터 widget 동적 로드 ───
  function loadTwitterWidgets() {
    if (!document.querySelector('.memo-tweet[data-tweet-id]')) return;

    if (window.twttr && window.twttr.widgets && window.twttr.widgets.createTweet) {
      processTweets();
      return;
    }
    if (document.querySelector('script[data-memo-twttr]')) {
      return;
    }
    var s = document.createElement('script');
    s.src   = 'https://platform.twitter.com/widgets.js';
    s.async = true;
    s.charset = 'utf-8';
    s.setAttribute('data-memo-twttr', '1');
    s.onload  = function () {
      processTweets();
    };
    s.onerror = function (e) { console.error('[memo] widgets.js failed', e); };
    document.body.appendChild(s);
  }

  // ─── 각 컨테이너에 createTweet 명시 호출 ───
  function processTweets() {
    if (!(window.twttr && window.twttr.widgets && window.twttr.widgets.createTweet)) {
      console.warn('[memo] twttr.widgets.createTweet not available');
      return;
    }
    document.querySelectorAll('.memo-tweet[data-tweet-id]').forEach(function (el) {
      if (el.dataset.processed === '1') return;
      el.dataset.processed = '1';
      var id  = el.dataset.tweetId;
      var url = el.dataset.tweetUrl;
      window.twttr.widgets.createTweet(id, el, {
        theme: 'light',
        dnt: true,
        conversation: 'none',
        align: 'center'
      }).then(function (rendered) {
        if (rendered) {
          // 로딩 placeholder 제거
          var loading = el.querySelector('.memo-tweet-loading');
          if (loading) loading.remove();
        } else {
          console.error('[memo] tweet failed to render', id);
          el.innerHTML = '<a class="memo-tweet-fallback" href="' + url +
            '" target="_blank" rel="noopener noreferrer">트윗 열기 →</a>';
        }
      }).catch(function (err) {
        console.error('[memo] createTweet error', err);
        el.innerHTML = '<a class="memo-tweet-fallback" href="' + url +
          '" target="_blank" rel="noopener noreferrer">트윗 열기 →</a>';
      });
    });
  }

  // ─── 인스타 widget 동적 로드 ───
  function loadInstagramEmbeds() {
    if (window.instgrm && window.instgrm.Embeds && window.instgrm.Embeds.process) {
      window.instgrm.Embeds.process();
      return;
    }
    if (document.querySelector('script[data-memo-instgrm]')) return;
    var s = document.createElement('script');
    s.src   = 'https://www.instagram.com/embed.js';
    s.async = true;
    s.setAttribute('data-memo-instgrm', '1');
    document.body.appendChild(s);
  }

  loadTwitterWidgets();
  loadInstagramEmbeds();

  // ─── 인라인 작성/수정 폼 ───
  var toggle = document.getElementById('memoNewToggle');
  var form   = document.getElementById('memoNewForm');
  var cancel = document.getElementById('memoNewCancel');
  if (!form) return;

  function focusFirst() {
    var first = form.querySelector('.memo-fields:not([hidden]) textarea, .memo-fields:not([hidden]) input[type=text]');
    if (first) first.focus();
  }
  function open() {
    form.hidden = false;
    if (toggle) toggle.classList.add('active');
    focusFirst();
  }
  function close() {
    form.hidden = true;
    if (toggle) toggle.classList.remove('active');
  }

  if (!form.hidden && toggle) toggle.classList.add('active');

  if (toggle) {
    toggle.addEventListener('click', function () {
      if (form.hidden) open();
      else close();
    });
  }
  if (cancel && cancel.tagName === 'BUTTON') {
    cancel.addEventListener('click', close);
  }

  form.querySelectorAll('.memo-type-pill input[type=radio]').forEach(function (r) {
    r.addEventListener('change', function () {
      form.querySelectorAll('.memo-type-pill').forEach(function (p) {
        p.classList.toggle('active', p.querySelector('input').checked);
      });
      form.querySelectorAll('.memo-fields').forEach(function (f) {
        f.hidden = (f.dataset.type !== r.value);
      });
      focusFirst();
    });
  });

  // pill (label) 클릭 시 명시적으로 라디오 checked + change 이벤트 강제 — 일부 환경에서
  // label → input 자동 활성화가 불안정한 경우를 대비
  form.querySelectorAll('.memo-type-pill').forEach(function (pill) {
    pill.addEventListener('click', function (e) {
      var radio = pill.querySelector('input[type=radio]');
      if (!radio) return;
      // 다른 라디오 클릭 차단 방지 — 이미 default 동작이 있으니 한 tick 뒤에
      setTimeout(function () {
        if (!radio.checked) {
          radio.checked = true;
          radio.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }, 0);
    });
  });

  // submit 직전에 type 동기화 — 어떤 이유로든 라디오 상태가 시각과 어긋나면
  // 시각적으로 active 인 pill 의 type 으로 강제. 추가로 확실히 hidden input 도 보냄.
  form.addEventListener('submit', function (e) {
    var activePill = form.querySelector('.memo-type-pill.active');
    var checked    = form.querySelector('.memo-type-pill input[type=radio]:checked');
    var visualType = activePill ? activePill.querySelector('input').value : null;
    var dataType   = checked ? checked.value : null;

    if (visualType && visualType !== dataType) {
      // 시각이 image 인데 라디오는 quote 인 상태 — 시각을 정답으로
      console.warn('[memo] type mismatch — fixing', { visualType: visualType, dataType: dataType });
      form.querySelectorAll('.memo-type-pill input[type=radio]').forEach(function (r) {
        r.checked = (r.value === visualType);
      });
    }

    // hidden input 으로 한 번 더 보장 + 라디오는 disabled 로 만들어 POST 에서 제외.
    // (라디오와 hidden 같은 name 충돌 시 PHP 가 라디오 값을 받는 경우 대비)
    var finalType = visualType || dataType || 'quote';

    form.querySelectorAll('.memo-type-pill input[type=radio]').forEach(function (r) {
      r.disabled = true;
    });

    var existingHidden = form.querySelector('input[type=hidden][name=memo_type]');
    if (existingHidden) existingHidden.remove();
    var hidden = document.createElement('input');
    hidden.type  = 'hidden';
    hidden.name  = 'memo_type';
    hidden.value = finalType;
    form.appendChild(hidden);

  });

  form.querySelectorAll('input[type=file]').forEach(function (f) {
    f.addEventListener('change', function () {
      var label = f.closest('label');
      if (!label) return;
      if (f.files[0]) {
        label.classList.add('has-file');
        label.setAttribute('title', f.files[0].name);
      } else {
        label.classList.remove('has-file');
        label.removeAttribute('title');
      }
    });
  });
})();
