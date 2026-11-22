<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import type { ProjectSummary } from '@/types/projects';
import { CircleAlert, CircleCheck, CircleDashed, Clock3 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'ziggy-js';

type Source = {
    project_id: number;
    id: number;
    clipping_connection_id: number;
    external_id: string;
    title: string | null;
    last_synced_at: string | null;
    sync_paused_at: string | null;
    sync_error: string | null;
    publications_pending: boolean;
};
type Import = {
    id: number;
    clipping_connection_id: number;
    external_id: string | null;
    source_name: string | null;
    imported_at: string | null;
    clip_count: number;
    account_count: number;
};
type Run = {
    id: number;
    opus_source_id: number;
    clips_received: number;
    clips_added: number;
    publications_added: number;
    metrics_added: number;
    clips_completed_at: string | null;
    resumed_at: string | null;
    completed_at: string | null;
    created_at: string;
};
type Connection = { id: number; name: string; access_checked_at: string | null; access_result: Record<string, string> | null };
type MetricStatus = { connection_id: number; name: string; status: string };
const props = defineProps<{
    projects: Pick<ProjectSummary, 'id' | 'name' | 'cover'>[];
    sources: Source[];
    imports: Import[];
    runs: Run[];
    connections: Connection[];
    metricStatuses: MetricStatus[];
}>();
const route = useRoute();
const areas = { clips: 'Проекты и клипы', accounts: 'Аккаунты', publications: 'Публикации', metrics: 'Метрики' };
type Area = keyof typeof areas;
const filterOpen = ref(false);
const connectionId = ref(0);
const selectedArea = ref<Area | ''>('');
const openArea = ref<Area | null>(null);
const hash = ref('');
const refreshPending = ref(false);
const refreshError = ref('');
const updateHash = () => {
    hash.value = location.hash;
};
onMounted(() => {
    updateHash();
    window.addEventListener('hashchange', updateHash);
});
onUnmounted(() => window.removeEventListener('hashchange', updateHash));
const date = new Intl.DateTimeFormat('ru', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', timeZone: 'UTC' });
function time(value: string) {
    return date.format(new Date(value)) + ' UTC';
}
const sources = computed(() => props.sources.filter((source) => !connectionId.value || source.clipping_connection_id === connectionId.value));
const previews = computed(() => props.imports.filter((run) => !connectionId.value || run.clipping_connection_id === connectionId.value));
const selectedRun = computed(() => props.runs.find((run) => hash.value === '#run-' + run.id));
const source = computed(() =>
    props.sources.find((item) => (selectedRun.value ? item.id === selectedRun.value.opus_source_id : hash.value === '#source-' + item.id)),
);
const run = computed(() => selectedRun.value ?? props.runs.find((item) => item.opus_source_id === source.value?.id));
const project = computed(() => props.projects.find((item) => item.id === source.value?.project_id));
const connection = computed(() => props.connections.find((item) => item.id === source.value?.clipping_connection_id));
const latest = computed(() => sources.value.flatMap((source) => props.runs.find((run) => run.opus_source_id === source.id) ?? []));
const partial = computed(() => !!source.value?.publications_pending && !selectedRun.value?.completed_at);
const paused = computed(() => !!source.value?.sync_paused_at && !selectedRun.value?.completed_at);
const checkedAccess = computed(
    () =>
        !!connection.value?.access_checked_at &&
        !!source.value?.sync_paused_at &&
        connection.value.access_result?.source === source.value.external_id &&
        new Date(connection.value.access_checked_at) >= new Date(source.value.sync_paused_at) &&
        connection.value.access_result[source.value.publications_pending ? 'publications' : 'clips'] === 'available',
);
const summaryTitle = computed(() =>
    paused.value
        ? 'Приостановленная синхронизация'
        : partial.value
          ? 'Клипы получены, публикации ожидаются'
          : run.value?.resumed_at && run.value.completed_at
            ? 'Успешное продолжение импорта'
            : 'Синхронизация источника',
);
const statuses = computed(() => props.metricStatuses.filter((status) => !connectionId.value || status.connection_id === connectionId.value));
const filteredAreas = computed(() =>
    (Object.keys(areas) as Area[]).filter((area) => !selectedArea.value || area === selectedArea.value).map((area) => ({ area, ...status(area) })),
);
function status(area: Area) {
    if (area === 'accounts') {
        if (previews.value.some((preview) => !preview.imported_at && preview.account_count > 0))
            return { label: 'Нужно сопоставление', tone: 'warning' };
        const n = previews.value.filter((run) => run.imported_at).reduce((sum, run) => sum + run.account_count, 0);
        return n ? { label: 'Подтверждено в импортах · ' + n, tone: 'success' } : { label: 'Нет сопоставлений' };
    }
    if (area === 'metrics') {
        if (statuses.value.some((item) => item.status === 'rate_limited')) return { label: 'Пауза по лимиту API', tone: 'warning' };
        if (statuses.value.some((item) => item.status === 'no_permission')) return { label: 'Требуется доступ к статистике', tone: 'warning' };
        if (statuses.value.some((item) => item.status === 'temporary_error')) return { label: 'Временная ошибка сбора', tone: 'warning' };
        const n = latest.value.reduce((sum, run) => sum + Number(run.metrics_added), 0);
        return n ? { label: 'Получено наблюдений · ' + n, tone: 'success' } : { label: 'Нет новых наблюдений' };
    }
    const pending = sources.value.filter((source) => (area === 'publications' ? source.publications_pending : !source.publications_pending));
    if (pending.some((source) => source.sync_paused_at)) return { label: 'Синхронизация приостановлена', tone: 'warning' };
    if (pending.some((source) => source.sync_error === 'HTTP 429')) return { label: 'Пауза по лимиту API', tone: 'warning' };
    if (pending.some((source) => source.sync_error)) return { label: 'Ожидает повторной загрузки', tone: 'warning' };
    if (area === 'publications' && pending.length) return { label: 'Частичное обновление', tone: 'warning' };
    if (!latest.value.length) return { label: 'Нет результатов запуска' };
    const n = latest.value.reduce((sum, run) => sum + (area === 'clips' ? run.clips_received : run.publications_added), 0);
    const complete = latest.value.every((run) => (area === 'clips' ? run.clips_completed_at || run.completed_at : run.completed_at));
    return { label: area === 'clips' ? 'Получено клипов · ' + n : 'Добавлено публикаций · ' + n, tone: complete ? 'success' : undefined };
}
function clearFilters() {
    connectionId.value = 0;
    selectedArea.value = '';
    filterOpen.value = false;
    openArea.value = null;
}
function refresh() {
    if (refreshPending.value) return;
    refreshPending.value = true;
    router.reload({
        onSuccess: async () => {
            const restoreFocus = document.activeElement?.id === 'history-retry';
            refreshError.value = '';
            if (restoreFocus) {
                await nextTick();
                document.getElementById('history-refresh')?.focus();
            }
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            refreshError.value =
                response.status === 403
                    ? 'Доступ к списку ограничен.'
                    : 'Не удалось обновить список. На экране остались последние загруженные данные.';
            return false;
        },
        onNetworkError: () => {
            refreshError.value = 'Нет связи. Проверьте подключение и повторите попытку.';
            return false;
        },
        onFinish: () => {
            refreshPending.value = false;
        },
    });
}
</script>
<template>
    <AppLayout :breadcrumbs="[{ title: 'Источники', href: route('clipping.index') }]">
        <Head title="История импорта" />
        <div class="workspace-page import-history" :class="{ 'history-state-page': source }">
            <div class="history-heading">
                <Heading
                    title="История импорта"
                    :description="
                        source ? (paused ? 'Синхронизация' : partial ? 'Ход выполнения' : 'Результат') : 'Основной импорт / Последние запуски'
                    "
                />
                <Button v-if="!source" id="history-refresh" :aria-disabled="refreshPending" @click="refresh">{{
                    refreshPending ? 'Обновление…' : 'Обновить'
                }}</Button>
            </div>
            <template v-if="!source">
                <div
                    v-if="refreshError"
                    role="alert"
                    class="border-destructive/20 bg-destructive/5 text-destructive flex flex-wrap items-center justify-between gap-3 rounded-lg border px-5 py-3 text-sm"
                >
                    <p>{{ refreshError }}</p>
                    <Button id="history-retry" variant="outline" size="sm" class="h-11 sm:h-9" :aria-disabled="refreshPending" @click="refresh"
                        >Повторить</Button
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="history-chip" :aria-pressed="!selectedArea && !connectionId" @click="clearFilters">Все</button>
                    <button
                        type="button"
                        class="history-chip"
                        :aria-expanded="filterOpen"
                        aria-controls="history-filters"
                        @click="filterOpen = !filterOpen"
                    >
                        Фильтры
                    </button>
                </div>
                <div v-if="filterOpen" id="history-filters" class="flex flex-wrap gap-4">
                    <label class="grid gap-2 text-sm"
                        >Подключение<select v-model="connectionId" class="list-select">
                            <option :value="0">Все подключения</option>
                            <option v-for="item in connections" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select></label
                    >
                    <label class="grid gap-2 text-sm"
                        >Область данных<select v-model="selectedArea" class="list-select">
                            <option value="">Все области</option>
                            <option v-for="(label, key) in areas" :key="key" :value="key">{{ label }}</option>
                        </select></label
                    >
                </div>
                <section id="history">
                    <h2>Область данных</h2>
                    <div v-for="{ area, label, tone } in filteredAreas" :key="area" class="history-resource">
                        <button
                            class="history-row"
                            type="button"
                            :aria-expanded="openArea === area"
                            @click="openArea = openArea === area ? null : area"
                        >
                            <strong>{{ areas[area] }}</strong><span :data-status="tone">{{ label }}</span>
                        </button>
                        <div v-if="openArea === area" class="grid gap-2 py-3">
                            <template v-if="area === 'accounts' || area === 'clips'">
                                <Link
                                    v-for="item in previews.filter((item) => area === 'clips' || item.account_count > 0)"
                                    :key="item.id"
                                    :href="route('clipping.preview.show', [item.clipping_connection_id, item.id])"
                                    class="history-link"
                                    >{{ item.source_name || 'Предпросмотр импорта' }} · {{ item.imported_at ? 'Подтверждено' : 'Предпросмотр' }}</Link
                                >
                            </template>
                            <template v-if="area !== 'accounts'">
                                <a v-for="item in sources" :key="item.id" :href="'#source-' + item.id" class="history-link">{{
                                    item.title || 'Источник без названия'
                                }}</a>
                                <a
                                    v-for="item in runs.filter((item) => sources.some((source) => source.id === item.opus_source_id))"
                                    :key="item.id"
                                    :href="'#run-' + item.id"
                                    class="history-link"
                                    >{{ sources.find((source) => source.id === item.opus_source_id)?.title }} · {{ time(item.created_at) }}</a
                                >
                            </template>
                            <template v-if="area === 'metrics'"
                                ><p v-for="item in statuses" :key="item.connection_id + ':' + item.name" class="text-muted-foreground text-sm">
                                    {{ item.name }} ·
                                    {{
                                        {
                                            rate_limited: 'Пауза по лимиту API',
                                            no_permission: 'Требуется доступ',
                                            temporary_error: 'Временная ошибка',
                                            collected: 'Данные получены',
                                            unsupported: 'Сбор недоступен',
                                            no_data: 'Нет наблюдений',
                                        }[item.status]
                                    }}
                                </p>
                            </template>
                            <p v-if="!sources.length && !previews.length" class="text-muted-foreground text-sm">Импортов пока нет.</p>
                        </div>
                    </div>
                </section>
            </template>
            <template v-else>
                <div class="history-columns">
                    <section class="studio-panel history-detail" :aria-label="summaryTitle">
                        <h2>{{ paused ? 'Синхронизация' : partial ? 'Ход выполнения' : 'Результат' }}</h2>
                        <div v-if="paused" role="status" class="history-state warning">
                            <CircleAlert aria-hidden="true" />
                            <strong>Синхронизация приостановлена</strong>
                            <p>Проверьте доступ к подключению.</p>
                        </div>
                        <div v-else-if="partial" role="status" class="history-state">
                            <Clock3 aria-hidden="true" />
                            <strong>Частичное обновление</strong>
                            <p>История публикаций пока не получена.</p>
                        </div>
                        <div v-else-if="run?.completed_at" role="status" class="history-state success">
                            <CircleCheck aria-hidden="true" />
                            <strong>Обновление завершено</strong>
                            <p v-if="run.resumed_at">Продолжено с сохранённой позиции.</p>
                        </div>
                        <div v-else class="history-state">
                            <strong>{{ source.sync_error ? 'Ожидает повторной загрузки' : 'Состояние источника' }}</strong>
                        </div>
                        <dl>
                            <template v-if="paused">
                                <div class="studio-row">
                                    <dt>Последняя успешная загрузка</dt>
                                    <dd>{{ source.last_synced_at ? time(source.last_synced_at) : 'Ещё не завершена' }}</dd>
                                </div>
                                <div :class="checkedAccess ? 'history-state success' : 'studio-row'">
                                    <CircleCheck v-if="checkedAccess" aria-hidden="true" />
                                    <dt>Проверка доступа</dt>
                                    <dd v-if="checkedAccess && connection?.access_checked_at">
                                        Доступ подтверждён · {{ time(connection.access_checked_at) }}
                                    </dd>
                                    <dd v-else>
                                        <Link
                                            :href="route('clipping.access', { connection: source.clipping_connection_id, source_id: source.id })"
                                            class="underline"
                                            >Проверить доступ</Link
                                        >
                                    </dd>
                                </div>
                                <div class="studio-row">
                                    <dt>Точка продолжения</dt>
                                    <dd>{{ source.publications_pending ? 'После сохранения клипов' : 'С загрузки проектов и клипов' }}</dd>
                                </div>
                            </template>
                            <template v-else>
                                <div class="history-step">
                                    <CircleCheck aria-hidden="true" />
                                    <dt>{{ partial ? 'Проекты и клипы' : 'Клипы' }}</dt>
                                    <dd>{{ run ? 'Получено · ' + run.clips_received + ' · новых ' + run.clips_added : 'Нет результата запуска' }}</dd>
                                </div>
                                <div class="history-step">
                                    <component :is="partial ? Clock3 : CircleCheck" aria-hidden="true" />
                                    <dt>Публикации</dt>
                                    <dd>
                                        {{
                                            partial
                                                ? 'Ожидает следующую страницу провайдера'
                                                : run?.completed_at
                                                  ? 'Добавлено · ' + run.publications_added
                                                  : 'Нет результата запуска'
                                        }}
                                    </dd>
                                </div>
                                <div class="history-step">
                                    <component :is="run?.metrics_added ? CircleCheck : CircleDashed" aria-hidden="true" />
                                    <dt>Метрики</dt>
                                    <dd>
                                        {{
                                            run?.metrics_added
                                                ? 'Получено наблюдений · ' + run.metrics_added
                                                : partial
                                                  ? 'После получения публикаций'
                                                  : 'Нет новых наблюдений'
                                        }}
                                    </dd>
                                </div>
                                <div class="studio-row">
                                    <dt>Последнее полное обновление</dt>
                                    <dd>
                                        {{
                                            run?.completed_at
                                                ? time(run.completed_at)
                                                : source.last_synced_at
                                                  ? time(source.last_synced_at)
                                                  : 'Ещё не завершено'
                                        }}
                                    </dd>
                                </div>
                            </template>
                        </dl>
                        <Form
                            v-if="paused"
                            :action="route('clipping.sources.resume', [source.clipping_connection_id, source.id])"
                            method="post"
                            preserve-scroll
                            v-slot="{ processing }"
                        >
                            <Button type="submit" :disabled="processing">Возобновить</Button>
                        </Form>
                    </section>
                    <aside v-if="project" class="studio-panel history-preview">
                        <ProjectArtwork :name="project.name" :cover="project.cover" class="rounded-lg" />
                        <h2>{{ project.name }}</h2>
                        <p v-if="paused">Источник · {{ source.title || project.name }}</p>
                        <p v-if="paused">Клипы · {{ run?.clips_received ?? '—' }}</p>
                    </aside>
                </div>
                <a href="#history" class="history-link">К истории импорта</a>
            </template>
        </div>
    </AppLayout>
</template>
<style scoped>
.history-heading {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 12px;
}
.history-heading button {
    min-height: 44px;
}
.history-chip {
    min-height: 40px;
    padding: 0 12px;
    background: var(--muted);
    color: var(--muted-foreground);
    border-radius: 8px;
    font-size: 14px;
}
.history-chip[aria-pressed='true'],
.history-chip[aria-expanded='true'] {
    color: var(--primary);
    background: var(--sidebar-accent);
}
.import-history h2 {
    font-size: 20px;
    font-weight: 700;
    line-height: 1.4;
}
.history-resource {
    border-bottom: 1px solid var(--border);
}
.history-row {
    display: grid;
    grid-template-columns: 47% 1fr;
    gap: 8px;
    align-items: center;
    text-align: left;
    width: 100%;
    min-height: 52px;
}
.history-row span {
    color: var(--muted-foreground);
    font-size: 14px;
}
.history-resource:first-of-type {
    margin-top: 14px;
}
.history-row [data-status='success'] {
    color: var(--sidebar-accent-foreground);
}
.history-row [data-status='warning'] {
    color: light-dark(#84482e, #e6bba4);
}
.history-link {
    width: fit-content;
    text-decoration: underline;
    font-size: 14px;
}
.history-detail {
    max-width: 780px;
}
.history-state {
    padding: 14px 16px;
    border-inline-start: 2px solid var(--primary);
    background: var(--muted);
    margin: 16px 0;
}
.history-detail form {
    margin-top: 16px;
}

.history-columns {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 0.45fr);
    gap: 16px;
    align-items: start;
}
.history-detail {
    width: 100%;
    max-width: none;
}
.history-preview {
    padding: 16px;
}
.history-preview > :first-child {
    aspect-ratio: 2.45;
}
.history-preview h2 {
    margin-block: 12px 4px;
}
.history-state-page {
    padding-top: 12px;
    gap: 16px;
}
.history-detail .history-state,
.history-step {
    display: grid;
    grid-template-columns: 20px 1fr;
    column-gap: 12px;
    align-items: start;
    text-align: left;
}
.history-state svg,
.history-step svg {
    width: 20px;
    height: 20px;
    grid-column: 1;
    grid-row: 1 / 3;
}
.history-state p,
.history-state dd,
.history-step dd {
    grid-column: 2;
    color: var(--muted-foreground);
    font-size: 14px;
}
.history-state.warning {
    border-color: var(--color-amber-500);
}
.history-state.warning strong {
    color: var(--color-amber-600);
}
.history-state.success strong {
    color: var(--primary);
}
.history-step {
    padding-block: 10px;
    border-bottom: 1px solid var(--border);
}
.history-detail .studio-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr);
    gap: 12px;
    font-size: 14px;
}
.history-detail .studio-row dt {
    color: var(--muted-foreground);
}
@media (max-width: 1099px) {
    .history-columns {
        display: block;
    }
    .history-preview {
        display: none;
    }
}
@media (max-width: 767px) {
    .history-detail .studio-row {
        grid-template-columns: 1fr;
        gap: 4px;
    }
}
@media (max-width: 1099px) {
    .history-heading {
        align-items: start;
        flex-direction: column;
    }
}
@media (max-width: 767px) {
    .history-row {
        grid-template-columns: 1fr;
        gap: 2px;
        padding: 10px 0;
    }
}
</style>
