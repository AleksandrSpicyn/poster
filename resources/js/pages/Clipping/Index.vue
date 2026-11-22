<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import { ref } from 'vue';
import { Input } from '@/components/ui/input';
import { useRoute } from 'ziggy-js';

type AccessStatus = 'available' | 'denied' | 'unavailable' | 'unsupported' | 'unchecked';
type Connection = {
    id: number;
    name: string;
    driver: string;
    access: Partial<Record<'projects' | 'clips' | 'accounts' | 'metrics', AccessStatus>> | null;
};
const props = defineProps<{ connections: Connection[]; drivers: Record<string, string> }>();
const page = usePage<SharedData>();
const route = useRoute();
const filtering = ref(false);
const selectedConnection = ref(props.connections[0]?.id ?? null);
const sourceTitle = ref('');
function findSources() {
    if (selectedConnection.value !== null) {
        router.get(route('clipping.sources', selectedConnection.value), { search: sourceTitle.value.trim() });
    }
}
const labels: Record<AccessStatus, string> = {
    available: 'Чтение',
    denied: 'Нет доступа',
    unavailable: 'Проверка не удалась',
    unsupported: 'Не поддерживается',
    unchecked: 'Не проверено',
};
function rows(connection: Connection) {
    const access = connection.access;
    const project = access?.projects ?? 'unchecked',
        clips = access?.clips ?? 'unchecked';
    const sources = route('clipping.sources', connection.id);
    return [
        ...(project === clips
            ? [{ name: 'Проекты и клипы', status: clips, href: sources }]
            : [
                  { name: 'Проекты', status: project, href: sources },
                  { name: 'Клипы', status: clips, href: sources },
              ]),
        {
            name: 'Социальные аккаунты',
            status: access?.accounts ?? 'unchecked',
            href: route('clipping.sources', { connection: connection.id, step: 'channels' }),
        },
        { name: 'Метрики провайдера', status: access?.metrics ?? 'unchecked', href: route('clipping.history') },
    ];
}
</script>
<template>
    <AppLayout :breadcrumbs="[{ title: 'Источники', href: route('clipping.index') }]">
        <Head title="Провайдеры клиппинга" />
        <div class="workspace-page providers">
            <header>
                <Heading title="Провайдеры клиппинга" description="Подключения этого пользователя" />
                <Button as-child><Link :href="route('clipping.create')">Добавить подключение</Link></Button>
            </header>
            <p v-if="page.props.errors.connection" role="alert" class="text-destructive">{{ page.props.errors.connection }}</p>
            <nav aria-label="Источники">
                <button type="button" :aria-pressed="!filtering" @click="filtering = false">Все</button>
                <button
                    type="button"
                    aria-controls="provider-filters"
                    :aria-expanded="filtering"
                    :aria-pressed="filtering"
                    @click="filtering = !filtering"
                >
                    Фильтры
                </button>
            </nav>
            <form v-if="filtering" id="provider-filters" class="studio-panel grid max-w-2xl gap-3" @submit.prevent="findSources">
                <h2>Фильтры</h2>
                <label for="provider-connection">Подключение</label>
                <select id="provider-connection" v-model="selectedConnection" class="list-select" required>
                    <option v-if="!connections.length" :value="null" disabled>Нет подключений</option>
                    <option v-for="connection in connections" :key="connection.id" :value="connection.id">{{ connection.name }}</option>
                </select>
                <label for="provider-project-name">Название внешнего проекта</label>
                <Input id="provider-project-name" v-model="sourceTitle" type="search" maxlength="200" />
                <Button type="submit" :disabled="!selectedConnection" class="justify-self-start">Применить</Button>
                <Link :href="route('clipping.history')" class="justify-self-start underline">История импорта</Link>
            </form>
            <section>
                <h2>Подключение и возможности</h2>
                <p v-if="!connections.length">Подключите провайдера для импорта проектов и клипов.</p>
                <section v-for="connection in connections" :key="connection.id" :aria-label="connection.name">
                    <Link
                        class="provider-row"
                        :href="route('clipping.edit', connection.id)"
                        :aria-label="connection.name + ' · Параметры подключения'"
                        ><strong>{{ connection.name }}</strong
                        ><span
                            >{{ drivers[connection.driver] ?? connection.driver }} ·
                            {{ connection.access?.projects === 'available' ? 'Доступен' : labels[connection.access?.projects ?? 'unchecked'] }}</span
                        ></Link
                    >
                    <Link v-for="row in rows(connection)" :key="row.name" class="provider-row" :href="row.href"
                        ><strong>{{ row.name }}</strong
                        ><span :data-status="row.status">{{ labels[row.status] }}</span></Link
                    >
                </section>
            </section>
        </div>
    </AppLayout>
</template>
<style scoped>
.providers header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 16px;
}
.providers header [data-slot='button'] {
    min-height: 44px;
    font-weight: 700;
}
.providers nav {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.providers nav button {
    background: var(--muted);
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 14px;
}
.providers nav [aria-pressed='true'] {
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 14%, var(--card));
}
.providers h2 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 14px;
}
.provider-row {
    display: grid;
    grid-template-columns: minmax(0, 0.88fr) minmax(0, 1fr);
    gap: 12px;
    border-bottom: 1px solid var(--border);
    padding: 15px 0;
    overflow-wrap: anywhere;
}
.provider-row span {
    color: var(--muted-foreground);
}
.provider-row:hover strong {
    text-decoration: underline;
}
.provider-row [data-status='denied'],
.provider-row [data-status='unsupported'],
.provider-row [data-status='unavailable'] {
    color: #e8ac7d;
}
@media (max-width: 1099px) {
    .providers header {
        display: grid;
        justify-items: start;
    }
}
@media (max-width: 767px) {
    .provider-row {
        grid-template-columns: 1fr;
        gap: 4px;
    }
}
</style>
