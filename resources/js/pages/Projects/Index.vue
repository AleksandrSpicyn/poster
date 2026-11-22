<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ListControls from '@/components/ListControls.vue';
import ListPanel from '@/components/ListPanel.vue';
import ProjectNotice from '@/components/projects/ProjectNotice.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatProjectDate } from '@/lib/formatProjectDate';
import type { BreadcrumbItem } from '@/types';
import type { ProjectsPageProps } from '@/types/projects';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import drive from '../../../assets/studio/night-drive.webp';
import forest from '../../../assets/studio/forest-courier.webp';
import lunar from '../../../assets/studio/lunar-landscape.webp';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<ProjectsPageProps>();
const { filters, loading, error, applyFilters, changeSort, changePerPage, changePage, refresh } = useListFilters('projects.index', () => ({
    search: page.props.filters.search,
    active: Number(!!page.props.filters.active),
    updated: Number(!!page.props.filters.updated),
    sort: page.props.filters.sort,
    perPage: page.props.projects.meta.per_page,
    page: page.props.projects.meta.current_page,
}));
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Проекты', href: route('projects.index') }];

function selectGroup(active: boolean) {
    filters.active = Number(active);
    applyFilters();
}
function toggleUpdated() {
    filters.updated = Number(!filters.updated);
    applyFilters();
}
function clearSearch() {
    filters.search = '';
    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Мои проекты" />
        <div class="workspace-page">
            <ProjectNotice />
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading title="Мои проекты" :description="page.props.projects.meta.total + ' · Проекты студии'" />
                <Button as-child class="h-11"><Link :href="route('projects.create')">Создать проект</Link></Button>
            </div>
            <div class="project-tabs flex flex-wrap gap-2" aria-label="Фильтры проектов">
                <Button :variant="filters.active ? 'secondary' : 'default'" :aria-pressed="!filters.active" @click="selectGroup(false)">Все</Button>
                <Button :variant="filters.active ? 'default' : 'secondary'" :aria-pressed="!!filters.active" @click="selectGroup(true)"
                    >Активные</Button
                >
                <Button :variant="filters.updated ? 'default' : 'secondary'" :aria-pressed="!!filters.updated" @click="toggleUpdated"
                    >По дате изменения</Button
                >

                <details class="ml-auto min-w-0">
                    <summary class="text-muted-foreground cursor-pointer py-2 text-sm">Поиск и порядок</summary>
                    <form class="mt-3 flex flex-wrap items-end gap-3" role="search" @submit.prevent="applyFilters">
                        <label class="grid gap-1.5" for="project-search"
                            >Название или базовый промпт
                            <Input id="project-search" v-model="filters.search" type="search" :maxlength="200" :disabled="loading"
                        /></label>
                        <Button type="submit" variant="outline" :disabled="loading">Найти</Button>
                        <Button v-if="filters.search || page.props.filters.search" type="button" variant="ghost" @click="clearSearch"
                            >Очистить</Button
                        >
                        <ListControls
                            :sort="filters.sort"
                            :per-page="filters.perPage"
                            :directions="page.props.sortDirections"
                            :disabled="loading"
                            @sort="changeSort"
                            @per-page="changePerPage"
                        />
                        <Button type="button" variant="ghost" :disabled="loading" @click="refresh">Обновить</Button>
                    </form>
                </details>
            </div>
            <ListPanel
                external-controls
                class="project-list"
                :meta="page.props.projects.meta"
                :count="page.props.projects.data.length"
                :loading="loading"
                :error="error"
                :empty-title="page.props.filters.search || filters.active ? 'Проекты не найдены' : 'Создайте первый проект'"
                :empty-description="
                    page.props.filters.search ? 'Попробуйте другое название или фразу из промпта.' : 'Здесь появятся ваши проекты и их каналы.'
                "
                @page="changePage"
                @refresh="refresh"
            >
                <h2 class="mb-4 text-xl font-bold">{{ filters.updated ? 'По дате изменения' : 'Проекты студии' }}</h2>
                <div class="project-grid">
                    <article v-for="project in page.props.projects.data" :key="project.id" class="project-card">
                        <Link :href="route('projects.show', project.id)" class="focus-visible:ring-ring block focus-visible:ring-2">
                            <img :src="project.is_clipping ? drive : project.content_mode === 'series' ? forest : lunar" alt="" loading="lazy" />
                            <h3>{{ project.name }}</h3>
                        </Link>
                        <div class="flex min-w-0 items-center justify-between gap-2 px-4 pb-2">
                            <span class="text-muted-foreground min-w-0 text-sm"
                                >{{ project.is_clipping ? 'Клиппинг' : project.content_mode === 'series' ? 'Сериал' : 'Короткие ролики'
                                }}{{ project.is_active ? '' : ' · Приостановлен' }}</span
                            >
                            <Link
                                :href="route('projects.edit', project.id)"
                                :aria-label="'Настройки проекта ' + project.name"
                                class="inline-flex size-10 items-center justify-center"
                                ><Pencil class="size-4"
                            /></Link>
                        </div>
                        <details class="text-muted-foreground px-4 pb-3 text-sm">
                            <summary class="cursor-pointer">Сведения</summary>
                            <p class="my-2 break-words">{{ project.prompt }}</p>
                            <p>Создан {{ formatProjectDate(project.created_at) }}</p>
                            <p>
                                Генерации · {{ project.prompts_count }}. Готовые видео · {{ project.ready_prompts_count }}. Каналы ·
                                {{ project.channels_count }}.
                            </p>
                        </details>
                    </article>
                </div>
            </ListPanel>
        </div>
    </AppLayout>
</template>

<style scoped>
.project-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.project-card {
    min-width: 0;
    overflow: hidden;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
}
.project-card img {
    width: 100%;
    aspect-ratio: 1.85;
    max-height: 245px;
    object-fit: cover;
    object-position: center 45%;
}
.project-card h3 {
    padding: 14px 15px 0;
    font-size: 19px;
    font-weight: 700;
    overflow-wrap: anywhere;
}
.project-list {
    background: transparent;
    border: 0;
}
.project-list :deep(> div:first-child),
.project-list :deep(footer) {
    padding-inline: 0;
}
@media (max-width: 1099px) {
    .project-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 699px) {
    .project-grid {
        grid-template-columns: 1fr;
    }
}
.project-tabs button {
    height: 40px;
    background: var(--muted);
    color: var(--muted-foreground);
    font-size: 13px;
}
.project-tabs button[aria-pressed='true'] {
    background: var(--sidebar-accent);
    color: var(--sidebar-accent-foreground);
}
</style>
