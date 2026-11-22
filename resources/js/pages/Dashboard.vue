<script setup lang="ts">
import forest from '../../assets/studio/forest-courier.webp';
import EmptyState from '@/components/EmptyState.vue';
import GenerationItem from '@/components/GenerationItem.vue';
import Heading from '@/components/Heading.vue';
import PublicationCounters from '@/components/PublicationCounters.vue';
import PublicationMetrics, { type MetricsReport } from '@/components/PublicationMetrics.vue';
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
const page = usePage<DashboardPageProps & { analytics: MetricsReport }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Обзор', href: route('dashboard') }];
const metrics = computed(() => [
    { label: 'Все идеи', value: page.props.stats.prompts, icon: Layers, note: 'Во всех проектах' },
    { label: 'В работе', value: page.props.stats.pending, icon: Clock3, note: 'Ожидают сборки' },
    { label: 'Готовые видео', value: page.props.stats.ready, icon: Clapperboard, note: 'Сборка завершена' },
    { label: 'Публикации', value: page.props.stats.posts, icon: Send, note: 'Во всех каналах' },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Обзор студии" />
        <div class="workspace-page">
            <Heading :title="page.props.analytics.projects.length ? 'Обзор студии' : 'Первый вход'" />
            <section
                v-if="!page.props.analytics.projects.length"
                class="grid items-start gap-4 md:grid-cols-[min(34%,400px)_minmax(0,1fr)]"
                aria-label="Первый выпуск"
            >
                <img :src="forest" alt="" class="h-[210px] w-full rounded-xl object-cover md:h-[393px]" />
                <div>
                    <h2 class="mb-4 text-xl font-bold">Первый выпуск</h2>
                    <div class="grid gap-3">
                        <Link :href="route('channels.create')" class="studio-panel font-semibold">Подключить канал</Link>
                        <Link :href="route('projects.create')" class="studio-panel font-semibold">Создать первый проект</Link>
                        <Link :href="route('projects.create', { format: 'series' })" class="studio-panel font-semibold"
                            >Подготовить первый сериал</Link
                        >
                    </div>
                </div>
            </section>
            <PublicationCounters v-if="page.props.analytics.projects.length" :report="page.props.analytics" />
            <div v-if="page.props.analytics.projects.length" class="grid items-start gap-4 xl:grid-cols-[minmax(0,1.8fr)_minmax(260px,1fr)]">
                <PublicationMetrics :report="page.props.analytics" />
                <section class="studio-panel">
                    <h2>Каналы</h2>
                    <ul v-if="page.props.analytics.coverage.length" class="mt-3 divide-y">
                        <li v-for="(channel, index) in page.props.analytics.coverage" :key="index" class="flex flex-wrap justify-between gap-2 py-3">
                            <span>{{ channel.name }}</span><small>{{ channel.status }}</small>
                        </li>
                    </ul>
                    <Link :href="route('channels.index')" class="mt-3 inline-block underline">Мои каналы</Link>
                </section>
            </div>
            <div v-if="page.props.analytics.projects.length" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]">
                <section class="bg-card min-w-0 overflow-hidden rounded-xl border">
                    <header class="flex items-center justify-between gap-4 border-b px-5 py-4">
                        <div>
                            <h2 class="font-semibold">Последние генерации</h2>
                            <p class="text-muted-foreground mt-1 text-xs">Идеи и готовые видео</p>
                        </div>
                        <Link :href="route('prompts')" class="text-primary inline-flex min-h-9 shrink-0 items-center gap-1 text-sm font-medium"
                            >Все записи<ArrowUpRight class="size-4" aria-hidden="true"
                        /></Link>
                    </header>
                    <div v-if="page.props.recentPrompts.data.length" class="divide-y">
                        <GenerationItem v-for="prompt in page.props.recentPrompts.data" :key="prompt.id" :prompt="prompt" />
                    </div>
                    <EmptyState v-else title="Пока нет генераций" description="После генерации здесь появится идея и её текущий этап."
                        ><Button as-child variant="outline"><Link :href="route('prompts')">Открыть генерации</Link></Button></EmptyState
                    >
                </section>
                <section class="bg-card min-w-0 overflow-hidden rounded-xl border">
                    <header class="flex items-center justify-between gap-4 border-b px-5 py-4">
                        <div>
                            <h2 class="font-semibold">Последние публикации</h2>
                            <p class="text-muted-foreground mt-1 text-xs">Результаты отправки в каналы</p>
                        </div>
                        <Link :href="route('posts')" class="text-primary inline-flex min-h-9 items-center gap-1 text-sm font-medium"
                            >Все записи<ArrowUpRight class="size-4" aria-hidden="true"
                        /></Link>
                    </header>
                    <div v-if="page.props.recentPosts.data.length" class="divide-y">
                        <PublicationItem v-for="post in page.props.recentPosts.data" :key="post.id" :post="post" />
                    </div>
                    <EmptyState v-else title="Пока нет публикаций" description="Здесь появятся публикации из ваших каналов." />
                </section>
            </div>
            <section v-if="page.props.analytics.projects.length" class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Производство">
                <div v-for="metric in metrics" :key="metric.label" class="bg-card rounded-xl border p-4 sm:p-5">
                    <div class="mb-4 flex items-start justify-between gap-2">
                        <p class="text-muted-foreground text-sm font-medium">{{ metric.label }}</p>
                        <component :is="metric.icon" class="text-primary size-4 shrink-0" aria-hidden="true" />
                    </div>
                    <p class="text-3xl font-semibold tracking-tight tabular-nums sm:text-4xl">{{ metric.value.toLocaleString('ru') }}</p>
                    <p class="text-muted-foreground mt-2 text-xs">{{ metric.note }}</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
