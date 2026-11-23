<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ImportProjectCard from '@/components/ImportProjectCard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProjectSummary } from '@/types/projects';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';
type Account = { id: string; external_id: string; name: string; platform: string };
type Choice = { id: string; channel_id: number | null };
type Step = 'source' | 'project' | 'channels' | 'map-project' | 'map-channel';
const props = defineProps<{
    search: string;
    connection: { id: number; name: string; driver: string };
    sources: { id: string; title: string | null }[];
    projects: Pick<ProjectSummary, 'id' | 'name' | 'cover'>[];
    accounts: Account[];
    channels: { id: number; name: string; type: string }[];
    mappings: Record<string, number>;
    importProjects: boolean;
    importAccounts: boolean;
    readsAccounts: boolean;
    accountError: string | null;
    sourceBindings: { external_id: string; project_id: number; clips_count: number }[];
    previous: {
        id: number;
        external_id: string | null;
        project_id: number | null;
        accounts: Choice[] | null;
        clip_count: number;
        imported_at: string | null;
    } | null;
}>();
const route = useRoute();
const allowed = ['source', 'project', 'channels', 'map-project', 'map-channel'] as const;
const requestedStep = String(route().params.step) as Step;
const step = ref<Step>(allowed.includes(requestedStep) ? requestedStep : props.importProjects ? 'source' : 'project');
function syncStep() {
    const s = (location.hash.slice(1) || new URLSearchParams(location.search).get('step')) as Step;
    step.value = allowed.includes(s) ? s : props.importProjects ? 'source' : 'project';
}
function show(s: Step) {
    location.hash = s;
    step.value = s;
}
onMounted(() => {
    syncStep();
    window.addEventListener('hashchange', syncStep);
});
onUnmounted(() => window.removeEventListener('hashchange', syncStep));
const previous = props.previous;
const form = useForm({
    external_id: (previous?.external_id ?? null) as string | null,
    project_id: (previous?.project_id ?? props.projects.find((p) => String(p.id) === String(route().params.project_id))?.id ?? null) as number | null,
    accounts: [] as Choice[],
});
const choices = reactive<Record<string, string>>(Object.fromEntries(Object.entries(props.mappings).map(([k, id]) => [k, String(id)])));
for (const entry of previous?.accounts ?? [])
    if (!Object.hasOwn(props.mappings, entry.id)) choices[entry.id] = entry.channel_id === null ? 'new' : String(entry.channel_id);
const accountId = ref(props.accounts.find((a) => a.id === String(route().params.account))?.id ?? props.accounts[0]?.id ?? null);
const account = computed(() => props.accounts.find((a) => a.id === accountId.value));
const local = computed(() => props.projects.find((p) => p.id === form.project_id));
const source = computed(() => props.sources.find((s) => s.id === form.external_id));
const binding = computed(() => props.sourceBindings.find((s) => s.external_id === form.external_id));
const count = computed(() => (previous && previous.external_id === form.external_id ? previous.clip_count : binding.value?.clips_count));
const accountsUnavailable = computed(() => !!props.accountError || !props.readsAccounts);

