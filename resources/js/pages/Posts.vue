<script setup lang="ts">
import { useHashNavigation } from '@/composables/useHashNavigation';
import Heading from '@/components/Heading.vue';
import ChannelIcon from '@/components/ChannelIcon.vue';
import ListControls from '@/components/ListControls.vue';
import ListPanel from '@/components/ListPanel.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { publicationStates } from '@/lib/publicationStatus';
import type { PostsPageProps } from '@/types/content';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useRoute } from '@/composables/useRoute';

const route = useRoute();
const eventTime = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Europe/Moscow' });
const page = usePage<PostsPageProps>();
const { filters, loading, error, applyFilters, changePage, refresh } = useListFilters('posts', () => ({
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
useHashNavigation(readSection);
const channelLabel = computed(() => page.props.types[page.props.filters.type ?? ''] ?? 'Все площадки');
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
                    <p class="text-muted-foreground mt-2 text-sm">{{ channelLabel }} / {{ periodLabel }}</p>
                </div>
                <div class="flex max-w-full flex-wrap gap-2">
                    <Button variant="outline" :aria-expanded="section === 'filters'" @click="section = section === 'filters' ? '' : 'filters'"
                        >Фильтры</Button
                    >
                    <Button
                        v-for="channel in page.props.destinations"
                        :key="channel.id"
                        as-child
                        class="h-auto max-w-full text-center wrap-anywhere whitespace-normal"
                        ><Link :href="route('posts.tiktok.create', channel.id)">Отправить в TikTok · {{ channel.name }}</Link></Button
                    >
                </div>
            </header>
            <form v-if="section === 'filters'" class="studio-panel studio-filters" role="search" @submit.prevent="applyFilters">
                <label>Название ролика<Input v-model="filters.search" type="search" maxlength="200" /></label>
                <label
                    >Площадка<select v-model="filters.type" class="list-select">
                        <option :value="page.props.allTypes">Все площадки</option>
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
                    @sort="filters.sort = $event"
                    @per-page="filters.perPage = $event"
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
                        <div class="grid gap-1.5">
                            <span class="flex flex-wrap items-center gap-2"
                                ><ChannelIcon :channel="row.platform" /><span v-if="row.platform" class="sr-only">{{
                                    page.props.types[row.platform]
                                }}</span
                                >{{ row.channel_name ?? 'Канал удалён' }} ·
                                <time v-if="row.event_at" :datetime="row.event_at">{{ eventTime.format(new Date(row.event_at)) }} МСК</time></span
                            ><span :data-status="row.status">{{ publicationStates[row.status] }}</span>
                        </div>
                    </li>
                </ul>
            </ListPanel>
        </div>
    </AppLayout>
</template>

<style scoped>
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
.feed h2 {
    font-weight: 700;
    font-size: 20px;
    margin-bottom: 16px;
}
.feed-row {
    display: grid;
    grid-template-columns: minmax(0, 0.87fr) minmax(0, 1fr);
    align-items: center;
    gap: 12px 24px;
    padding-block: 16px;
    border-bottom: 1px solid var(--border);
    overflow-wrap: anywhere;
}
.feed-row :is(a, strong) {
    font-weight: 700;
}
.feed-row span {
    color: var(--muted-foreground);
    font-size: 14px;
}
.feed-row [data-status='published'] {
    color: var(--ring);
}
.feed-row [data-status='submission_unknown'] {
    color: var(--warning);
}
.feed-row [data-status='planned'] {
    color: var(--progress);
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
        gap: 8px;
    }
}
</style>
