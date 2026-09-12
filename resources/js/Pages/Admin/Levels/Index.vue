<script setup>
import { router } from '@inertiajs/vue3';

defineProps({ levels: { type: Array, default: () => [] } });

function publish(id) {
  router.post(`/admin/levels/${id}/publish`);
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-bold mb-4">Levels</h1>
    <ul class="space-y-2">
      <li v-for="level in levels" :key="level.id" class="flex items-center gap-3 border p-3 rounded">
        <span class="font-medium">{{ level.title }}</span>
        <span v-if="level.is_published" class="text-sm text-green-600">Published</span>
        <span v-else class="text-sm text-gray-500">Draft</span>
        <button
          v-if="!level.is_published"
          @click="publish(level.id)"
          class="ml-auto text-sm bg-gray-900 text-white px-3 py-1 rounded"
        >
          Publish
        </button>
      </li>
    </ul>
    <p v-if="!levels.length" class="text-sm text-gray-500">No levels yet.</p>
  </div>
</template>
