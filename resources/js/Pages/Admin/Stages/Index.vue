<script setup>
import { router } from '@inertiajs/vue3';

defineProps({ stages: { type: Array, default: () => [] } });

function publish(id) {
  router.post(`/admin/stages/${id}/publish`);
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-bold mb-4">Stages</h1>
    <ul class="space-y-2">
      <li v-for="stage in stages" :key="stage.id" class="flex items-center gap-3 border p-3 rounded">
        <span class="font-medium">{{ stage.title }}</span>
        <span v-if="stage.is_published" class="text-sm text-green-600">Published</span>
        <span v-else class="text-sm text-gray-500">Draft</span>
        <button
          v-if="!stage.is_published"
          @click="publish(stage.id)"
          class="ml-auto text-sm bg-gray-900 text-white px-3 py-1 rounded"
        >
          Publish
        </button>
      </li>
    </ul>
    <p v-if="!stages.length" class="text-sm text-gray-500">No stages yet.</p>
  </div>
</template>
