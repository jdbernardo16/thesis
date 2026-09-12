<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
  videoId: { type: String, required: true },
  storyId: { type: [Number, String], required: true },
  mustWatchPct: { type: Number, default: 80 },
});

const emit = defineEmits(['progress']);

const pct = ref(0);
const playerEl = ref(null);
let player = null;
let timer = null;
let lastPing = 0;

function postPing(p) {
  const now = Date.now();
  if (now - lastPing < 5000) return;
  lastPing = now;
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  fetch('/watch-pings', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    },
    body: JSON.stringify({ story_id: props.storyId, pct: Math.round(p) }),
  }).catch(() => {});
}

function pollProgress() {
  if (!player || typeof player.getCurrentTime !== 'function') return;
  try {
    const cur = player.getCurrentTime();
    const dur = player.getDuration();
    if (dur > 0) {
      pct.value = Math.min(100, Math.round((cur / dur) * 100));
      emit('progress', pct.value);
      postPing(pct.value);
    }
  } catch (_) {
    // player not ready yet
  }
}

function loadApi() {
  return new Promise((resolve) => {
    if (window.YT && window.YT.Player) return resolve();
    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
    window.onYouTubeIframeAPIReady = () => resolve();
  });
}

onMounted(async () => {
  await loadApi();
  player = new window.YT.Player(playerEl.value, {
    videoId: props.videoId,
    events: {
      onStateChange: () => pollProgress(),
    },
  });
  timer = setInterval(pollProgress, 5000);
});

onBeforeUnmount(() => {
  if (timer) clearInterval(timer);
  if (player && typeof player.destroy === 'function') {
    try {
      player.destroy();
    } catch (_) {
      // ignore
    }
  }
});

defineExpose({ pct });
</script>

<template>
  <div>
    <div class="aspect-video w-full overflow-hidden rounded bg-black">
      <div ref="playerEl" class="h-full w-full" />
    </div>
    <div class="mt-2 flex items-center gap-2">
      <div class="h-2 flex-1 overflow-hidden rounded bg-gray-200">
        <div
          class="h-full bg-indigo-600 transition-all"
          :style="{ width: pct + '%' }"
        />
      </div>
      <span class="text-xs text-gray-500">{{ pct }}% / {{ mustWatchPct }}%</span>
    </div>
  </div>
</template>
