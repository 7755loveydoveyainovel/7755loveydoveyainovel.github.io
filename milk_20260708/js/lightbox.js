/* ═══════════════════════════════════════════════════════════════
 *  js/lightbox.js — 이미지 모달 (라이트박스)
 *
 *  사용법:
 *  1) HTML 에서 [data-lightbox] 속성 가진 컨테이너 안의 img 클릭 시 자동 모달
 *     <div class="ch-log-thumb" data-lightbox><img src="..."></div>
 *  2) 직접 호출: window.openImageModal(src, alt)
 *
 *  특징:
 *  - 모달 element 는 lazy 생성 (첫 호출 시점)
 *  - document delegation — SPA 갈아끼워도 새 DOM 자동 처리
 *  - 부모 a 태그 navigate 차단 (이미지 보기 우선)
 *  - 배경 클릭 / × 버튼 / ESC 로 닫기
 * ═══════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var modal = null;

  function getModal() {
    if (modal) return modal;
    modal = document.createElement('div');
    modal.className = 'hp-img-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML =
      '<button type="button" class="hp-img-modal-close" aria-label="닫기">×</button>' +
      '<img src="" alt="">';
    document.body.appendChild(modal);

    modal.addEventListener('click', function (e) {
      // 배경 또는 닫기 버튼 — 이미지 자체 클릭은 닫지 않음
      if (e.target === modal || e.target.classList.contains('hp-img-modal-close')) {
        close();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('open')) close();
    });

    return modal;
  }

  function close() {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  window.openImageModal = function (src, alt) {
    if (!src) return;
    var m = getModal();
    var img = m.querySelector('img');
    img.src = src;
    img.alt = alt || '';
    m.classList.add('open');
    m.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  };

  // ─── 자동 적용 (document delegation) ───
  // [data-lightbox] 영역 안의 img 클릭 → 모달
  // capture phase 로 등록 — SPA 의 link click 가로채기보다 먼저 동작
  document.addEventListener('click', function (e) {
    var img = e.target.closest && e.target.closest('img');
    if (!img) return;
    var area = img.closest('[data-lightbox]');
    if (!area) return;

    // 부모 a 태그가 있으면 navigate 차단 (이미지 보기 우선)
    e.preventDefault();
    e.stopPropagation();

    window.openImageModal(img.currentSrc || img.src, img.alt);
  }, true);
})();
