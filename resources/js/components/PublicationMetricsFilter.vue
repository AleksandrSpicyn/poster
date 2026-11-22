<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { counterNames, platformNames } from '@/types/analytics';
import type { AnalyticsFilters, MetricsReport } from '@/types/analytics';

const props = defineProps<{ report: MetricsReport; detail?: boolean; interval: string }>();
const emit = defineEmits<{ selectProject: [project: number | null] }>();
const page = usePage();
const route = useRoute();
const filters = computed(() => props.report.filters);
const draft = reactive<AnalyticsFilters>({ ...filters.value, exclude_channels: [...filters.value.exclude_channels] });
const custom = ref(!!filters.value.from);
const applying = ref(false);
const requestError = ref('');
watch(
    () => JSON.stringify(props.report.filters),
    () => {
        const value = props.report.filters;
        Object.assign(draft, value, { exclude_channels: [...value.exclude_channels] });
        custom.value = !!value.from;
    },
);
watch(
    () => draft.project,
    (project) => emit('selectProject', project),
    { immediate: true },
);
const filterChannels = computed(() =>
    props.report.channels.filter(
        (c) => (draft.project === null || c.projects.includes(draft.project)) && (!draft.platform || c.platform === draft.platform),
    ),
);
function apply() {
    if (applying.value) return;
    applying.value = true;
    requestError.value = '';
    const values = { ...draft, from: custom.value ? draft.from : null, to: custom.value ? draft.to : null };
    router.get(route(props.detail ? 'dashboard.analytics' : 'dashboard'), values, {
        onHttpException: (response) => {
            if (response.status === 419) return;
            requestError.value = response.status === 403 ? 'Доступ к отчёту ограничен.' : 'Не удалось загрузить отчёт. Повторите попытку.';
            return false;
        },
        onNetworkError: () => {
            requestError.value = 'Нет связи. Проверьте подключение и повторите попытку.';
            return false;
        },
        onFinish: () => {
            applying.value = false;
        },
    });
}
function reset() {
    Object.assign(draft, {
        days: 7,
        metric: 'views',
        project: null,
        platform: null,
        format: null,
        channel: null,
        from: null,
        to: null,
        exclude_channels: [],
    });
    custom.value = false;
    requestError.value = '';
}
function choosePeriod(event: Event) {
    const v = (event.target as HTMLSelectElement).value;
    custom.value = v === 'custom';
    if (!custom.value) draft.days = Number(v);
}
function toggle(id: number, event: Event) {
    draft.exclude_channels = (event.target as HTMLInputElement).checked
        ? draft.exclude_channels.filter((x) => x !== id)
        : [...draft.exclude_channels, id];
}
</script>
<template>
    <form :aria-busy="applying" class="studio-panel filter-panel" @submit.prevent="apply">
        <h2>Фильтры</h2>
        <label for="metric-period">Период</label
        ><select id="metric-period" :value="custom ? 'custom' : draft.days" @change="choosePeriod">
            <option v-for="d in [7, 28, 30, 90]" :key="d" :value="d">
                {{ filters.days === d && !filters.from ? interval : d + ' дней' }}
            </option>
            <option value="custom">Произвольный интервал</option>
        </select>
        <div v-if="custom" class="date-pair">
            <label>С <input v-model="draft.from" type="date" required :max="draft.to ?? undefined" /></label
            ><label>По <input v-model="draft.to" type="date" required :min="draft.from ?? undefined" /></label>
        </div>
        <label for="metric-project">Проект</label
        ><select id="metric-project" v-model="draft.project">
            <option :value="null">Все проекты</option>
            <option v-for="p in report.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <label for="metric-platform">Площадка</label>
        <select id="metric-platform" v-model="draft.platform">
            <option :value="null">Все площадки</option>
            <option v-for="(label, value) in platformNames" :key="value" :value="value">{{ label }}</option>
        </select>
        <label for="metric-format">Формат производства</label>
        <select id="metric-format" v-model="draft.format">
            <option :value="null">Все форматы</option>
            <option value="short">Короткие ролики</option>
            <option value="series">Сериалы</option>
        </select>
        <label for="metric-counter">Показатель</label>
        <select id="metric-counter" v-model="draft.metric">
            <option v-for="(label, value) in counterNames" :key="value" :value="value">{{ label }}</option>
        </select>
        <label v-for="c in filterChannels" :key="c.id" class="filter-channel"
            ><input type="checkbox" :checked="!draft.exclude_channels.includes(c.id)" @change="toggle(c.id, $event)" /><span
                ><strong>{{ c.name }} · {{ platformNames[c.platform] ?? c.platform }}</strong
                ><small>{{ draft.exclude_channels.includes(c.id) ? 'Исключён' : 'Включён в выборку' }}</small></span
            ></label
        >
        <p v-if="page.props.errors.from || page.props.errors.to" class="text-destructive" role="alert">
            {{ page.props.errors.from || page.props.errors.to }}
        </p>
        <p v-if="requestError" role="alert" class="text-destructive">{{ requestError }}</p>
        <div class="flex flex-wrap gap-2">
            <Button :disabled="applying">{{ applying ? 'Загрузка…' : 'Применить фильтр' }}</Button><Button
                type="button"
                variant="outline"
                :disabled="applying"
                @click="reset"
                >Сбросить</Button
            >
        </div>
    </form>
</template>
<style scoped>
.filter-panel {
    display: grid;
    gap: 4px;
}
.filter-panel select,
.date-pair input {
    padding: 0 12px;
    border: 1px solid var(--input);
    border-radius: 8px;
    width: 100%;
    height: 46px;
    background: var(--background);
    color: var(--foreground);
}
.filter-panel > label:not(.filter-channel) {
    font-size: 14px;
    color: var(--muted-foreground);
}
.filter-panel select {
    margin: 0 0 4px;
}
.filter-channel {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 7px 0;
    border-bottom: 1px solid var(--border);
}
.filter-channel input {
    margin-top: 5px;
    accent-color: var(--primary);
    width: 20px;
    height: 20px;
}
.date-pair {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.filter-panel > div:last-child {
    margin-top: 10px;
}

.filter-panel h2 {
    margin: 0 0 8px;
    font-size: 20px;
    font-weight: 700;
}
.filter-channel small {
    display: block;
    color: var(--muted-foreground);
    font-size: 14px;
}
.filter-panel [data-slot='button'] {
    min-height: 44px;
    font-weight: 700;
}
</style>
