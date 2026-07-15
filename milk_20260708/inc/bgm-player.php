<?php
/**
 * inc/bgm-player.php — 사이트 BGM 플레이어 (YouTube)
 *
 * 어드민 → 사이트 설정 → BGM 에서 유튜브 URL 을 등록하면 사이트 우측 하단에
 * 작은 음악 버튼이 떠. 클릭하면 hidden iframe 으로 재생.
 *
 * SPA 의 .content-inner 바깥에 렌더돼서 페이지 이동에도 음악이 끊기지 않음.
 */

$bgm_url = trim(hp_config('bgm_url', ''));
if ($bgm_url === '') return;

// URL 에서 video_id / list_id 추출
$video_id = '';
$list_id  = '';
if (preg_match('#youtu\.be/([a-zA-Z0-9_-]{11})#', $bgm_url, $m)) $video_id = $m[1];
if (preg_match('#[?&]v=([a-zA-Z0-9_-]{11})#', $bgm_url, $m))    $video_id = $m[1];
if (preg_match('#[?&]list=([a-zA-Z0-9_-]+)#', $bgm_url, $m))     $list_id  = $m[1];

if ($video_id === '' && $list_id === '') return;

$autoplay = hp_config('bgm_autoplay', '0') === '1' ? 1 : 0;
$loop     = hp_config('bgm_loop',     '1') === '1' ? 1 : 0;
?>
<div id="bgm-player"
     data-video="<?= h($video_id) ?>"
     data-list="<?= h($list_id) ?>"
     data-autoplay="<?= $autoplay ?>"
     data-loop="<?= $loop ?>">
  <button id="bgm-toggle" type="button" aria-label="BGM 재생/일시정지" title="BGM">
    <i class="fas fa-music"></i>
  </button>
  <div id="bgm-yt"></div>
</div>
<script>
(function () {
  if (window._bgmInit) return;  // SPA 재진입 시 중복 초기화 방지
  window._bgmInit = true;

  var p   = document.getElementById('bgm-player');
  var btn = document.getElementById('bgm-toggle');
  if (!p || !btn) return;

  var videoId  = p.dataset.video || '';
  var listId   = p.dataset.list  || '';
  var autoplay = p.dataset.autoplay === '1';
  var loop     = p.dataset.loop === '1';
  var player   = null;

  function setIcon(playing) {
    btn.classList.toggle('playing', playing);
    btn.innerHTML = playing
      ? '<i class="fas fa-pause"></i>'
      : '<i class="fas fa-music"></i>';
  }

  // YouTube IFrame API 로드 (한 번만)
  if (!window.YT || !window.YT.Player) {
    var tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
  }

  function initPlayer() {
    var vars = { autoplay: autoplay ? 1 : 0, controls: 0, modestbranding: 1, playsinline: 1 };
    if (listId) {
      vars.listType = 'playlist';
      vars.list     = listId;
    }
    if (loop) {
      vars.loop = 1;
      if (videoId && !listId) vars.playlist = videoId;  // 단일 영상 loop 트릭
    }

    var opts = {
      height: '0',
      width:  '0',
      playerVars: vars,
      events: {
        onReady: function () {
          if (autoplay) {
            try { player.playVideo(); } catch (e) {}
          }
        },
        onStateChange: function (e) {
          var YTState = window.YT.PlayerState;
          setIcon(e.data === YTState.PLAYING || e.data === YTState.BUFFERING);
        }
      }
    };
    if (videoId) opts.videoId = videoId;

    player = new window.YT.Player('bgm-yt', opts);
  }

  // API 가 이미 준비됐으면 즉시, 아니면 콜백 등록
  if (window.YT && window.YT.Player) {
    initPlayer();
  } else {
    var prev = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = function () {
      if (typeof prev === 'function') prev();
      initPlayer();
    };
  }

  btn.addEventListener('click', function () {
    if (!player) return;
    var st = player.getPlayerState && player.getPlayerState();
    var YTState = window.YT.PlayerState;
    if (st === YTState.PLAYING || st === YTState.BUFFERING) {
      player.pauseVideo();
    } else {
      player.playVideo();
    }
  });
})();
</script>
