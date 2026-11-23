<script setup lang="ts">
import ProjectCoverGallery from '@/components/projects/ProjectCoverGallery.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import Heading from '@/components/Heading.vue';
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
import { Search } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<ProjectsPageProps>();
const { filters, loading, error, applyFilters, changePage, changePerPage, refresh } = useListFilters('projects.index', () => ({
    search: page.props.filters.search,
    clipping: Number(!!page.props.filters.clipping),
    active: Number(!!page.props.filters.active),
    archived: Number(!!page.props.filters.archived),
    sort: page.props.filters.sort,
    perPage: page.props.projects.meta.per_page,
    page: page.props.projects.meta.current_page,
}));
const pageSizes = computed(() => [...new Set([filters.perPage, 10, 25, 50, 100])].sort((a, b) => a - b));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Проекты', href: route('projects.index') },
    ...(page.props.information ? [{ title: 'Сведения', href: page.url }] : []),
    ...(searching.value || page.props.filters.search ? [{ title: searching.value ? 'Поиск проектов' : 'Результаты поиска', href: page.url }] : []),
    ...(page.props.filters.clipping ? [{ title: 'Клиппинг', href: route('projects.index', { clipping: 1 }) }] : []),
]);

const plurals = new Intl.PluralRules('ru');
function counted(value: number, one: string, few: string, many: string) {
    const ending = plurals.select(value);
    return value + ' ' + (ending === 'one' ? one : ending === 'few' ? few : many);
}
const information = computed(() => page.props.information);
const searching = ref(false);
const preview = ref<{ total: number; name?: string }>();
watch(
    () => [searching.value, filters.search, filters.active, filters.clipping, page.props.projects] as const,
    ([open, search, active, clipping, projects], _, onCleanup) => {
        preview.value = undefined;
        if (!open) return;
        if (
            search === page.props.filters.search &&
            !page.props.filters.archived &&
            !!active === !!page.props.filters.active &&
            !!clipping === !!page.props.filters.clipping
        ) {
            preview.value = { total: projects.meta.total, name: projects.data[0]?.name };
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const url = route('projects.index', { search, active, clipping, perPage: 1 });
                const response = await fetch(url, {
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        'X-Inertia': 'true',
                        'X-Inertia-Partial-Component': page.component,
                        'X-Inertia-Partial-Data': 'projects',
                        ...(page.version ? { 'X-Inertia-Version': page.version } : {}),
                    },
                });
                if (!response.ok || !response.headers.has('X-Inertia')) return;
                const result: { props: Pick<ProjectsPageProps, 'projects'> } = await response.json();
                const matches = result.props.projects;
                if (!controller.signal.aborted && matches) preview.value = { total: matches.meta.total, name: matches.data[0]?.name };
            } catch {
                // The full search remains available when its background preview is interrupted.
            }
        }, 220);
        onCleanup(() => {
            window.clearTimeout(timer);
            controller.abort();
        });
    },
);
function restoreSearch() {
    searching.value = location.hash === '#search';
}
onMounted(() => {
    restoreSearch();
    window.addEventListener('hashchange', restoreSearch);
});
onUnmounted(() => window.removeEventListener('hashchange', restoreSearch));
function runSearch() {
    filters.archived = 0;
    searching.value = false;
    applyFilters();
}
function selectGroup(group: 'all' | 'active') {
    filters.active = Number(group === 'active');
    filters.archived = 0;
    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Проекты студии" />
        <div class="workspace-page">
            <ProjectNotice />
            <div class="project-heading flex flex-wrap items-start justify-between gap-4">
                <Heading
                    :title="information || searching || page.props.filters.clipping ? 'Мои проекты' : 'Проекты студии'"
                    :description="
                        information
                            ? 'Сведения'
                            : searching
                              ? 'Поиск проектов'
                              : page.props.filters.search
                                ? 'Результаты поиска · ' + page.props.filters.search
                                : page.props.filters.clipping
                                  ? 'Клиппинг'
                                  : counted(page.props.projects.meta.total, 'проект', 'проекта', 'проектов') + ' / Все форматы'
                    "
                />
                <a
                    v-if="!information && !searching && !page.props.filters.clipping"
                    href="#search"
                    class="project-search-link"
                    aria-label="Поиск проектов"
                    title="Поиск проектов"
                    @click="searching = true"
                    ><Search aria-hidden="true" class="size-4"
                /></a>
                <Button
                    v-if="page.props.filters.search || searching || page.props.filters.archived || page.props.filters.active"
                    as-child
                    class="h-11"
                    variant="outline"
                >
                    <Link :href="route('projects.index')">Ко всем</Link>
                </Button>
                <Button v-if="!information && !page.props.filters.clipping" as-child class="h-11"
                    ><Link :href="route('projects.create')">Создать проект</Link></Button
                >
            </div>
            <div v-if="information" class="project-information">
                <section class="studio-panel info-panel">
                    <h2 class="text-xl font-bold">Сведения</h2>
                    <div class="flex items-center gap-3">
                        <ProjectArtwork :name="information.project.name" :cover="information.project.cover" class="h-14 w-20 rounded-lg" /><strong>{{
                            information.project.name
                        }}</strong>
                    </div>
                    <p class="studio-row">
                        <strong>Владелец</strong><span>{{ information.owner }} · Моя студия</span>
                    </p>
                    <p class="studio-row">
                        <strong>Производство</strong
                        ><span v-if="information.project.content_mode === 'series'"
                            >{{ counted(information.accepted, 'принятый эпизод', 'принятых эпизода', 'принятых эпизодов') }} из
                            {{ information.episodes }}</span
                        ><span v-else
                            >{{ counted(information.project.ready_prompts_count, 'готовый ролик', 'готовых ролика', 'готовых роликов') }} из
                            {{ information.project.prompts_count }}</span
                        >
                    </p>
                    <p class="studio-row">
                        <strong>Каналы проекта</strong
                        >{{
                            information.project.channels.map((channel) => channel.name + ' · ' + channel.type_label).join(', ') ||
                            'Каналы ещё не привязаны'
                        }}
                    </p>
                    <p class="studio-row">
                        <strong>Последнее изменение</strong><span>{{ formatProjectDate(information.project.updated_at) }}</span>
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <Button as-child variant="outline"><Link :href="route('projects.show', information.project.id)">Открыть проект</Link></Button
                        ><Link :href="route('projects.edit', information.project.id)">Настройки проекта</Link
                        ><Link :href="route('projects.index')">К проектам</Link>
                    </div>
                </section>
                <aside class="studio-panel hidden min-[1100px]:block">
                    <h2 class="font-bold">Мои проекты</h2>
                    <p class="mt-4">Студия</p>
                    <strong>{{ information.owner }}</strong>
                    <p class="mt-4">Рабочая область</p>
                    <span>Моя студия</span>
                </aside>
            </div>
            <div v-else-if="searching" class="project-information">
                <form class="studio-panel search-panel grid items-start gap-3" role="search" @submit.prevent="runSearch">
                    <h2 class="text-xl font-bold">Поиск проектов</h2>
                    <label class="grid gap-1.5" for="project-search"
                        >Поиск по названию <Input id="project-search" v-model="filters.search" type="search" :maxlength="200" :disabled="loading"
                    /></label>
                    <label class="grid gap-1"
                        >Дата добавления
                        <select v-model="filters.sort" class="list-select">
                            <option value="desc">Сначала новые</option>
                            <option value="asc">Сначала старые</option>
                        </select>
                    </label>
                    <p class="studio-row" aria-live="polite">
                        <strong>Найдено</strong
                        ><span v-if="preview"
                            >{{ counted(preview.total, 'проект', 'проекта', 'проектов')
                            }}<template v-if="preview.total === 1"> · {{ preview.name }}</template></span
                        ><span v-else>—</span>
                    </p>
                    <Button type="submit" class="justify-self-start" :disabled="loading">Применить</Button>
                </form>
                <aside class="studio-panel hidden min-[1100px]:block">
                    <h2 class="font-bold">Мои проекты</h2>
                    <p class="mt-4">Студия</p>
                    <strong>{{ page.props.auth.user.name }}</strong>
                    <p class="mt-4">Рабочая область</p>
                    <span>Моя студия</span>
                </aside>
            </div>
            <ProjectCoverGallery v-else-if="page.props.filters.clipping" :projects="page.props.projects" :loading="loading" @page="changePage" />
            <template v-else>
                <div class="project-tabs flex flex-wrap gap-2" aria-label="Фильтры проектов">
                    <Button
                        :variant="filters.active || filters.archived ? 'secondary' : 'default'"
                        :aria-pressed="!filters.active && !filters.archived"
                        @click="selectGroup('all')"
                        >Все</Button
                    >
                    <Button :variant="filters.active ? 'default' : 'secondary'" :aria-pressed="!!filters.active" @click="selectGroup('active')"
                        >Активные</Button
                    >
                    <Button v-if="page.props.filters.archived" variant="default" :aria-pressed="true" @click="selectGroup('all')">В архиве</Button>
                    <label class="ml-auto flex items-center gap-2 text-sm">
                        На странице
                        <select
                            class="list-select"
                            :value="filters.perPage"
                            :disabled="loading"
                            @change="changePerPage(Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option v-for="size in pageSizes" :key="size" :value="size">{{ size }}</option>
                        </select>
                    </label>
                </div>
                <ListPanel
                    external-controls
                    compact-footer
                    class="project-list"
                    :meta="page.props.projects.meta"
                    :count="page.props.projects.data.length"
                    :loading="loading"
                    :error="error"
                    :empty-title="
                        filters.archived
                            ? 'Архив пуст'
                            : page.props.filters.search || filters.active
                              ? 'Проекты не найдены'
                              : 'Создайте первый проект'
                    "
                    :empty-description="
                        page.props.filters.search ? 'Попробуйте другое название или фразу из промпта.' : 'Здесь появятся ваши проекты и их каналы.'
                    "
                    @page="changePage"
                    @refresh="refresh"
                >
                    <h2 class="mb-[18px] text-xl font-bold min-[700px]:mb-[15px] min-[1100px]:mb-[18px]">
                        {{ filters.sort === 'asc' ? 'Сначала старые' : 'Сначала новые' }}
                    </h2>
                    <div class="project-grid">
                        <article v-for="project in page.props.projects.data" :key="project.id" class="project-card">
                            <Link :href="route('projects.show', project.id)" class="focus-visible:ring-ring block focus-visible:ring-2">
                                <ProjectArtwork :name="project.name" :cover="project.cover" class="project-list-cover" />
                                <h3>{{ project.name }}</h3>
                            </Link>
                            <div class="flex min-w-0 items-center justify-between gap-2 px-4 pb-2">
                                <span class="text-muted-foreground min-w-0 text-sm">
                                    <Link v-if="project.is_clipping" class="hover:underline" :href="route('projects.index', { clipping: 1 })"
                                        >Клиппинг</Link
                                    >
                                    <template v-else>{{ project.content_mode === 'series' ? 'Сериал' : 'Короткие ролики' }}</template>{{
                                        project.is_active ? '' : ' · Приостановлен'
                                    }}
                                </span>
                            </div>
                            <Link
                                :href="route('projects.index', { info: project.id })"
                                :aria-label="'Сведения о проекте ' + project.name"
                                class="project-info-link"
                                >Сведения</Link
                            >
                        </article>
                    </div>
                </ListPanel>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.project-heading [data-slot='button'] {
    font-weight: 700;
}
.project-heading {
    position: relative;
}
.project-search-link {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    color: var(--muted-foreground);
    border-radius: 8px;
}
.project-search-link:hover,
.project-search-link:focus-visible {
    background: var(--muted);
    color: var(--foreground);
}
@media (min-width: 1100px) {
    .project-search-link {
        margin-left: auto;
    }
}
@media (max-width: 1099px) {
    .project-search-link {
        position: absolute;
        right: 0;
        top: 0;
    }
}
.workspace-page:has(.project-list) {
    gap: 23px;
}
@media (max-width: 767px) {
    .workspace-page:has(.project-list) {
        padding-top: 15px;
    }
}

.info-panel .studio-row,
.search-panel .studio-row {
    display: grid;
    grid-template-columns: minmax(0, 38%) minmax(0, 1fr);
    gap: 8px;
    align-items: center;
}
.info-panel > :first-child,
.info-panel > :nth-child(2) {
    margin-bottom: 14px;
}
.info-panel > :last-child {
    margin-top: 16px;
}
.project-card {
    position: relative;
}
.project-info-link {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 5px 9px;
    border-radius: 6px;
    color: var(--foreground);
    background: var(--card);
    opacity: 0;
}
.project-card:hover .project-info-link,
.project-card:focus-within .project-info-link {
    opacity: 1;
}
@media (hover: none) {
    .project-info-link {
        opacity: 1;
    }
}
.workspace-page:has(.project-clipping-gallery),
.workspace-page:has(.project-information) {
    row-gap: 13px;
}
.project-information {
    max-width: 1234px;
}
.project-information aside > p {
    margin-top: 7px;
}
.info-panel .studio-row {
    padding-block: 12px;
}
.search-panel {
    row-gap: 8px;
}
@media (max-width: 699px) {
    .workspace-page:has(.project-clipping-gallery),
    .workspace-page:has(.project-information) {
        row-gap: 9px;
    }
}
@media (min-width: 1100px) {
    .project-information {
        grid-template-columns: minmax(0, 2.38fr) minmax(0, 1fr);
        gap: 22px;
    }
}
.project-information {
    display: grid;
    align-items: start;
    gap: 16px;
}
@media (min-width: 1100px) {
    .project-information {
        grid-template-columns: minmax(0, 2.3fr) minmax(0, 1fr);
    }
}
@media (max-width: 1099px) {
    .project-heading {
        flex-direction: column;
    }
}

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
.project-list-cover {
    width: calc(100% + 2px);
    aspect-ratio: 1.85;
    max-height: 246px;
    margin-inline: -1px;
}
.project-card {
    padding-bottom: 15px;
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