const query = ref('');
const found = computed(() => props.projects.filter((p) => p.name.toLocaleLowerCase('ru').includes(query.value.trim().toLocaleLowerCase('ru'))));
const target = computed(() => step.value.startsWith('map-'));
const canSubmit = computed(() => (props.importProjects ? !!form.external_id : !!form.project_id && Object.values(choices).some(Boolean)));
const eligible = computed(() => props.channels.filter((c) => account.value && c.type === account.value.platform));
const platforms: Record<string, string> = { youtube: 'YouTube', instagram: 'Instagram', tiktok: 'TikTok', vk: 'VK' };
const tabs = computed(() =>
    target.value
        ? [
              { name: 'Проект Poster', id: 'map-project' },
              { name: 'Каналы', id: 'map-channel' },
          ]
        : [
              ...(props.importProjects ? [{ name: 'Источник', id: 'source' }] : []),
              { name: 'Проект Poster', id: 'project' },
              ...((props.importAccounts && props.accounts.length) || !props.importProjects ? [{ name: 'Каналы', id: 'channels' }] : []),
          ],
);
watch(
    binding,
    (current, previousBinding) => {
        if (current) form.project_id = current.project_id;
        else if (previousBinding) form.project_id = null;
    },
    { immediate: true },
);
function submit() {
    if (step.value === 'source') return show('project');
    if (step.value === 'project' && props.importAccounts && props.accounts.length) return show('channels');
    form.accounts = props.accounts
        .filter((a) => !!choices[a.id])
        .map((a) => ({ id: a.id, channel_id: choices[a.id] === 'new' ? null : Number(choices[a.id]) }));
    form.post(route('clipping.preview', props.connection.id));
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
            { title: connection.name, href: route('clipping.edit', connection.id) },
            ...(target ? [{ title: 'Выбор источника', href: route('clipping.sources', connection.id) }] : []),
            { title: target ? 'Сопоставление' : 'Выбор источника', href: route('clipping.sources', connection.id) },
        ]"
    >
        <Head :title="target ? 'Сопоставление импорта' : 'Выбор источника'" />
        <div class="workspace-page source-picker">
            <Heading :title="target ? 'Сопоставление импорта' : 'Выбор источника'" />
            <nav aria-label="Шаги импорта">
                <a
                    v-for="t in tabs"
                    :key="t.id"
                    :href="'#' + t.id"
                    :aria-current="step === t.id ? 'page' : undefined"
                    @click.prevent="show(t.id as Step)"
                    >{{ t.name }}</a><Link v-if="target && previous" :href="route('clipping.preview.show', [connection.id, previous.id])"
                    >Предпросмотр</Link
                >
            </nav>
            <div class="pick-columns">
                <form class="studio-panel pick-panel" @submit.prevent="submit">
                    <template v-if="step === 'source'"
                        ><h2>{{ accountError || !readsAccounts ? 'Проект и клипы' : 'Проект провайдера' }}</h2>
                        <div class="stage-message">
                            <strong>Подключение</strong><small>{{ connection.name }}</small>
                        </div>
                        <div class="selector-block">
                            <label for="external-project">Проект провайдера</label
                            ><select class="list-select w-full" id="external-project" v-model="form.external_id" required>
                                <option :value="null" disabled>Выберите проект</option>
                                <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.title || 'Проект без названия' }}</option>
                            </select>
                        </div>
                        <div v-if="source" class="pick-row">
                            <span>Получено</span><span>{{ count == null ? 'После предпросмотра' : 'Клипов: ' + count }}</span>
                        </div>
                        <p v-if="search">
                            Поиск: {{ search }} <Link :href="route('clipping.sources', connection.id)" class="underline">Сбросить фильтр</Link>
                        </p>
                        <p v-if="!sources.length">{{ search ? 'Проекты не найдены.' : 'Доступных проектов нет.' }}</p>
                        <div v-if="accountsUnavailable" class="stage-message warning">
                            <strong>Аккаунты недоступны</strong>
                            <p>
                                {{
                                    accountError ||
                                    (!importAccounts
                                        ? 'Связи с аккаунтами исключены из области импорта.'
                                        : 'Драйвер не предоставляет список аккаунтов.')
                                }}
                                Клипы можно импортировать.
                            </p>
                        </div>
                        <p v-if="binding && local">Связан с проектом {{ local.name }}</p></template
                    >
                    <template v-else-if="step === 'project' || step === 'map-project'"
                        ><h2>{{ target ? 'Выберите проект Poster' : 'Проект назначения' }}</h2>
                        <div class="pick-row">
                            <span>Источник</span
                            ><span>{{ source?.title || 'Источник не выбран' }}{{ count != null ? ' · Клипов: ' + count : '' }}</span>
                        </div>
                        <p v-if="binding && local" class="studio-row">
                            <span>Связанный проект</span><strong>{{ local.name }}</strong>
                        </p>
                        <template v-else-if="target"
                            ><label for="project-search">Найти проект Poster</label><Input id="project-search" v-model="query" type="search" />
                            <label v-for="p in found" :key="p.id" class="pick-option"
                                ><input type="radio" v-model="form.project_id" :value="p.id" /><span
                                    ><strong>{{ p.name }}</strong
                                    ><small>Мои проекты</small></span
                                ></label
                            ></template
                        >
                        <div v-else class="grid gap-2">
                            <label for="destination">Проект Poster</label>
                            <select id="destination" v-model="form.project_id" class="list-select w-full">
                                <option :value="null" :disabled="!importProjects">
                                    {{ importProjects ? 'Создать новый проект' : 'Выберите проект' }}
                                </option>
                                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <label v-if="target && importProjects && !binding" class="pick-option"
                            ><input v-model="form.project_id" type="radio" :value="null" />Создать новый проект</label
                        >
                        <div v-if="importProjects && !form.project_id" class="stage-message">
                            <strong>Новая связь</strong><small>Проект будет создан при подтверждении импорта.</small>
                        </div></template
                    >
                    <template v-else
                        ><h2>Сопоставление аккаунта</h2>
                        <template v-if="account"
                            ><label v-if="!target && accounts.length > 1" for="choose-account">Аккаунт провайдера</label
                            ><select class="list-select w-full" v-if="!target && accounts.length > 1" id="choose-account" v-model="accountId">
                                <option v-for="a in accounts" :value="a.id" :key="a.id">{{ a.name || 'Аккаунт без названия' }}</option>
                            </select>
                            <div v-else class="pick-row">
                                <span>Аккаунт провайдера</span
                                ><span>{{ account.name || 'Аккаунт без названия' }} · {{ platforms[account.platform] || account.platform }}</span>
                            </div>
                            <label for="channel-target">Канал Poster</label
                            ><select class="list-select w-full" id="channel-target" v-model="choices[account.id]" :disabled="!!mappings[account.id]">
                                <option :value="undefined">Пропустить</option>
                                <option v-for="c in eligible" :value="String(c.id)" :key="c.id">
                                    {{ c.name }} · {{ platforms[c.type] || c.type }}
                                </option>
                                <option v-if="platforms[account.platform]" value="new">Создать канал</option>
                            </select>
                        </template>
                        <div v-else class="stage-message warning">
                            <strong>Аккаунты недоступны</strong>
                            <p>
                                {{
                                    accountError ||
                                    (!importAccounts
                                        ? 'Связи с аккаунтами исключены из области импорта.'
                                        : !readsAccounts
                                          ? 'Драйвер не предоставляет список аккаунтов.'
                                          : 'Доступных аккаунтов нет.')
                                }}
                            </p>
                            <Link v-if="!importAccounts" class="underline" :href="route('clipping.edit', connection.id)">Параметры подключения</Link>
                        </div></template
                    >
                    <InputError v-for="(msg, field) in form.errors" :key="field" :message="msg" /><Button
                        :disabled="form.processing || (step === 'project' && !importProjects ? !form.project_id : !canSubmit)"
                        >{{
                            step === 'source'
                                ? 'Далее к проекту'
                                : step === 'project' && importAccounts && accounts.length
                                  ? 'Далее к каналам'
                                  : step === 'map-project'
                                    ? 'Применить выбор'
                                    : step === 'map-channel'
                                      ? 'Сохранить сопоставление'
                                      : 'Предпросмотр импорта'
                        }}</Button
                    >
                </form>
                <ImportProjectCard
                    :name="local?.name || source?.title || connection.name"
                    :cover="local?.cover"
                    :connection="connection.name"
                    :count="count"
                />
            </div></div
    ></AppLayout>
