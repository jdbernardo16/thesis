<script setup>
import { computed, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import YoutubePlayer from '@/Components/YoutubePlayer.vue';

const props = defineProps({
  story: { type: Object, required: true },
  stage: { type: Object, required: true },
  level: { type: Object, default: null },
  bestStars: { type: Number, default: 0 },
  questionCount: { type: Number, default: 0 },
});

const fontSize = ref('M');
const page = ref(0);
const watchPct = ref(0);
const transcriptDone = ref(false);
const transcriptEnd = ref(null);

const fontClass = computed(() => {
  if (fontSize.value === 'S') return 'text-sm leading-6';
  if (fontSize.value === 'L') return 'text-xl leading-9';
  return 'text-base leading-8';
});

// Simple client-side pagination: split body_html into ~600-char pages.
const pages = computed(() => {
  const html = props.story.body_html || '';
  if (!html) return [];
  const chunks = [];
  let cur = '';
  const parts = html.split(/<\/p>/i);
  for (const p of parts) {
    if (!p.trim()) continue;
    const piece = p + '</p>';
    if ((cur + piece).length > 1200 && cur) {
      chunks.push(cur);
      cur = piece;
    } else {
      cur += piece;
    }
  }
  if (cur) chunks.push(cur);
  return chunks.length ? chunks : [html];
});

const quizEnabled = computed(() => {
  if (props.story.type !== 'youtube') return true;
  return watchPct.value >= (props.story.must_watch_pct ?? 80) || transcriptDone.value;
});

function onProgress(p) {
  watchPct.value = p;
}

onMounted(() => {
  if (!('IntersectionObserver' in window) || !transcriptEnd.value) return;
  const io = new IntersectionObserver(
    (entries) => {
      for (const e of entries) {
        if (e.isIntersecting) transcriptDone.value = true;
      }
    },
    { threshold: 0.5 }
  );
  io.observe(transcriptEnd.value);
});

function setFont(s) {
  fontSize.value = s;
}
</script>

<template>
  <Head :title="story.title" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link href="/map" class="text-sm text-gray-500 underline">← Map</Link>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ story.title }}</h2>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
          <div class="mb-4 flex items-center gap-2">
            <span class="text-sm text-gray-500">Text size:</span>
            <button
              v-for="s in ['S', 'M', 'L']"
              :key="s"
              @click="setFont(s)"
              class="rounded border px-2 py-1 text-sm"
              :class="fontSize === s ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300'"
            >
              {{ s }}
            </button>
            <span v-if="bestStars > 0" class="ml-auto text-sm font-semibold text-amber-600">
              Best: {{ '★'.repeat(bestStars) }}
            </span>
          </div>

          <!-- Text story -->
          <div v-if="story.type === 'text'">
            <div v-if="pages.length" :class="fontClass" v-html="pages[page]" class="text-gray-900" />
            <p v-else class="text-sm text-gray-500">No content yet.</p>
            <div v-if="pages.length > 1" class="mt-4 flex items-center justify-between">
              <button
                :disabled="page === 0"
                @click="page--"
                class="rounded border px-3 py-1 text-sm disabled:opacity-40"
              >
                Prev
              </button>
              <span class="text-xs text-gray-500">Page {{ page + 1 }} / {{ pages.length }}</span>
              <button
                :disabled="page >= pages.length - 1"
                @click="page++"
                class="rounded border px-3 py-1 text-sm disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>

          <!-- YouTube story -->
          <div v-else-if="story.type === 'youtube'">
            <YoutubePlayer
              v-if="story.youtube_video_id"
              :video-id="story.youtube_video_id"
              :story-id="story.id"
              :must-watch-pct="story.must_watch_pct ?? 80"
              @progress="onProgress"
            />
            <p v-else class="text-sm text-gray-500">Video missing.</p>

            <div v-if="story.transcript" class="mt-4">
              <h3 class="mb-2 text-sm font-semibold text-gray-700">Transcript (scroll to bottom as alternative)</h3>
              <div class="max-h-48 overflow-y-auto rounded border border-gray-200 p-3 text-sm leading-6 text-gray-800">
                <p>{{ story.transcript }}</p>
                <div ref="transcriptEnd" class="pt-4 text-center text-xs text-gray-400">
                  — end of transcript —
                </div>
              </div>
              <p v-if="transcriptDone" class="mt-1 text-xs text-green-600">Transcript completed ✓</p>
            </div>
          </div>

          <div class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-4">
            <span class="text-sm text-gray-500">{{ questionCount }} questions</span>
            <button
              :disabled="!quizEnabled"
              class="ml-auto rounded bg-gray-900 px-4 py-2 text-sm text-white disabled:cursor-not-allowed disabled:bg-gray-300"
            >
              {{ quizEnabled ? 'Start Quiz' : `Watch ${story.must_watch_pct ?? 80}% or finish transcript to unlock quiz` }}
            </button>
          </div>
          <p v-if="!quizEnabled" class="mt-1 text-right text-xs text-gray-400">
            Watched {{ watchPct }}% — keep watching or scroll the transcript.
          </p>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
