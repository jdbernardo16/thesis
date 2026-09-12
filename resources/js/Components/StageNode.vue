<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  stage: { type: Object, required: true },
});
</script>

<template>
  <div
    class="flex items-center gap-3 rounded border p-3"
    :class="{
      'border-gray-200 bg-gray-50 opacity-60': stage.state === 'locked',
      'border-indigo-300 bg-indigo-50': stage.state === 'current',
      'border-green-300 bg-green-50': stage.state === 'cleared',
    }"
  >
    <div
      class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold"
      :class="{
        'bg-gray-300 text-gray-600': stage.state === 'locked',
        'bg-indigo-600 text-white': stage.state === 'current',
        'bg-green-600 text-white': stage.state === 'cleared',
      }"
    >
      <span v-if="stage.state === 'locked'">🔒</span>
      <span v-else-if="stage.state === 'cleared'">★</span>
      <span v-else>{{ stage.order }}</span>
    </div>

    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium text-gray-900">{{ stage.title }}</p>
      <p class="text-xs text-gray-500">
        <span v-if="stage.state === 'locked'">Locked — clear previous stage first</span>
        <span v-else-if="stage.state === 'cleared'">Cleared — {{ stage.bestStars }} ★</span>
        <span v-else>Ready to play</span>
      </p>
    </div>

    <div class="flex shrink-0 items-center gap-2">
      <span v-if="stage.bestStars > 0" class="text-xs font-semibold text-amber-600">
        {{ '★'.repeat(stage.bestStars) }}{{ '☆'.repeat(3 - stage.bestStars) }}
      </span>
      <Link
        v-if="stage.unlocked && stage.story_id"
        :href="`/stories/${stage.story_id}`"
        class="rounded bg-gray-900 px-3 py-1 text-sm text-white"
      >
        Open
      </Link>
      <span
        v-else
        class="cursor-not-allowed rounded bg-gray-200 px-3 py-1 text-sm text-gray-400"
      >
        Locked
      </span>
    </div>
  </div>
</template>
