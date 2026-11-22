<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ListControls from '@/components/ListControls.vue';
import ListPanel from '@/components/ListPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { publicationStates } from '@/lib/publicationStatus';
import type { PostsPageProps } from '@/types/content';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<PostsPageProps>();
const { filters, loading, error, applyFilters, changeSort, changePerPage, changePage, refresh } = useListFilters('posts', () => ({
    type: page.props.filters.type ?? page.props.allTypes,
    sort: page.props.filters.sort ?? page.props.defaultSort,
    perPage: page.props.activity.meta.per_page,
    page: page.props.activity.meta.current_page,
    days: page.props.periodDays,
    search: page.props.search,
}));
const section = ref('');
function readSection() {
    section.value = window.location.hash.slice(1);
}
onMounted(() => {
    readSection();
    window.addEventListener('hashchange', readSection);
});
onUnmounted(() => window.removeEventListener('hashchange', readSection));
const channelLabel = computed(() => page.props.types[page.props.filters.type ?? ''] ?? 'Все каналы');
const requestRows = computed(() => page.props.requests.filter((item) => item.status !== 'published'));
const periodLabel = computed(() => (page.props.periodDays ? 'За ' + page.props.periodDays + ' дней' : 'За всё время'));
function resetFilters() {
    Object.assign(filters, { search: '', type: page.props.allTypes, days: 30, sort: page.props.defaultSort });
    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Публикации', href: route('posts') }]">
        <Head title="Публикации" />
        <div class="workspace-page posts-page">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Heading title="Публикации" />
                    <p class="text-muted-foreground mt-2 text-sm">
                        {{ channelLabel }}<template v-if="section !== 'channels'"> / {{ periodLabel }}</template>
                    </p>
                </div>
                <Button v-if="section !== 'channels'" as-child class="h-11"><a href="#filters">Фильтры</a></Button>
            </header>
            <nav aria-label="Публикации" class="tabs flex flex-wrap gap-2">
                <a href="#all" :aria-current="section !== 'filters' && section !== 'channels' ? 'page' : undefined">Все</a>
                <a href="#filters" :aria-current="section === 'filters' ? 'page' : undefined">Фильтры</a>
                <a href="#channels" :aria-current="section === 'channels' ? 'page' : undefined">Все каналы</a>
            </nav>
            <section v-if="section === 'channels'" class="studio-panel overview">
                <h2>Все каналы</h2>
                <h3>Запросы на отправку</h3>
                <p v-if="!requestRows.length">Запросов пока нет</p>
                <div v-for="item in requestRows" :key="item.id" class="summary-row" :data-status="item.status">
                    <Link
                        v-if="item.status === 'submission_unknown' && item.can_verify"
                        :href="route('posts.requests.review', item.id) + '#response'"
                    >
                        {{ item.title }} · {{ publicationStates[item.status] }}
                    </Link>
                    <strong v-else>{{ item.title }} · {{ publicationStates[item.status] }}</strong>
                    <small
                        >{{ item.channel_name }}<template v-if="item.platform"> · {{ page.props.types[item.platform] }}</template></small
                    >
                </div>
                <h3>Итоговые публикации</h3>
                <p v-if="!page.props.posts.data.length">Пока нет публикаций</p>
                <div v-for="item in page.props.posts.data" :key="item.id" class="summary-row">
                    <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer">{{ item.title }}</a>
                    <strong v-else>{{ item.title }}</strong>
                    <small>Опубликовано · {{ item.channel_name ?? 'Канал удалён' }}</small>
                </div>
                <template v-if="page.props.destinations.length">
                    <h3>Доступные TikTok-каналы</h3>
                    <Link
                        v-for="channel in page.props.destinations"
                        :key="channel.id"
                        :href="route('posts.tiktok.create', channel.id)"
                        class="summary-row"
                        :aria-label="'Отправить в ' + channel.name"
                    >
                        <strong>{{ channel.name }} · TikTok</strong>
                        <small>{{
                            channel.direct && channel.inbox
                                ? 'Доступна загрузка и прямая отправка'
                                : channel.direct
                                  ? 'Доступна прямая отправка'
                                  : 'Доступна загрузка во входящие'
                        }}</small>
                    </Link>
                </template>
            </section>
            <template v-else>
                <form v-if="section === 'filters'" class="studio-panel flex flex-wrap items-end gap-4" role="search" @submit.prevent="applyFilters">
                    <label>Название ролика<Input v-model="filters.search" type="search" maxlength="200" /></label>
                    <label
                        >Канал<select v-model="filters.type" class="list-select">
                            <option :value="page.props.allTypes">Все каналы</option>
                            <option v-for="(label, value) in page.props.types" :key="value" :value="value">{{ label }}</option>
                        </select></label
                    >
                    <label
                        >Период<select v-model.number="filters.days" class="list-select">
                            <option v-for="days in [7, 30, 90, 0]" :key="days" :value="days">{{ days ? days + ' дней' : 'Всё время' }}</option>
                        </select></label
                    >
                    <ListControls
                        :sort="filters.sort"
                        :per-page="filters.perPage"
                        :directions="page.props.sortDirections"
                        :disabled="loading"
                        @sort="changeSort"
                        @per-page="changePerPage"
                    />
                    <Button type="submit" :disabled="loading">Применить фильтр</Button
                    ><Button type="button" variant="outline" :disabled="loading" @click="resetFilters">Сбросить</Button>
                </form>
                <ListPanel
                    external-controls
                    class="feed"
                    :meta="page.props.activity.meta"
                    :count="page.props.activity.data.length"
                    :loading="loading"
                    :error="error"
                    empty-title="Публикации не найдены"
                    empty-description="Попробуйте изменить период или фильтры."
                    @page="changePage"
                    @refresh="refresh"
                >
                    <h2>Название ролика</h2>
                    <ul>
                        <li v-for="row in page.props.activity.data" :key="row.kind + row.id" class="feed-row">
                            <a v-if="row.kind === 'post' && row.url" :href="row.url" target="_blank" rel="noopener noreferrer">{{ row.title }}</a>
                            <Link
                                v-else-if="
                                    row.status === 'submission_unknown' && row.channel_id && page.props.verifiableChannels.includes(row.channel_id)
                                "
                                :href="route('posts.requests.review', row.id)"
                                :aria-label="'Сверка операции · ' + row.title"
                                >{{ row.title }}</Link
                            >
                            <strong v-else>{{ row.title }}</strong>
                            <span :data-status="row.status">{{ publicationStates[row.status] }}</span>
                        </li>
                    </ul>
                </ListPanel>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.tabs a {
    border-radius: 9px;
    padding: 10px 12px;
    background: var(--muted);
    font-size: 14px;
    color: var(--muted-foreground);
}
.tabs [aria-current] {
    color: var(--primary);
    background: var(--sidebar-accent);
}
.feed {
    border: 0;
    background: transparent;
}
.feed :deep(footer) {
    padding-inline: 0;
}
.feed:has(.feed-row) :deep(footer:has(button:disabled:first-of-type):has(button:disabled:last-of-type)) {
    display: none;
}
.feed h2,
.overview h3 {
    font-weight: 700;
    font-size: 20px;
    margin-bottom: 16px;
}
.feed-row {
    display: grid;
    grid-template-columns: minmax(0, 0.87fr) minmax(0, 1fr);
    align-items: center;
    gap: 12px;
    min-height: 54px;
    border-bottom: 1px solid var(--border);
    overflow-wrap: anywhere;
}
.feed-row :is(a, strong),
.summary-row :is(a, strong) {
    font-weight: 700;
}
.feed-row span,
.summary-row small {
    color: var(--muted-foreground);
    font-size: 14px;
}
.feed-row [data-status='published'] {
    color: var(--primary);
}
.feed-row [data-status='submission_unknown'],
.summary-row[data-status='submission_unknown'] {
    color: #cd9167;
}
.feed-row [data-status='planned'] {
    color: #a798d0;
}
.summary-row {
    display: grid;
    gap: 4px;
    border-bottom: 1px solid var(--border);
    padding-block: 12px;
}
.overview h3 {
    margin-top: 20px;
}
form label {
    display: grid;
    gap: 6px;
}
@media (max-width: 1099px) {
    .posts-page header {
        flex-direction: column;
    }
}
@media (max-width: 699px) {
    .feed-row {
        grid-template-columns: 1fr;
        align-content: center;
        gap: 2px;
        min-height: 66px;
    }
}
</style>
