<script setup lang="ts">
import forest from '../../assets/studio/forest-courier.webp';
import { Button } from '@/components/ui/button';
import { Circle } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import PublicationMetrics from '@/components/PublicationMetrics.vue';
import type { MetricsReport } from '@/types/analytics';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { DashboardPageProps } from '@/types/content';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<DashboardPageProps & { analytics: MetricsReport }>();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Обзор', href: route('dashboard') }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Обзор студии" />
        <div v-if="!page.props.analytics.projects.length" class="workspace-page">
            <header class="first-header">
                <Heading title="Первый вход" description="В студии пока нет проектов" />
                <Button as-child class="h-11"><Link :href="route('projects.create')">Создать первый проект</Link></Button>
            </header>
            <section class="grid items-start gap-4 md:grid-cols-[min(34%,400px)_minmax(0,1fr)]" aria-label="Первый выпуск">
                <img :src="forest" alt="" class="first-cover w-full rounded-xl object-cover" />
                <div>
                    <h2 class="mb-4 text-xl font-bold">Первый выпуск</h2>
                    <div class="first-steps">
                        <Link :href="route('channels.create')"><Circle aria-hidden="true" />Подключить канал</Link>
                        <Link :href="route('projects.create')"><Circle aria-hidden="true" />Создать проект</Link>
                        <Link :href="route('projects.create', { format: 'series' })"><Circle aria-hidden="true" />Подготовить первый выпуск</Link>
                    </div>
                </div>
            </section>
        </div>
        <PublicationMetrics v-else :report="page.props.analytics" />
    </AppLayout>
</template>

<style scoped>
.first-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}
.first-cover {
    height: 394px;
}
.first-steps {
    display: grid;
    gap: 10px;
}
.first-steps a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--card);
    font-size: 14px;
}
.first-steps a:first-child {
    color: var(--sidebar-accent-foreground);
}
.first-steps svg {
    width: 16px;
    height: 16px;
}
@media (max-width: 1099px) {
    .first-header {
        flex-direction: column;
    }
}
@media (max-width: 767px) {
    .first-cover {
        height: 210px;
    }
}
@media (max-width: 767px) {
    .workspace-page {
        padding-block-start: 16px;
    }
}
</style>
