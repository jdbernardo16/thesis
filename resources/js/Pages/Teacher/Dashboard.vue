<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
  classes: { type: Array, default: () => [] },
  struggling: { type: Array, default: () => [] },
  total_stages: { type: Number, default: 0 },
});

function pct(completion) {
  return Math.round((completion ?? 0) * 100);
}
</script>

<template>
  <Head title="Teacher Dashboard" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Teacher Dashboard</h2>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
        <div class="grid gap-4 md:grid-cols-2">
          <div
            v-for="c in classes"
            :key="c.id"
            class="rounded bg-white p-4 shadow-sm"
          >
            <div class="flex items-center gap-2">
              <h3 class="text-base font-bold text-gray-900">{{ c.name }}</h3>
              <span v-if="c.section" class="text-sm text-gray-500">{{ c.section }}</span>
              <Link :href="`/teacher/classes/${c.id}`" class="ml-auto text-sm text-indigo-600 underline">
                View class
              </Link>
            </div>
            <p class="mt-1 text-xs text-gray-500">Code: {{ c.code }} · {{ c.school_year }}</p>
            <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
              <div class="rounded bg-gray-50 p-2">
                <dt class="text-xs text-gray-500">Students</dt>
                <dd class="text-lg font-bold text-gray-900">{{ c.students_count }}</dd>
              </div>
              <div class="rounded bg-gray-50 p-2">
                <dt class="text-xs text-gray-500">Avg completion</dt>
                <dd class="text-lg font-bold text-gray-900">{{ pct(c.avg_completion) }}%</dd>
              </div>
              <div class="rounded bg-gray-50 p-2">
                <dt class="text-xs text-gray-500">Avg accuracy</dt>
                <dd class="text-lg font-bold text-gray-900">{{ c.avg_accuracy }}%</dd>
              </div>
            </dl>
            <p v-if="c.struggling_count" class="mt-2 text-xs font-medium text-red-600">
              {{ c.struggling_count }} struggling
            </p>
          </div>
        </div>
        <p v-if="!classes.length" class="text-center text-sm text-gray-500">No classes yet.</p>

        <div class="rounded bg-white p-4 shadow-sm">
          <h3 class="mb-2 text-sm font-semibold text-gray-700">Struggling students</h3>
          <p class="mb-3 text-xs text-gray-500">Accuracy below 50% or inactive for more than 7 days.</p>
          <ul class="divide-y divide-gray-100">
            <li v-for="s in struggling" :key="`${s.class_id}-${s.student_id}`" class="flex items-center gap-2 py-2 text-sm">
              <span class="font-medium text-gray-900">{{ s.name }}</span>
              <span class="text-xs text-gray-500">{{ s.class_name }}</span>
              <span class="ml-auto flex gap-1">
                <span v-for="r in s.reasons" :key="r" class="rounded bg-red-100 px-2 py-0.5 text-xs text-red-700">
                  {{ r === 'low_accuracy' ? `Accuracy ${s.avg_accuracy ?? '—'}%` : 'Inactive 7d+' }}
                </span>
              </span>
            </li>
          </ul>
          <p v-if="!struggling.length" class="text-sm text-gray-500">None — all students on track.</p>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
