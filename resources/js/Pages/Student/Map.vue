<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import StageNode from '@/Components/StageNode.vue';

defineProps({
  levels: { type: Array, default: () => [] },
});

const openLevels = ref({});

function toggle(levelId) {
  openLevels.value[levelId] = !openLevels.value[levelId];
}

function isOpen(level, idx) {
  if (level.id in openLevels.value) return openLevels.value[level.id];
  return idx === 0;
}
</script>

<template>
  <Head title="Map" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Reading Map</h2>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
        <div
          v-for="(level, idx) in levels"
          :key="level.id"
          class="overflow-hidden bg-white shadow-sm sm:rounded-lg"
        >
          <button
            @click="toggle(level.id)"
            class="flex w-full items-center gap-3 p-4 text-left"
          >
            <span class="text-lg font-bold text-gray-900">
              Level {{ level.order }} — {{ level.title }}
            </span>
            <span
              v-if="level.badge_name"
              class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
            >
              {{ level.badge_name }}
            </span>
            <span class="ml-auto text-sm text-gray-500">
              {{ isOpen(level, idx) ? '▲' : '▼' }}
            </span>
          </button>

          <div v-if="isOpen(level, idx)" class="space-y-2 border-t border-gray-100 p-4">
            <p v-if="level.description" class="text-sm text-gray-600">{{ level.description }}</p>
            <StageNode
              v-for="stage in level.stages"
              :key="stage.id"
              :stage="stage"
            />
            <p v-if="!level.stages.length" class="text-sm text-gray-500">No stages yet.</p>
          </div>
        </div>

        <p v-if="!levels.length" class="text-center text-sm text-gray-500">
          No levels yet. Check back soon!
        </p>

        <div class="text-center">
          <Link href="/dashboard" class="text-sm text-gray-500 underline">Back to dashboard</Link>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
