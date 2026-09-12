<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import QuizRunner from '@/Components/QuizRunner.vue';

defineProps({
  story: { type: Object, required: true },
  stage: { type: Object, required: true },
  questions: { type: Array, default: () => [] },
  bestStars: { type: Number, default: 0 },
});
</script>

<template>
  <Head :title="`Quiz — ${story.title}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link :href="`/stories/${story.id}`" class="text-sm text-gray-500 underline">← Story</Link>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Quiz: {{ story.title }}</h2>
        <span v-if="bestStars > 0" class="ml-auto text-sm font-semibold text-amber-600">
          Best: {{ '★'.repeat(Math.min(3, Math.max(0, bestStars))) }}
        </span>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
          <QuizRunner :story="story" :stage="stage" :questions="questions" />
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
