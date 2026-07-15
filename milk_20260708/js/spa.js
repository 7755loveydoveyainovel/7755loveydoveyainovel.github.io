/* ═══════════════════════════════════════════════════════════════
 *  js/spa.js — SPA 네비게이션 (cross-dissolve 패턴)
 *
 *  부드러움의 핵심:
 *  1. 클릭 → 옛 컨텐츠 fade-out (opacity 1 → 0)
 *  2. 동시에 fetch (병렬)
 *  3. 둘 다 끝나면 swap (보이지 않는 상태)
 *  4. 새 컨텐츠 fade-in (opacity 0 → 1)
 *
 *  swap 이 항상 invisible 상태에서 일어나기 때문에 시각적으로 깜빡임 없음.
 *  fade-out 과 fetch 가 병렬이라 체감 속도는 거의 그대로.
 * ═══════════════════════════════════════════════════════════════ */

(function () {
  'use strict';

  var HP_BASE = (window.HP_BASE || '') + '/';
  var FADE_OUT = 180;   // ms — 옛 컨텐츠 사라지는 시간
  var FADE_IN  = 220;   // ms — 새 컨텐츠 등장하는 시간
  var contentEl = null;

  function init() {
    contentEl = document.querySelector('.content-inner');
    if (!contentEl) return;

    var initialUrl = location.pathname + location.search;
    history.replaceState({ url: initialUrl }, '', HP_BASE);

    document.addEventListener('click', handleClick, true);
    document.addEventListener('submit', handleSubmit, true);
    window.addEventListener('popstate', handlePopstate);
  }

  // ─── 링크 클릭 가로채기 ───
  function handleClick(e) {
    var a = e.target.closest && e.target.closest('a');
    if (!a) return;

    var href = a.getAttribute('href');
    if (!href) return;
    if (href.charAt(0) === '#') return;
    if (href.indexOf('mailto:') === 0) return;
    if (href.indexOf('tel:') === 0) return;
    if (href.indexOf('javascript:') === 0) return;
    if (a.target === '_blank') return;
    if (a.hasAttribute('download')) return;
    if (a.dataset.noSpa !== undefined) return;
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    if (e.button !== 0) return;

    var url;
    try { url = new URL(href, location.href); }
    catch (err) { return; }
    if (url.origin !== location.origin) return;

    var p = url.pathname;
    if (p.indexOf('/admin/') !== -1) return;
    if (p.indexOf('/install.php') !== -1) return;
    if (p.indexOf('/share.php') !== -1) return;

    e.preventDefault();
    navigate(url.pathname + url.search);
  }

  // ─── 폼 submit 가로채기 ───
  function handleSubmit(e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM') return;
    if (form.dataset.noSpa !== undefined) return;

    var stateUrl = (history.state && history.state.url) || HP_BASE;
    var action = form.getAttribute('action') || stateUrl;

    var url;
    try { url = new URL(action, location.href); }
    catch (err) { return; }
    if (url.origin !== location.origin) return;

    var p = url.pathname;
    if (p.indexOf('/admin/') !== -1) return;
    if (p.indexOf('/install.php') !== -1) return;

    e.preventDefault();

    var method = (form.getAttribute('method') || 'get').toLowerCase();
    var targetUrl = url.pathname + url.search;

    if (method === 'post') {
      submitPost(targetUrl, new FormData(form));
    } else {
      var params = new URLSearchParams(new FormData(form));
      var qs = params.toString();
      var sep = url.search ? '&' : '?';
      navigate(targetUrl + (qs ? sep + qs : ''));
    }
  }

  function handlePopstate(e) {
    var url = (e.state && e.state.url) || HP_BASE;
    crossDissolve(fetchHtml(url), url, false);
  }

  // ─── GET 네비게이션 ───
  function navigate(url) {
    history.pushState({ url: url }, '', HP_BASE);
    crossDissolve(fetchHtml(url), url, false);
  }

  // ─── POST 폼 ───
  function submitPost(url, formData) {
    var fetchPromise = fetch(addAjaxParam(url), {
      method: 'POST',
      body: formData,
      redirect: 'follow',
      credentials: 'same-origin'
    }).then(function (r) {
      if (r.url) {
        var u = new URL(r.url);
        var finalUrl = stripAjaxParam(u.pathname + u.search);
        history.pushState({ url: finalUrl }, '', HP_BASE);
      }
      return r.text();
    });
    crossDissolve(fetchPromise, url, true);
  }

  // ─── fetch GET ───
  function fetchHtml(url) {
    return fetch(addAjaxParam(url), {
      redirect: 'follow',
      credentials: 'same-origin'
    }).then(function (r) { return r.text(); });
  }

  // ─── Cross-dissolve: fade-out + fetch 병렬 → swap → fade-in ───
  function crossDissolve(fetchPromise, url, isPost) {
    if (!contentEl) return;

    // Phase 1: fade-out 시작 (병렬로 fetch 도 진행 중)
    contentEl.style.transition = 'opacity ' + FADE_OUT + 'ms ease';
    contentEl.style.opacity = '0';

    var fadeOutPromise = new Promise(function (resolve) {
      setTimeout(resolve, FADE_OUT);
    });

    // Phase 2: 둘 다 끝나길 기다림
    Promise.all([fadeOutPromise, fetchPromise])
      .then(function (results) {
        var html = results[1];
        // Phase 3: invisible 상태에서 swap
        applySwap(html);
        // Phase 4: fade-in
        // requestAnimationFrame 두 번 — DOM 안정 후 transition 트리거
        requestAnimationFrame(function () {
          requestAnimationFrame(function () {
            contentEl.style.transition = 'opacity ' + FADE_IN + 'ms ease';
            contentEl.style.opacity = '1';
          });
        });
      })
      .catch(function (err) {
        console.error('SPA 실패:', err);
        contentEl.style.opacity = '1';
      });
  }

  // ─── 콘텐츠 교체 (invisible 상태에서 호출됨) ───
  function applySwap(html) {
    var temp = document.createElement('div');
    temp.innerHTML = html;

    // 메타 추출
    var meta = temp.querySelector('[data-spa-meta]');
    var page = '';
    var slug = '';
    if (meta) {
      if (meta.dataset.title) document.title = meta.dataset.title;
      page = meta.dataset.page || '';
      slug = meta.dataset.slug || '';
      meta.remove();
    }

    // sidebar / drawer / tabbar 갈아끼우기 — 자동 섹션 전환된 새 메뉴 반영
    // (SPA 응답이 아닌 경우 template 이 없으니 안전하게 skip)
    swapPartial(temp, '[data-spa-sidebar]', '.sidebar');
    swapPartial(temp, '[data-spa-drawer]',  '.m-drawer');
    swapPartial(temp, '[data-spa-tabbar]',  '.m-tabbar');

    // footer 는 .content-inner 외부에 있으므로 swap 영향 없음
    contentEl.innerHTML = temp.innerHTML;

    // <script> 태그 재실행 (innerHTML 은 script 를 실행 안 시킴)
    contentEl.querySelectorAll('script').forEach(function (oldScript) {
      reExecuteScript(oldScript);
    });

    updateActive(page, slug);

    // 새 콘텐츠의 [title] 속성을 커스텀 툴팁으로 변환
    if (window.hpInitTooltips) window.hpInitTooltips(contentEl);

    // 모바일 드로어 닫기
    var drawer = document.querySelector('.m-drawer');
    if (drawer) drawer.classList.remove('open');
    document.body.classList.remove('drawer-open');

    window.scrollTo(0, 0);
  }

  // ─── 부분 갈아끼우기 (sidebar / drawer / tabbar) ───
  // template 안의 새 영역을 꺼내서 기존 영역과 통째로 교체.
  // 새 영역 안의 <script> 도 다시 실행해서 섹션 토글 같은 인라인 핸들러가 작동하도록 함.
  function swapPartial(parsedRoot, templateSelector, targetSelector) {
    var tmpl = parsedRoot.querySelector(templateSelector);
    var oldEl = document.querySelector(targetSelector);
    if (!tmpl || !oldEl) {
      if (tmpl) tmpl.remove();
      return;
    }
    // <template> 의 content 는 .innerHTML 로 꺼내서 다시 파싱
    var holder = document.createElement('div');
    holder.innerHTML = tmpl.innerHTML;
    var newEl = holder.firstElementChild;
    tmpl.remove();
    if (!newEl) return;

    oldEl.parentNode.replaceChild(newEl, oldEl);

    // 새 영역의 script 재실행
    newEl.querySelectorAll('script').forEach(function (oldScript) {
      reExecuteScript(oldScript);
    });

    // 새 영역의 [title] 도 툴팁 변환
    if (window.hpInitTooltips) window.hpInitTooltips(newEl);
  }


  // ─── script element 안전한 재실행 ───
  // attribute 무차별 복사 시 invalid 속성으로 인한 setAttribute throw 가능성을 피함.
  // type 같은 표준 속성만 전달, 나머지는 무시. error 도 격리해서 다른 script 실행 막지 않음.
  function reExecuteScript(oldScript) {
    try {
      var newScript = document.createElement('script');
      // src 가 있으면 src 만, 없으면 inline textContent
      if (oldScript.src) {
        newScript.src = oldScript.src;
        if (oldScript.async)   newScript.async   = true;
        if (oldScript.defer)   newScript.defer   = true;
        if (oldScript.crossOrigin) newScript.crossOrigin = oldScript.crossOrigin;
      } else {
        newScript.textContent = oldScript.textContent;
      }
      // type 속성 보존 (text/javascript 가 default 라 굳이 안 해도 되지만 명시적)
      if (oldScript.type && oldScript.type !== 'text/javascript') {
        newScript.type = oldScript.type;
      }
      oldScript.parentNode.replaceChild(newScript, oldScript);
    } catch (err) {
      console.error('[spa] reExecuteScript error:', err, oldScript);
    }
  }

  // ─── ?ajax=1 추가 / 제거 ───
  function addAjaxParam(url) {
    if (url.indexOf('ajax=1') !== -1) return url;
    return url + (url.indexOf('?') !== -1 ? '&' : '?') + 'ajax=1';
  }
  function stripAjaxParam(url) {
    return url
      .replace(/([?&])ajax=1(&|$)/, function (_, pre, post) {
        return post === '&' ? pre : (pre === '?' ? '' : '');
      })
      .replace(/[?&]$/, '');
  }

  // ─── 사이드바 / 드로어 active ───
  function updateActive(page, slug) {
    var navLinks = document.querySelectorAll('.sidebar a, .m-drawer a, .m-tabbar a');
    navLinks.forEach(function (a) { a.classList.remove('active'); });

    // URLSearchParams 로 정확히 매치 — 'slug=test' 가 'slug=test2' 의 substring 으로
    // 잘못 매치되거나, 같은 slug 의 다른 게시판 링크가 모두 active 되는 문제를 방지
    navLinks.forEach(function (a) {
      var href = a.getAttribute('href') || '';
      // 어드민 링크는 매칭에서 제외 (절대 active 안 됨)
      if (href.indexOf('/admin') !== -1) return;

      var qs;
      try { qs = new URL(href, location.href).searchParams; }
      catch (e) { return; }

      var hrefSlug = qs.get('slug');
      var hrefPage = qs.get('p');
      var hrefBoId = qs.get('bo_id');

      if (slug) {
        // slug 가 정확히 일치하는 board/view 링크만 active
        if (hrefSlug === slug && (hrefPage === 'board' || hrefPage === 'view' || !hrefPage)) {
          a.classList.add('active');
        }
      } else if (page === 'home') {
        // 홈은 href 가 루트이거나 ?p=home 일 때만
        var path = href.split('?')[0].replace(/\/$/, '');
        var base = HP_BASE.replace(/\/$/, '');
        if (path === base && !hrefPage || hrefPage === 'home') {
          a.classList.add('active');
        }
      } else if (page) {
        // 다른 페이지 (guestbook 등) — p 파라미터 정확 매치
        if (hrefPage === page) a.classList.add('active');
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

/* ─── 공유 URL 클립보드 복사 + 토스트 (전역 함수) ─── */
window.spaShare = function (url, btn) {
  function showToast(msg) {
    var t = document.createElement('div');
    t.className = 'spa-toast';
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () {
      t.classList.remove('show');
      setTimeout(function () { t.remove(); }, 300);
    }, 1800);
  }

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(
      function () { showToast('주소가 복사되었어요'); },
      function () { showToast(url); }
    );
  } else {
    // 구형 브라우저 fallback
    var ta = document.createElement('textarea');
    ta.value = url;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); showToast('주소가 복사되었어요'); }
    catch (e) { showToast(url); }
    ta.remove();
  }
};
