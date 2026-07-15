<?php
/**
 * skins/main/default/index.php — 1열 스택 스킨 진입점
 *
 * 책임:
 *  - 스킨 전용 CSS 로드
 *  - 활성 블록을 mb_order 순서대로 렌더
 *  - 본인 배너 클릭 → 주소 복사 스크립트
 */
?>
<link rel="stylesheet" href="<?= HP_BASE ?>/skins/main/default/style.css">

<div class="home-wrap">
  <div class="home-stack">
    <?php hp_render_blocks_from(__DIR__); ?>
  </div>
</div>

<script>
// 본인 배너 클릭 → 주소 복사 / 메일 아이콘 클릭 → 메일 주소 복사
(function(){
  function copyText(text, msg) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function(){ showToast(msg); });
    } else {
      var ta = document.createElement('textarea');
      ta.value = text; document.body.appendChild(ta);
      ta.select(); document.execCommand('copy');
      document.body.removeChild(ta);
      showToast(msg);
    }
  }

  document.querySelectorAll('.own-banner[data-url]').forEach(function(el){
    el.addEventListener('click', function(e){
      e.preventDefault();
      var url = el.dataset.url;
      if (url) copyText(url, '배너 주소가 복사되었어요!');
    });
  });

  document.querySelectorAll('.social-mail[data-mail]').forEach(function(el){
    el.addEventListener('click', function(e){
      e.preventDefault();
      var mail = el.dataset.mail;
      if (mail) copyText(mail, '메일 주소가 복사되었어요! (' + mail + ')');
    });
  });

  function showToast(msg) {
    var t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;left:50%;bottom:32px;transform:translateX(-50%);'
      + 'background:var(--ink);color:var(--paper);padding:12px 22px;border-radius:999px;'
      + 'font-size:13px;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.18);'
      + 'z-index:9999;opacity:0;transition:opacity .2s ease;';
    document.body.appendChild(t);
    requestAnimationFrame(function(){ t.style.opacity = '1'; });
    setTimeout(function(){
      t.style.opacity = '0';
      setTimeout(function(){ t.remove(); }, 250);
    }, 1800);
  }
})();
</script>
