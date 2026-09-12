<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
  attempt: { type: Object, required: true },
  is_best: { type: Boolean, default: false },
  bestStars: { type: Number, default: 0 },
  review: { type: Array, default: () => [] },
  stage: { type: Object, default: null },
  story: { type: Object, default: null },
});

function formatAnswer(value) {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'boolean') return value ? 'True' : 'False';
  if (Array.isArray(value)) return value.join(' → ');
  return String(value);
}

const earnedStars = computed(() => Math.min(3, Math.max(0, props.attempt.stars ?? 0)));
</script>

<template>
  <Head title="Quiz result" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link href="/map" class="text-sm text-gray-500 underline">← Map</Link>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Result</h2>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
        <div class="bg-white p-6 text-center shadow-sm sm:rounded-lg">
          <div class="star-row mb-2 text-4xl">
            <span
              v-for="i in [1, 2, 3]"
              :key="i"
              class="star"
              :class="i <= earnedStars ? 'text-amber-600' : 'text-gray-300'"
              :style="{ animationDelay: `${(i - 1) * 0.25}s` }"
            >
              {{ i <= earnedStars ? '★' : '☆' }}
            </span>
          </div>

          <p class="text-lg font-semibold text-gray-900">
            {{ attempt.correct_count }} / {{ attempt.total }} correct ({{ attempt.pct }}%)
          </p>
          <p class="mt-1 text-sm font-medium text-indigo-700">+{{ attempt.exp }} EXP</p>
          <p v-if="is_best" class="mt-1 inline-block rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
            New best!
          </p>

          <div class="mt-4 flex justify-center gap-2">
            <Link
              v-if="story"
              :href="`/quiz/${story.id}`"
              class="rounded border border-gray-300 px-4 py-2 text-sm"
            >
              Retry
            </Link>
            <Link
              href="/map"
              class="rounded bg-gray-900 px-4 py-2 text-sm text-white"
            >
              Next
            </Link>
          </div>
        </div>

        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
          <h3 class="mb-3 text-sm font-semibold text-gray-700">Review</h3>
          <div class="space-y-3">
            <div
              v-for="r in review"
              :key="r.question_id"
              class="rounded border p-3"
              :class="r.is_correct ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'"
            >
              <p class="text-sm font-medium text-gray-900">
                <span class="mr-1">{{ r.is_correct ? '✓' : '✗' }}</span>
                {{ r.stem }}
              </p>
              <p class="mt-1 text-xs text-gray-600">Your answer: {{ formatAnswer(r.given) }}</p>
              <p v-if="!r.is_correct" class="text-xs text-gray-600">
                Correct answer: {{ formatAnswer(r.correct_answer) }}
              </p>
              <p v-if="r.explanation" class="mt-1 text-xs italic text-gray-500">{{ r.explanation }}</p>
            </div>
            <p v-if="!review.length" class="text-sm text-gray-500">No review available.</p>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.star-row .star {
  display: inline-block;
  opacity: 0;
  animation: pop 0.4s ease-out forwards;
}
@keyframes pop {
  0% {
    opacity: 0;
    transform: scale(0.3);
  }
  70% {
    opacity: 1;
    transform: scale(1.25);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
