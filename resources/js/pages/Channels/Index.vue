<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ListPanel from '@/components/ListPanel.vue';
import ProjectNotice from '@/components/projects/ProjectNotice.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { ManagedChannel } from '@/types/channels';
import type { PaginatedResource } from '@/types/content';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useRoute } from 'ziggy-js';

const page = usePage<
    SharedData & { channels: PaginatedResource<ManagedChannel>; filters: { search: string | null; active: boolean | null; sort: string | null } }
>();
const route = useRoute();
const { filters, loading, error, applyFilters, changePage, refresh } = useListFilters('channels.index', () => ({
    page: page.props.channels.meta.current_page,
    perPage: page.props.channels.meta.per_page,
    sort: page.props.filters.sort ?? '',
    search: page.props.filters.search ?? '',
    active: page.props.filters.active === null ? null : Number(page.props.filters.active),
}));
const opened = ref(!!filters.search || filters.active !== null || !!filters.sort);
function reset() {
    Object.assign(filters, { search: '', active: null, sort: '', perPage: 20 });
    applyFilters();
}
</script>
<template>
    <AppLayout :breadcrumbs="[{ title: 'Каналы', href: route('channels.index') }]">
        <Head title="Каналы" />
        <div class="workspace-page channel-list">
            <ProjectNotice /><InputError :message="page.props.errors.oauth" />
            <header class="flex flex-col items-start justify-between gap-4 min-[1100px]:flex-row">
                <Heading title="Каналы" description="Аккаунты публикации этого пользователя" />
                <Button as-child class="h-11"><Link :href="route('channels.create')">Добавить канал</Link></Button>
            </header>
            <nav aria-label="Каналы" class="tabs flex gap-2">
                <button
                    :aria-pressed="!opened"
                    @click="
                        opened = false;
                        reset();
                    "
                >
                    Все
                </button>
                <button :aria-pressed="opened" aria-controls="channel-filters" @click="opened = !opened">Фильтры</button>
            </nav>
            <form v-if="opened" id="channel-filters" class="studio-panel flex flex-wrap items-end gap-3" role="search" @submit.prevent="applyFilters">
                <label>Название канала<Input v-model="filters.search" type="search" maxlength="200" /></label>
                <label
                    >Состояние<select v-model="filters.active" class="list-select">
                        <option :value="null">Все состояния</option>
                        <option :value="1">Активные</option>
                        <option :value="0">Приостановленные</option>
                    </select></label
                >
                <label
                    >Порядок<select v-model="filters.sort" class="list-select">
                        <option value="">По названию</option>
                        <option value="desc">Сначала новые</option>
                        <option value="asc">Сначала старые</option>
                    </select></label
                >
                <label
                    >На странице<select v-model.number="filters.perPage" class="list-select">
                        <option v-for="size in [10, 20, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
                    </select></label
                >
                <Button :disabled="loading" type="submit">Применить фильтр</Button
                ><Button variant="outline" type="button" :disabled="loading" @click="reset">Очистить фильтры</Button>
            </form>
            <ListPanel
                external-controls
                :class="['channels', { 'single-page': page.props.channels.meta.last_page === 1 }]"
                :meta="page.props.channels.meta"
                :count="page.props.channels.data.length"
                :loading="loading"
                :error="error"
                :empty-title="opened ? 'Совпадений нет' : 'Подключите первый канал'"
                :empty-description="opened ? 'Попробуйте другое название или снимите фильтр.' : 'Выберите площадку и привяжите аккаунт к проекту.'"
                @page="changePage"
                @refresh="refresh"
            >
                <h2>Название канала</h2>
                <Link v-for="channel in page.props.channels.data" :key="channel.id" :href="route('channels.edit', channel.id)" class="channel-row">
                    <strong>{{ channel.name }}</strong><span :class="{ connected: channel.connected && channel.is_active }"
                        >{{ channel.type_label }} · {{ !channel.is_active ? 'Отключён' : channel.connected ? 'Подключён' : 'Нужен вход' }}</span
                    >
                </Link>
            </ListPanel>
        </div>
    </AppLayout>
</template>
<style scoped>
.tabs button {
    padding: 10px 12px;
    border-radius: 9px;
    color: var(--muted-foreground);
    background: var(--muted);
    font-size: 14px;
}
.tabs [aria-pressed='true'] {
    color: var(--primary);
    background: var(--sidebar-accent);
}
.channels {
    border: 0;
    background: transparent;
}
.tabs + .channels {
    margin-top: -8px;
}
.channels.single-page :deep(footer) {
    display: none;
}
.channels h2 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 16px;
}
.channel-row {
    display: grid;
    grid-template-columns: minmax(0, 0.87fr) minmax(0, 1fr);
    gap: 12px;
    align-items: center;
    min-height: 54px;
    border-bottom: 1px solid var(--border);
    overflow-wrap: anywhere;
}
.channel-row span {
    color: #ce9675;
    font-size: 14px;
}
.channel-row .connected {
    color: var(--primary);
}
form label {
    display: grid;
    gap: 6px;
}
@media (max-width: 699px) {
    .channel-row {
        grid-template-columns: 1fr;
        align-content: center;
        gap: 2px;
        min-height: 66px;
    }
}
</style>
