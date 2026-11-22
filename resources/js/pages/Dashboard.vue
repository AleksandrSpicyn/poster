<script setup lang="ts">
import forest from '../../assets/studio/forest-courier.webp';
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
            <Heading title="Первый вход" />
            <section class="grid items-start gap-4 md:grid-cols-[min(34%,400px)_minmax(0,1fr)]" aria-label="Первый выпуск">
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
        </div>
        <PublicationMetrics v-else :report="page.props.analytics" />
    </AppLayout>
</template>
