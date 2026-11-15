<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import GenerationItem from '@/components/GenerationItem.vue';
import Heading from '@/components/Heading.vue';
import PublicationItem from '@/components/PublicationItem.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { DashboardPageProps } from '@/types/content';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Clapperboard, Clock3, Layers, Send } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<DashboardPageProps>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: route('dashboard') }];
const metrics = computed(() => [
    { label: 'Total ideas', value: page.props.stats.prompts, icon: Layers, note: 'Across all projects' },
    { label: 'Awaiting completion', value: page.props.stats.pending, icon: Clock3, note: 'Videos not yet assembled' },
    { label: 'Videos ready', value: page.props.stats.ready, icon: Clapperboard, note: 'Assembly complete' },
    { label: 'Published posts', value: page.props.stats.posts, icon: Send, note: 'Across all channels' },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Dashboard" />
        <div class="workspace-page">
            <Heading title="Your video workspace" description="A clear view of your ideas, finished videos and published posts." />
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Workspace totals">
                <div v-for="metric in metrics" :key="metric.label" class="bg-card rounded-xl border p-4 sm:p-5">
                    <div class="mb-4 flex items-start justify-between gap-2">
                        <p class="text-muted-foreground text-sm font-medium">{{ metric.label }}</p>
                        <component :is="metric.icon" class="text-primary size-4 shrink-0" aria-hidden="true" />
                    </div>
                    <p class="text-3xl font-semibold tracking-tight tabular-nums sm:text-4xl">{{ metric.value.toLocaleString('en') }}</p>
                    <p class="text-muted-foreground mt-2 text-xs">{{ metric.note }}</p>
                </div>
            </section>
            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]">
                <section class="bg-card min-w-0 overflow-hidden rounded-xl border">
                    <header class="flex items-center justify-between gap-4 border-b px-5 py-4">
                        <div>
                            <h2 class="font-semibold">Recent generations</h2>
                            <p class="text-muted-foreground mt-1 text-xs">From idea to finished video</p>
                        </div>
                        <Link :href="route('prompts')" class="text-primary inline-flex min-h-9 shrink-0 items-center gap-1 text-sm font-medium"
                            >View all<ArrowUpRight class="size-4" aria-hidden="true"
                        /></Link>
                    </header>
                    <div v-if="page.props.recentPrompts.data.length" class="divide-y">
                        <GenerationItem v-for="prompt in page.props.recentPrompts.data" :key="prompt.id" :prompt="prompt" />
                    </div>
                    <EmptyState
                        v-else
                        title="Your first idea starts here"
                        description="Once a project generates an idea, you can follow its progress and preview the finished video here."
                        ><Button as-child variant="outline"><Link :href="route('prompts')">View prompts</Link></Button></EmptyState
                    >
                </section>
                <section class="bg-card min-w-0 overflow-hidden rounded-xl border">
                    <header class="flex items-center justify-between gap-4 border-b px-5 py-4">
                        <div>
                            <h2 class="font-semibold">Latest posts</h2>
                            <p class="text-muted-foreground mt-1 text-xs">Your most recent publications</p>
                        </div>
                        <Link :href="route('posts')" class="text-primary inline-flex min-h-9 items-center gap-1 text-sm font-medium"
                            >View all<ArrowUpRight class="size-4" aria-hidden="true"
                        /></Link>
                    </header>
                    <div v-if="page.props.recentPosts.data.length" class="divide-y">
                        <PublicationItem v-for="post in page.props.recentPosts.data" :key="post.id" :post="post" />
                    </div>
                    <EmptyState
                        v-else
                        title="No published posts yet"
                        description="Your publishing history will appear after a finished video is posted to a channel."
                    />
                </section>
            </div>
        </div>
    </AppLayout>
</template>
