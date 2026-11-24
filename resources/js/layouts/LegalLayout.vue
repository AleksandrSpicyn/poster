<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';

defineProps<{ title: string; relatedPage: 'policy' | 'terms'; relatedTitle: string; updated: string; updatedLabel: string }>();
const route = useRoute();
</script>

<template>
    <div class="public-frame">
        <PublicHeader workspace-link />
        <main class="legal-copy pt-[13px] min-[700px]:pt-[18px]" lang="en">
            <Head :title="title" />
            <h1>{{ title }}</h1>
            <p class="relative -left-px mt-[17px] text-sm tracking-[0.06px] min-[700px]:mt-5">
                <time :datetime="updated">{{ updatedLabel }}</time>
            </p>
            <slot />
            <footer class="mt-10 flex flex-wrap gap-5 border-t pt-6 text-sm">
                <a href="mailto:fenixxxar85@gmail.com" class="underline">fenixxxar85@gmail.com</a>
                <Link :href="route(relatedPage)" class="underline">{{ relatedTitle }}</Link>
            </footer>
        </main>
    </div>
</template>
