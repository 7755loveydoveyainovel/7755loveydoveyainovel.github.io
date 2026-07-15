/**
 * admin/script.js — 관리자 페이지 인터랙션
 *
 * - 메인 페이지 블록 드래그 정렬 (Sortable)
 * - 블록 노출 토글 (AJAX)
 * - 블록 옵션 폼 펼치기/접기
 * - 토스트 알림
 * - 라디오 → label 활성화 (테마 선택)
 */

(function () {
  // ─── CSRF 토큰 헬퍼 ───
  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }

  // ─── 토스트 알림 ───
  function showFlash(msg) {
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;left:50%;bottom:32px;transform:translateX(-50%);' +
      'background:var(--ink);color:var(--paper);padding:12px 22px;border-radius:999px;' +
      'font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.18);z-index:9999;' +
      'opacity:0;transition:opacity .2s;pointer-events:none;';
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.style.opacity = '1'; });
    setTimeout(function () {
      t.style.opacity = '0';
      setTimeout(function () { t.remove(); }, 250);
    }, 1500);
  }
  window.showFlash = showFlash;

  // ─── 메인 페이지 블록 정렬 ───
  if (window.Sortable) {
    var blockList = document.getElementById('blockList');
    if (blockList) {
      Sortable.create(blockList, {
        handle: '.handle',
        animation: 150,
        ghostClass: 'dragging',
        onEnd: function () {
          var ids = [];
          blockList.querySelectorAll('.sortable-item').forEach(function (el) {
            if (el.dataset.id) ids.push(el.dataset.id);
          });
          var fd = new FormData();
          fd.append('action', 'reorder');
          fd.append('_csrf', csrfToken());
          ids.forEach(function (id) { fd.append('ids[]', id); });
          fetch('?section=mainpage', { method: 'POST', body: fd })
            .then(function () { showFlash('순서가 저장되었어요'); });
        }
      });
    }
  }

  // ─── 블록 노출 토글 ───
  window.toggleBlock = function (id) {
    var fd = new FormData();
    fd.append('action', 'toggle_block');
    fd.append('mb_id', id);
    fd.append('_csrf', csrfToken());
    fetch('?section=mainpage', { method: 'POST', body: fd })
      .then(function () { showFlash('변경되었어요'); });
  };

  // ─── 블록 옵션 폼 펼치기/접기 ───
  window.toggleOptionsForm = function (id) {
    var el = document.getElementById('opts-' + id);
    if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
  };

  // ─── 테마 라디오 → 활성화 표시 + 색상 피커 자동 채움 ───
  document.querySelectorAll('input[name="theme"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      document.querySelectorAll('.theme-opt').forEach(function (o) { o.classList.remove('active'); });
      radio.closest('.theme-opt').classList.add('active');

      // 프리셋의 색을 color input 에 자동 반영 (커스텀 색상 섹션이 있을 때)
      if (window.themePresets && window.themePresets[radio.value]) {
        var preset = window.themePresets[radio.value];
        document.querySelectorAll('input[type="color"][data-var]').forEach(function (input) {
          var key = input.dataset.var;
          if (preset[key]) input.value = preset[key];
        });
      }
    });
  });
  // ─── 범용 sortable: data-reorder-section 속성이 있는 모든 .sortable-list 자동 처리 ───
  // 사용:
  //   <div class="sortable-list" data-reorder-section="menu" data-reorder-action="reorder_groups">
  //     <div class="sortable-item" data-id="3"> ... </div>
  //   </div>
  if (window.Sortable) {
    document.querySelectorAll('.sortable-list[data-reorder-section]').forEach(function (list) {
      var section = list.dataset.reorderSection;
      var action  = list.dataset.reorderAction || 'reorder';
      Sortable.create(list, {
        handle: '.handle',
        animation: 150,
        ghostClass: 'dragging',
        onEnd: function () {
          var ids = [];
          // 직접 자식만 — nested sortable-list (그룹 안 게시판) 의 자식은 별도 처리
          list.querySelectorAll(':scope > .sortable-item').forEach(function (el) {
            if (el.dataset.id) ids.push(el.dataset.id);
          });
          var fd = new FormData();
          fd.append('action', action);
          fd.append('_csrf', csrfToken());
          ids.forEach(function (id) { fd.append('ids[]', id); });
          fetch('?section=' + section, { method: 'POST', body: fd })
            .then(function () { showFlash('순서가 저장되었어요'); });
        }
      });
    });
  }

})();
