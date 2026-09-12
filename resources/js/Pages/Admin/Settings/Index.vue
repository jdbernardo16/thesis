<script setup>
defineProps({ settings: { type: Object, default: () => ({}) } });
</script>

<template>
  <div class="p-6">
    <h1 class="mb-4 text-xl font-bold">Settings (read-only)</h1>
    <div class="space-y-2">
      <div v-for="(value, key) in settings" :key="key" class="rounded border p-3">
        <p class="text-sm font-semibold">{{ key }}</p>
        <pre class="mt-1 overflow-x-auto text-xs text-gray-700">{{ JSON.stringify(value, null, 2) }}</pre>
      </div>
    </div>
    <p v-if="!Object.keys(settings).length" class="text-sm text-gray-500">No settings seeded yet.</p>
  </div>
</template>
