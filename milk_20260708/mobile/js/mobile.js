/**
 * mobile/js/mobile.js — 모바일 인터랙션
 *
 *  - 더보기 탭 → 드로어 (전체) 열기
 *  - 섹션 탭 → 드로어 (해당 섹션 + 공용만) 열기
 *  - 닫기 버튼 / ESC → 드로어 닫기
 *  - 드로어 내 링크 클릭 시 자동 닫기
 *  - 로그인 폼 토글
 */

(function () {
  // SPA 가 drawer 를 갈아끼울 수 있어 모든 reference 는 매번 lookup.
  // 초기 존재 검증하지 않음 — document delegation 은 click 시점에 element 를 찾으므로
  // init 순간 drawer 가 없어도(일부 모바일 브라우저에서 DOM 파싱 타이밍 이슈) 문제 없이 작동.
  function getDrawer()   { return document.getElementById('mDrawer'); }
  function getOpenBtn()  { return document.getElementById('moreTabBtn'); }
  function getCloseBtn() { return document.getElementById('mDrawerClose'); }
  function getTitleEl()  { return document.getElementById('mDrawerTitle'); }

  /**
   * 드로어 열기. section 인자가 있으면 해당 섹션 + 공용만 표시,
   * 없거나 빈 문자열이면 전체 표시 (더보기 모드).
   */
  function openDrawer(section) {
    var drawer = getDrawer();
    if (!drawer) return;
    var titleEl = getTitleEl();

    // 일반 메뉴 view 로 (sections view 가 떠 있으면 닫음)
    var sectionsView = document.getElementById('mDrawerSections');
    var bodyView     = document.getElementById('mDrawerBody');
    if (sectionsView) sectionsView.hidden = true;
    if (bodyView)     bodyView.hidden = false;

    var groups = drawer.querySelectorAll('.m-group');
    groups.forEach(function (g) {
      var s = g.getAttribute('data-section') || '0';
      var show = !section || s === '0' || s === section;
      g.classList.toggle('m-group--hidden', !show);
    });

    // 헤더 제목
    if (titleEl) {
      if (section) {
        var tab = document.querySelector('.m-tab-section[data-section="' + section + '"] .lbl');
        titleEl.textContent = tab ? tab.textContent : '메뉴';
      } else {
        titleEl.textContent = '전체 메뉴';
      }
    }

    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    var drawer = getDrawer();
    if (!drawer) return;
    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  // inline onclick fallback — 일부 모바일 브라우저(구 Samsung Internet 등)에서
  // document delegation 이 작동 안 할 경우를 대비한 이중 안전장치
  window.hpOpenMore = function (btn) {
    var secCount = parseInt(btn.getAttribute('data-section-count') || '0', 10);
    if (secCount >= 2) openSectionPicker();
    else openDrawer('');
  };

  // closest 헬퍼 — target 이 텍스트노드(nodeType !== 1) 일 때나 구형 브라우저 대비
  function closestSafe(node, sel) {
    if (!node) return null;
    // 텍스트노드면 parent 로 올라감
    if (node.nodeType && node.nodeType !== 1) node = node.parentElement;
    if (!node) return null;
    if (node.closest) return node.closest(sel);
    // 폴리필
    var matches = node.matches || node.msMatchesSelector || node.webkitMatchesSelector;
    if (!matches) return null;
    while (node && node.nodeType === 1) {
      if (matches.call(node, sel)) return node;
      node = node.parentElement;
    }
    return null;
  }

  // 더보기 탭 — sections 2+ 면 섹션 선택 view, 아니면 전체 메뉴 drawer
  // document delegation 으로 — DOM 이 갈아끼워져도 동작
  document.addEventListener('click', function (e) {
    // 더보기 버튼
    var moreBtn = closestSafe(e.target, '#moreTabBtn');
    if (moreBtn) {
      var secCount = parseInt(moreBtn.getAttribute('data-section-count') || '0', 10);
      if (secCount >= 2) {
        openSectionPicker();
      } else {
        openDrawer('');
      }
      return;
    }

    // 섹션 탭 (모바일 탭바)
    var secTab = closestSafe(e.target, '.m-tab-section');
    if (secTab) {
      var sId = secTab.getAttribute('data-section') || '';
      setSectionCookie(sId);
      openDrawer(sId);
      return;
    }

    // 섹션 선택 (drawer 안) — 픽한 섹션의 메뉴를 같은 drawer 안에서 표시
    // (페이지 리로드 안 함. 사용자가 메뉴 항목을 클릭하는 시점에 그 섹션 컨텍스트로 이동)
    var pickBtn = closestSafe(e.target, '.m-section-pick');
    if (pickBtn) {
      var pId = pickBtn.getAttribute('data-section-id') || '';
      setSectionCookie(pId);
      openDrawer(pId);
      return;
    }

    // 로그인 폼 토글 — drawer 가 SPA 로 교체될 수 있어 document delegation 사용
    var loginToggle = closestSafe(e.target, '.m-login-toggle');
    if (loginToggle) {
      var pop = loginToggle.parentElement && loginToggle.parentElement.querySelector('.m-login-pop');
      if (pop) pop.style.display = pop.style.display === 'none' ? 'block' : 'none';
      return;
    }

    // drawer 안 링크 클릭 시 자동 닫기 (drawer 가 SPA 교체돼도 작동하도록 delegation)
    var drawerLink = closestSafe(e.target, '.m-drawer a');
    if (drawerLink) {
      setTimeout(closeDrawer, 50);
      // return 안 함 — SPA 가 링크 처리 계속 진행해야 함
    }
  });

  // closeBtn 도 SPA 갈아끼움 후 무효화될 수 있어 document delegation 으로
  document.addEventListener('click', function (e) {
    if (closestSafe(e.target, '#mDrawerClose')) {
      closeDrawer();
    }
  });

  // ─── 섹션 선택 view ───
  function openSectionPicker() {
    var drawer = getDrawer();
    if (!drawer) return;
    var titleEl = getTitleEl();
    var sectionsView = document.getElementById('mDrawerSections');
    var bodyView     = document.getElementById('mDrawerBody');
    if (sectionsView) sectionsView.hidden = false;
    if (bodyView)     bodyView.hidden = true;
    if (titleEl)      titleEl.textContent = '섹션 선택';

    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  // ─── 현재 섹션 / 쿠키 ───
  function currentSection() {
    var m = document.cookie.match(/(?:^|;\s*)hp_section=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }
  function setSectionCookie(secId) {
    var maxAge = 60 * 60 * 24 * 30;
    document.cookie = 'hp_section=' + encodeURIComponent(secId)
      + '; max-age=' + maxAge + '; path=/; SameSite=Lax';
  }

  // ESC 키 — drawer 동적 lookup
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var drawer = getDrawer();
    if (drawer && drawer.classList.contains('open')) closeDrawer();
  });

  // 드로어 내 링크 클릭 자동 닫기 / 로그인 토글은 위 document delegation 에서 처리
})();
