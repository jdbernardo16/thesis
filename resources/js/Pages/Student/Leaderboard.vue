<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
  class: { type: Object, required: true },
  scope: { type: String, default: 'all' },
  entries: { type: Array, default: () => [] },
  note: { type: String, default: null },
  is_preview: { type: Boolean, default: false },
});

function setScope(s) {
  router.get(`/classes/${props.class.id}/leaderboard`, { scope: s }, { preserveState: true });
}
</script>

<template>
  <Head :title="`Leaderboard — ${props.class.name}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link href="/map" class="text-sm text-gray-500 underline">← Map</Link>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          Leaderboard — {{ props.class.name }}
        </h2>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-2">
          <button
            @click="setScope('all')"
            class="rounded border px-3 py-1 text-sm"
            :class="scope === 'all' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300'"
          >
            All-time
          </button>
          <button
            @click="setScope('week')"
            class="rounded border px-3 py-1 text-sm"
            :class="scope === 'week' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300'"
          >
            Week
          </button>
          <span v-if="is_preview" class="ml-auto rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
            Teacher preview (hidden from students)
          </span>
        </div>

        <p v-if="note" class="rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
          {{ note }}
        </p>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                <th class="px-4 py-2">Rank</th>
                <th class="px-4 py-2">Student</th>
                <th class="px-4 py-2 text-right">Stars</th>
                <th class="px-4 py-2 text-right">EXP</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="e in entries" :key="e.student_id" class="border-b border-gray-50">
                <td class="px-4 py-2 font-semibold text-gray-900">#{{ e.rank }}</td>
                <td class="px-4 py-2">
                  <span class="mr-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                    {{ e.initials }}
                  </span>
                  {{ e.name }}
                </td>
                <td class="px-4 py-2 text-right font-medium text-amber-600">★ {{ e.total_stars }}</td>
                <td class="px-4 py-2 text-right text-gray-700">{{ e.total_exp }}</td>
              </tr>
              <tr v-if="!entries.length">
                <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No students yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