</template>
<style scoped>
.source-picker {
    padding-top: 19px;
    gap: 18px;
}
.source-picker nav {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.source-picker nav a {
    background: var(--muted);
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 14px;
}
.source-picker nav [aria-current] {
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 14%, var(--card));
}
.pick-columns {
    display: grid;
    grid-template-columns: minmax(0, 2.36fr) minmax(0, 1fr);
    gap: 20px;
    align-items: start;
}
.pick-panel {
    display: grid;
    gap: 8px;
}
.pick-panel h2 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 8px;
}

.pick-panel > label:not(.pick-option),
.pick-panel small,
.pick-row > span:first-child {
    color: var(--muted-foreground);
    font-size: 14px;
}
.selector-block {
    display: grid;
    gap: 8px;
}
.selector-block label {
    color: var(--muted-foreground);
    font-size: 14px;
}

.pick-option {
    display: flex;
    gap: 12px;
    align-items: start;
    padding: 9px 0;
    border-bottom: 1px solid var(--border);
}
.pick-option input {
    margin-top: 4px;
    accent-color: var(--primary);
}
.pick-option small,
.stage-message small {
    display: block;
}
.stage-message {
    padding: 12px;
    border-left: 3px solid var(--muted-foreground);
    background: var(--muted);
    border-radius: 6px;
}
.stage-message.warning {
    border-color: #e8ac7d;
}
.pick-row {
    display: grid;
    grid-template-columns: 36% 1fr;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}
.pick-panel [data-slot='button'] {
    justify-self: start;
    margin-top: 8px;
    min-height: 44px;
    font-weight: 700;
}
@media (min-width: 1920px) {
    .pick-columns {
        max-width: 1236px;
    }
}
@media (max-width: 1099px) {
    .pick-columns {
        grid-template-columns: 1fr;
    }
}
</style>
