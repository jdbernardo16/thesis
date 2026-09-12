<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  story: { type: Object, required: true },
  stage: { type: Object, required: true },
  questions: { type: Array, default: () => [] },
});

const answers = ref({});
const confirming = ref(false);
const submitting = ref(false);
const startedAt = ref(Date.now());
const idempotencyKey = ref('');

onMounted(() => {
  startedAt.value = Date.now();
  idempotencyKey.value =
    typeof crypto !== 'undefined' && crypto.randomUUID
      ? crypto.randomUUID()
      : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  // Ordering questions: default rank = current position (1-based).
  for (const q of props.questions) {
    if (q.type === 'ordering' && q.items) {
      answers.value[q.id] = q.items.map((_, i) => i + 1);
    }
  }
});

function isAnswered(q) {
  const a = answers.value[q.id];
  if (a === undefined || a === null) return false;
  if (typeof a === 'string') return a.trim() !== '';
  if (Array.isArray(a)) return a.length > 0 && a.every((v) => v !== undefined && v !== null && v !== '');
  return true;
}

const answeredCount = computed(() => props.questions.filter(isAnswered).length);
const allAnswered = computed(
  () => props.questions.length > 0 && answeredCount.value === props.questions.length
);

function setRank(q, i, value) {
  const current = [...(answers.value[q.id] || q.items.map((_, j) => j + 1))];
  current[i] = Number(value);
  answers.value[q.id] = current;
}

function orderedItems(q) {
  // Build the student's ordered array from per-item rank inputs.
  const ranks = answers.value[q.id] || [];
  return q.items
    .map((item, i) => ({ item, rank: Number(ranks[i] ?? i + 1) }))
    .sort((a, b) => a.rank - b.rank)
    .map((r) => r.item);
}

function submit() {
  if (!allAnswered.value || submitting.value) return;
  confirming.value = true;
}

function confirmSubmit() {
  confirming.value = false;
  submitting.value = true;
  const payload = {};
  for (const q of props.questions) {
    payload[q.id] = q.type === 'ordering' ? orderedItems(q) : answers.value[q.id];
  }
  router.post(
    `/stages/${props.stage.id}/attempts`,
    {
      answers: payload,
      duration_sec: Math.max(0, Math.round((Date.now() - startedAt.value) / 1000)),
      idempotency_key: idempotencyKey.value,
    },
    { onFinish: () => (submitting.value = false) }
  );
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-for="(q, idx) in questions"
      :key="q.id"
      class="rounded border border-gray-200 bg-white p-4"
    >
      <p class="mb-3 text-sm font-semibold text-gray-900">
        <span class="mr-2 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
          Q{{ idx + 1 }}
        </span>
        {{ q.stem }}
      </p>

      <!-- Single-choice -->
      <div v-if="q.type === 'mc_single'" class="space-y-2">
        <label
          v-for="(opt, i) in q.options"
          :key="i"
          class="flex cursor-pointer items-center gap-2 rounded border px-3 py-2 text-sm"
          :class="answers[q.id] === opt ? 'border-gray-900 bg-gray-50' : 'border-gray-200'"
        >
          <input
            type="radio"
            :name="`q-${q.id}`"
            :value="opt"
            v-model="answers[q.id]"
            class="accent-gray-900"
          />
          <span class="text-gray-800">{{ opt }}</span>
        </label>
      </div>

      <!-- True / False -->
      <div v-else-if="q.type === 'true_false'" class="flex gap-2">
        <button
          v-for="opt in [true, false]"
          :key="String(opt)"
          type="button"
          @click="answers[q.id] = opt"
          class="rounded border px-4 py-2 text-sm"
          :class="answers[q.id] === opt ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300'"
        >
          {{ opt ? 'True' : 'False' }}
        </button>
      </div>

      <!-- Ordering: rank each item 1..n -->
      <div v-else-if="q.type === 'ordering'" class="space-y-2">
        <p class="text-xs text-gray-500">Rank each item from 1 (first) to {{ q.items.length }} (last).</p>
        <div
          v-for="(item, i) in q.items"
          :key="i"
          class="flex items-center gap-3 rounded border border-gray-200 px-3 py-2 text-sm"
        >
          <input
            type="number"
            :min="1"
            :max="q.items.length"
            :value="(answers[q.id] || [])[i] ?? i + 1"
            @input="setRank(q, i, $event.target.value)"
            class="w-16 rounded border border-gray-300 px-2 py-1 text-center"
          />
          <span class="text-gray-800">{{ item }}</span>
        </div>
      </div>

      <!-- Fill in the blank -->
      <div v-else-if="q.type === 'fill_blank'">
        <input
          type="text"
          v-model="answers[q.id]"
          placeholder="Type your answer"
          class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
        />
      </div>
    </div>

    <div class="flex items-center gap-3">
      <span class="text-sm text-gray-500">{{ answeredCount }} / {{ questions.length }} answered</span>
      <button
        @click="submit"
        :disabled="!allAnswered || submitting"
        class="ml-auto rounded bg-gray-900 px-4 py-2 text-sm text-white disabled:cursor-not-allowed disabled:bg-gray-300"
      >
        {{ submitting ? 'Submitting…' : 'Submit answers' }}
      </button>
    </div>
    <p v-if="!allAnswered" class="text-right text-xs text-gray-400">
      Answer every question to submit.
    </p>

    <!-- Confirm modal -->
    <div
      v-if="confirming"
      class="fixed inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 p-4"
    >
      <div class="w-full max-w-sm rounded bg-white p-6 shadow-lg">
        <h3 class="mb-2 text-base font-semibold text-gray-900">Submit quiz?</h3>
        <p class="mb-4 text-sm text-gray-600">
          You answered all {{ questions.length }} questions. This will be graded — you can't change
          answers after.
        </p>
        <div class="flex justify-end gap-2">
          <button
            @click="confirming = false"
            class="rounded border border-gray-300 px-3 py-1 text-sm"
          >
            Keep checking
          </button>
          <button
            @click="confirmSubmit"
            class="rounded bg-gray-900 px-3 py-1 text-sm text-white"
          >
            Submit
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
