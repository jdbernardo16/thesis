<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
  class: { type: Object, required: true },
  stages: { type: Array, default: () => [] },
  roster: { type: Array, default: () => [] },
});

function stars(n) {
  const v = Math.min(3, Math.max(0, n ?? 0));
  return '★'.repeat(v) + '☆'.repeat(3 - v);
}
</script>

<template>
  <Head :title="`Class — ${$props.class.name}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link href="/teacher/dashboard" class="text-sm text-gray-500 underline">← Dashboard</Link>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
          {{ $props.class.name }}
          <span v-if="$props.class.section" class="text-sm font-normal text-gray-500">· {{ $props.class.section }}</span>
        </h2>
        <Link :href="`/classes/${$props.class.id}/leaderboard`" class="ml-auto text-sm text-indigo-600 underline">
          Leaderboard
        </Link>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
        <div class="rounded bg-white p-4 text-sm text-gray-600 shadow-sm">
          Code: <span class="font-semibold text-gray-900">{{ $props.class.code }}</span>
          · {{ $props.class.school_year }}
          · {{ roster.length }} students
          · Leaderboard: {{ $props.class.leaderboard_visible ? 'visible' : 'hidden' }}
          <span v-if="$props.class.teacher_name" class="ml-2">· {{ $props.class.teacher_name }}</span>
        </div>

        <div class="overflow-x-auto rounded bg-white shadow-sm">
          <table class="w-full min-w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                <th class="px-4 py-2">Student</th>
                <th class="px-4 py-2 text-right">Stars</th>
                <th class="px-4 py-2 text-right">EXP</th>
                <th class="px-4 py-2 text-right">Accuracy</th>
                <th class="px-4 py-2">Current level</th>
                <th
                  v-for="s in stages"
                  :key="s.id"
                  class="px-2 py-2 text-center"
                  :title="`${s.level_title ?? ''} — ${s.title}`"
                >
                  S{{ s.order }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="r in roster"
                :key="r.student_id"
                class="border-b border-gray-50"
                :class="r.struggling ? 'bg-red-50' : ''"
              >
                <td class="px-4 py-2">
                  <span class="mr-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                    {{ r.initials }}
                  </span>
                  <span class="font-medium text-gray-900">{{ r.name }}</span>
                  <span v-if="r.struggling" class="ml-2 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-700">
                    {{ r.struggling_reasons.join(', ') }}
                  </span>
                </td>
                <td class="px-4 py-2 text-right font-medium text-amber-600">★ {{ r.total_stars }}</td>
                <td class="px-4 py-2 text-right text-gray-700">{{ r.total_exp }}</td>
                <td class="px-4 py-2 text-right text-gray-700">{{ r.avg_accuracy ?? '—' }}{{ r.avg_accuracy !== null ? '%' : '' }}</td>
                <td class="px-4 py-2 text-gray-600">{{ r.current_level?.title ?? '—' }}</td>
                <td v-for="s in stages" :key="s.id" class="px-2 py-2 text-center text-amber-600" :title="`${r.name} — ${s.title}: ${r.stars_by_stage[s.id] ?? 0} stars`">
                  {{ stars(r.stars_by_stage[s.id] ?? 0) }}
                </td>
              </tr>
              <tr v-if="!roster.length">
                <td :colspan="5 + stages.length" class="px-4 py-6 text-center text-sm text-gray-500">
                  No students enrolled yet. Import a roster to get started.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
