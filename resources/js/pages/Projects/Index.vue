<script setup lang="ts">
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
import { CircleSlash, CircleX } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<ProjectsPageProps>();
const { filters, loading, error, applyFilters, changePerPage, changePage, refresh } = useListFilters('projects.index', () => ({
    search: page.props.filters.search,
    alphabetical: Number(!!page.props.filters.alphabetical),
    active: Number(!!page.props.filters.active),
    updated: Number(!!page.props.filters.updated),
    sort: page.props.filters.sort,
    perPage: page.props.projects.meta.per_page,
    page: page.props.projects.meta.current_page,
}));
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Проекты', href: route('projects.index') }];

const plurals = new Intl.PluralRules('ru');
function counted(value: number, one: string, few: string, many: string) {
    const ending = plurals.select(value);
    return value + ' ' + (ending === 'one' ? one : ending === 'few' ? few : many);
}
const sidebarProject = ref(page.props.projects.data.at(0));
watch(
    () => page.props.projects.data,
    (projects) => {
        if (projects[0]) sidebarProject.value = projects[0];
    },
);
const preview = computed(() => {
    if (filters.search === page.props.filters.search) return { total: page.props.projects.meta.total, first: page.props.projects.data[0] };
    if (page.props.filters.search || page.props.projects.meta.total !== page.props.projects.data.length) return null;
    const term = (filters.search ?? '').trim().toLocaleLowerCase('ru');
    const matches = page.props.projects.data.filter((project) =>
        [project.name, project.prompt].some((field) => field?.toLocaleLowerCase('ru').includes(term)),
    );
    return { total: matches.length, first: matches[0] };
});
const information = computed(() => page.props.information);
function order(value: string) {
    filters.alphabetical = Number(value === 'name');
    filters.updated = Number(value === 'updated');
    filters.sort = value === 'oldest' ? 'asc' : 'desc';
}
const searching = ref(false);
function restoreSearch() {
    searching.value = location.hash === '#search';
}
onMounted(() => {
    restoreSearch();
    window.addEventListener('hashchange', restoreSearch);
});
onUnmounted(() => window.removeEventListener('hashchange', restoreSearch));
function runSearch() {
    searching.value = false;
    applyFilters();
}
const results = computed(() => !searching.value && (!!page.props.filters.search || !!error.value));
function selectGroup(active: boolean) {
    filters.active = Number(active);
    applyFilters();
}
function toggleUpdated() {
    filters.alphabetical = 0;
    filters.updated = Number(!filters.updated);
    applyFilters();
}
function clearSearch() {
    filters.search = '';
    filters.active = 0;
    filters.alphabetical = 0;
    filters.updated = 0;
    filters.sort = 'desc';
    searching.value = false;
    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Мои проекты" />
        <div class="workspace-page">
            <ProjectNotice />
            <div class="project-heading flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Мои проекты"
                    :description="
                        information
                            ? 'Сведения'
                            : searching
                              ? 'Поиск и порядок'
                              : results
                                ? 'Результаты поиска'
                                : counted(page.props.projects.meta.total, 'проект', 'проекта', 'проектов') + ' · Все форматы'
                    "
                />
                <Button v-if="!information && !searching && !results" as-child class="h-11"
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
                    <h2 class="text-xl font-bold">Поиск и порядок</h2>
                    <label class="grid gap-1.5" for="project-search"
                        >Поиск по названию <Input id="project-search" v-model="filters.search" type="search" :maxlength="200" :disabled="loading"
                    /></label>
                    <label class="grid gap-1"
                        >Порядок проектов
                        <select
                            class="list-select"
                            :value="filters.alphabetical ? 'name' : filters.updated ? 'updated' : filters.sort === 'asc' ? 'oldest' : 'newest'"
                            @change="order(($event.target as HTMLSelectElement).value)"
                        >
                            <option value="updated">По дате изменения</option>
                            <option value="newest">Сначала новые</option>
                            <option value="oldest">Сначала старые</option>
                            <option value="name">По названию · от А до Я</option>
                        </select>
                    </label>
                    <fieldset class="grid">
                        <legend class="sr-only">Порядок сортировки</legend>
                        <label class="radio-option">
                            <input
                                type="radio"
                                name="project-order"
                                :checked="!filters.alphabetical && filters.sort !== 'asc'"
                                @change="order('updated')"
                            />
                            <span><strong>Сначала новые</strong><small>Последние изменения сверху</small></span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="project-order" :checked="!!filters.alphabetical" @change="order('name')" />
                            <span><strong>По названию</strong><small>От А до Я</small></span>
                        </label>
                    </fieldset>
                    <p v-if="preview" class="studio-row">
                        <strong>Найдено</strong
                        ><span
                            >{{ counted(preview.total, 'проект', 'проекта', 'проектов')
                            }}<template v-if="preview.total === 1"> · {{ preview.first?.name }}</template></span
                        >
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
            <div v-else-if="results" class="project-information search-results">
                <section class="studio-panel results-panel grid gap-3">
                    <h2 class="text-xl font-bold">Результаты поиска</h2>
                    <p>
                        <a href="#search" class="font-bold hover:underline" aria-label="Изменить поиск" @click="searching = true"
                            >Поиск: {{ filters.search || page.props.filters.search }}</a
                        >
                    </p>
                    <div class="result-chips flex flex-wrap gap-2">
                        <span>{{ filters.active ? 'Активные' : 'Все проекты' }}</span><span>{{
                            filters.alphabetical ? 'По названию' : filters.sort === 'asc' ? 'Сначала старые' : 'Новые сверху'
                        }}</span>
                    </div>
                    <template v-if="error"
                        ><div role="alert" class="result-error">
                            <CircleX class="size-4 shrink-0" aria-hidden="true" />
                            <div>
                                <strong>Не удалось загрузить страницу {{ filters.page }}</strong>
                                <p>{{ error.startsWith('Не удалось обновить список') ? 'Поиск, порядок и размер страницы сохранены.' : error }}</p>
                            </div>
                        </div>
                    </template>
                    <template v-else-if="!page.props.projects.data.length"
                        ><CircleSlash class="empty-symbol" aria-hidden="true" /><strong class="text-xl">Совпадений нет</strong>
                        <p>Попробуйте другое название или измените фильтры.</p>
                        <Button class="justify-self-start" variant="outline" @click="clearSearch">Очистить фильтры</Button></template
                    >
                    <ul v-else>
                        <li v-for="project in page.props.projects.data" :key="project.id" class="border-b py-3">
                            <Link :href="route('projects.show', project.id)" class="font-bold">{{ project.name }}</Link>
                            <p>
                                Изменён {{ formatProjectDate(project.updated_at) }} ·
                                {{ project.is_clipping ? 'Клиппинг' : project.content_mode === 'series' ? 'Сериал' : 'Короткие ролики' }}
                            </p>
                        </li>
                    </ul>
                    <label v-if="error || page.props.projects.data.length" class="studio-row"
                        >На странице<select
                            class="list-select"
                            :value="filters.perPage"
                            @change="changePerPage(Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option
                                v-for="size in Array.from(new Set([filters.perPage, 10, 25, 50, 100])).sort((a, b) => a - b)"
                                :key="size"
                                :value="size"
                            >
                                {{ size }} записей
                            </option>
                        </select></label
                    >
                    <Button v-if="error" class="justify-self-start" @click="refresh">Повторить загрузку</Button>
                    <nav v-if="!error && page.props.projects.meta.last_page > 1" class="flex flex-wrap items-center gap-2">
                        <Button variant="outline" :disabled="loading || filters.page <= 1" @click="changePage(filters.page - 1)">Назад</Button>
                        <span class="flex gap-1"
                            ><Button
                                v-for="number in Array.from(
                                    { length: Math.min(5, page.props.projects.meta.last_page) },
                                    (_, i) => Math.max(1, Math.min(filters.page - 2, page.props.projects.meta.last_page - 4)) + i,
                                )"
                                :key="number"
                                :variant="number === filters.page ? 'default' : 'outline'"
                                :aria-current="number === filters.page ? 'page' : undefined"
                                @click="changePage(number)"
                                >{{ number }}</Button
                            ></span
                        ><Button
                            variant="outline"
                            :disabled="loading || filters.page >= page.props.projects.meta.last_page"
                            @click="changePage(filters.page + 1)"
                            >Далее</Button
                        >
                    </nav>
                    <p v-if="!error && page.props.projects.meta.total">
                        Показано {{ page.props.projects.meta.to }} из {{ page.props.projects.meta.total }}
                    </p>
                </section>
                <aside v-if="sidebarProject" class="studio-panel hidden min-[1100px]:block">
                    <ProjectArtwork :name="sidebarProject.name" :cover="sidebarProject.cover" class="mb-3 aspect-[2.4] rounded-lg" /><strong>{{
                        sidebarProject.name
                    }}</strong>
                    <p class="text-muted-foreground">
                        {{ sidebarProject.is_clipping ? 'Клиппинг' : sidebarProject.content_mode === 'series' ? 'Сериал' : 'Короткие ролики' }}
                    </p>
                </aside>
            </div>
            <template v-else>
                <div class="project-tabs flex flex-wrap gap-2" aria-label="Фильтры проектов">
                    <Button :variant="filters.active ? 'secondary' : 'default'" :aria-pressed="!filters.active" @click="selectGroup(false)"
                        >Все</Button
                    >
                    <Button :variant="filters.active ? 'default' : 'secondary'" :aria-pressed="!!filters.active" @click="selectGroup(true)"
                        >Активные</Button
                    >
                    <Button :variant="filters.updated ? 'default' : 'secondary'" :aria-pressed="!!filters.updated" @click="toggleUpdated"
                        >По дате изменения</Button
                    >

                    <a href="#search" @click="searching = true" class="text-muted-foreground ml-auto py-2 text-sm">Поиск и порядок</a>
                </div>
                <ListPanel
                    external-controls
                    compact-footer
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
                                <ProjectArtwork :name="project.name" :cover="project.cover" class="aspect-[1.86] max-h-[245px] w-full" />
                                <h3>{{ project.name }}</h3>
                            </Link>
                            <div class="flex min-w-0 items-center justify-between gap-2 px-4 pb-2">
                                <span class="text-muted-foreground min-w-0 text-sm"
                                    >{{ project.is_clipping ? 'Клиппинг' : project.content_mode === 'series' ? 'Сериал' : 'Короткие ролики'
                                    }}{{ project.is_active ? '' : ' · Приостановлен' }}</span
                                >
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
.info-panel .studio-row,
.search-panel .studio-row,
.results-panel .studio-row {
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
.radio-option {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.radio-option input {
    margin-top: 4px;
    accent-color: var(--primary);
}
.radio-option small {
    display: block;
    color: var(--muted-foreground);
}
.results-panel h2 {
    margin-bottom: 0;
}
.results-panel .studio-row {
    padding-block: 6px;
    border-top: 0;
    border-bottom: 1px solid var(--border);
    color: var(--muted-foreground);
}
.results-panel select {
    border: 0;
    padding: 0;
    height: 32px;
    min-height: 32px;
    background: transparent;
    color: var(--foreground);
}
.results-panel [data-slot='button'] {
    min-height: 42px;
}
.result-chips span,
.empty-symbol {
    background: var(--muted);
    padding: 9px 12px;
    border-radius: 8px;
    color: var(--muted-foreground);
    font-size: 13px;
}
.result-chips span:first-child {
    background: var(--sidebar-accent);
    color: var(--sidebar-accent-foreground);
}
.empty-symbol {
    width: 35px;
    height: 32px;
    padding: 8px;
}
.result-error {
    display: flex;
    align-items: baseline;
    gap: 10px;
    border-radius: 6px;
    background: var(--muted);
    border-inline-start: 3px solid var(--destructive);
    padding: 10px 14px;
}
.result-error svg {
    align-self: flex-start;
    margin-top: 3px;
    color: var(--destructive);
}
.result-error p {
    color: var(--muted-foreground);
    font-size: 14px;
}
.result-error strong {
    color: var(--destructive);
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
.workspace-page:has(.project-information) {
    row-gap: 13px;
}
.project-information:not(.search-results) {
    max-width: 1234px;
}
.project-information:not(.search-results) aside > p {
    margin-top: 7px;
}
.info-panel .studio-row {
    padding-block: 12px;
}
.search-panel {
    row-gap: 8px;
}
@media (max-width: 699px) {
    .workspace-page:has(.project-information) {
        row-gap: 9px;
    }
}
@media (min-width: 1100px) {
    .project-information:not(.search-results) {
        grid-template-columns: minmax(0, 2.38fr) minmax(0, 1fr);
        gap: 22px;
    }
    .project-information.search-results {
        grid-template-columns: minmax(0, 2.23fr) minmax(0, 1fr);
        gap: 20px;
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
