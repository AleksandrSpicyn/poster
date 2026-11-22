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
const step = ref<Step>(allowed.includes(requestedStep) ? requestedStep : props.importProjects ? 'source' : 'channels');
function syncStep() {
    const s = (location.hash.slice(1) || new URLSearchParams(location.search).get('step')) as Step;
    if (allowed.includes(s)) step.value = s;
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
const summarySource = computed(() => accountsUnavailable.value && !!source.value);
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
              { name: 'Каналы', id: 'channels' },
          ],
);
watch(
    () => form.external_id,
    () => {
        if (binding.value) form.project_id = binding.value.project_id;
    },
);
function submit() {
    form.accounts = props.accounts
        .filter((a) => !!choices[a.id])
        .map((a) => ({ id: a.id, channel_id: choices[a.id] === 'new' ? null : Number(choices[a.id]) }));
    form.post(route('clipping.preview', props.connection.id));
}
function selectMode(mode: 'existing' | 'new') {
    form.project_id = mode === 'existing' ? (form.project_id ?? props.projects[0]?.id ?? null) : null;
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
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
                        <div v-if="!summarySource" class="stage-message">
                            <strong>Подключение</strong><small>{{ connection.name }}</small>
                        </div>
                        <div :class="{ 'pick-row': summarySource, 'selector-block': !summarySource }">
                            <label for="external-project">Проект провайдера</label
                            ><select id="external-project" v-model="form.external_id" required>
                                <option :value="null" disabled>Выберите проект</option>
                                <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.title || 'Проект без названия' }}</option>
                            </select>
                        </div>
                        <div v-if="summarySource" class="pick-row">
                            <span>Получено</span><span>{{ count == null ? 'После предпросмотра' : 'Клипов: ' + count }}</span>
                        </div>
                        <p v-if="!sources.length">Доступных проектов нет.</p>
                        <template v-if="!summarySource"
                            ><label v-for="s in sources" :key="s.id" class="pick-option"
                                ><input type="radio" :value="s.id" v-model="form.external_id" /><span
                                    ><strong>{{ s.title || 'Проект без названия' }}</strong
                                    ><small v-if="sourceBindings.some((x) => x.external_id === s.id)">Связан с проектом Poster</small></span
                                ></label
                            >
                        </template>
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
                        <label for="local-project">Проект Poster</label
                        ><select id="local-project" v-model="form.project_id" :disabled="!!binding">
                            <option :value="null">Создать новый проект</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <div v-if="summarySource" class="pick-row">
                            <span>Сопоставление каналов</span><span>Пропущено · можно подключить канал позднее</span>
                        </div></template
                    >
                    <template v-else-if="step === 'project' || step === 'map-project'"
                        ><h2>{{ target ? 'Выберите проект Poster' : 'Проект назначения' }}</h2>
                        <div class="pick-row">
                            <span>Источник</span
                            ><span>{{ source?.title || 'Источник не выбран' }}{{ count != null ? ' · Клипов: ' + count : '' }}</span>
                        </div>
                        <template v-if="target"
                            ><label for="project-search">Найти проект Poster</label><Input id="project-search" v-model="query" type="search" />
                            <label v-for="p in found" :key="p.id" class="pick-option"
                                ><input
                                    type="radio"
                                    v-model="form.project_id"
                                    :value="p.id"
                                    :disabled="!!binding && binding.project_id !== p.id"
                                /><span
                                    ><strong>{{ p.name }}</strong
                                    ><small>Мои проекты</small></span
                                ></label
                            ></template
                        >
                        <template v-else
                            ><label class="pick-option"
                                ><input
                                    type="radio"
                                    :checked="form.project_id !== null"
                                    :disabled="!projects.length"
                                    @change="selectMode('existing')"
                                /><span><strong>Использовать существующий проект</strong><small>Выберите проект своей студии</small></span></label
                            ><select v-if="form.project_id !== null" aria-label="Проект Poster" v-model="form.project_id" :disabled="!!binding">
                                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select></template
                        >
                        <label v-if="importProjects" class="pick-option"
                            ><input type="radio" :checked="form.project_id === null" :disabled="!!binding" @change="selectMode('new')" /><span
                                ><strong>Создать новый проект</strong><small>{{ source?.title || 'Название из источника' }}</small></span
                            ></label
                        >
                        <div v-if="!form.project_id" class="stage-message">
                            <strong>Новая связь</strong><small>Проект будет создан при подтверждении импорта.</small>
                        </div></template
                    >
                    <template v-else
                        ><h2>Сопоставление аккаунта</h2>
                        <template v-if="account"
                            ><label v-if="!target && accounts.length > 1" for="choose-account">Аккаунт провайдера</label
                            ><select v-if="!target && accounts.length > 1" id="choose-account" v-model="accountId">
                                <option v-for="a in accounts" :value="a.id" :key="a.id">{{ a.name || 'Аккаунт без названия' }}</option>
                            </select>
                            <div v-else class="pick-row">
                                <span>Аккаунт провайдера</span
                                ><span>{{ account.name || 'Аккаунт без названия' }} · {{ platforms[account.platform] || account.platform }}</span>
                            </div>
                            <label for="channel-target">Канал Poster</label
                            ><select id="channel-target" v-model="choices[account.id]" :disabled="!!mappings[account.id]">
                                <option :value="undefined">Пропустить</option>
                                <option v-for="c in eligible" :value="String(c.id)" :key="c.id">
                                    {{ c.name }} · {{ platforms[c.type] || c.type }}
                                </option>
                                <option v-if="platforms[account.platform]" value="new">Создать канал</option>
                            </select>
                            <label v-for="c in eligible" :key="c.id" class="pick-option"
                                ><input type="radio" :value="String(c.id)" v-model="choices[account.id]" :disabled="!!mappings[account.id]" /><span
                                    ><strong>{{ c.name }}</strong
                                    ><small>{{ platforms[c.type] || c.type }} · Подключён</small></span
                                ></label
                            >
                            <label v-if="platforms[account.platform]" class="pick-option"
                                ><input type="radio" value="new" v-model="choices[account.id]" :disabled="!!mappings[account.id]" /><span
                                    ><strong>Создать канал</strong><small>Канал без права отправки</small></span
                                ></label
                            >
                            <label class="pick-option"
                                ><input
                                    type="radio"
                                    :checked="!choices[account.id]"
                                    :disabled="!!mappings[account.id]"
                                    @change="delete choices[account.id]"
                                /><span><strong>Пропустить</strong><small>Оставить аккаунт без связи</small></span></label
                            ></template
                        >
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
                    <InputError v-for="(msg, field) in form.errors" :key="field" :message="msg" /><Button :disabled="form.processing || !canSubmit">{{
                        step === 'map-project' ? 'Применить выбор' : step === 'map-channel' ? 'Сохранить сопоставление' : 'Предпросмотр импорта'
                    }}</Button>
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
.pick-panel select {
    width: 100%;
    height: 46px;
    padding: 0 12px;
    background: var(--background);
    border: 1px solid var(--input);
    border-radius: 8px;
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
.pick-row select {
    height: auto;
    padding: 0;
    border: none;
    background: transparent;
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
