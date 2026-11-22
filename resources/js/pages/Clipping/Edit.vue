<script setup lang="ts">
import type { ProjectSummary } from '@/types/projects';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

type Connection = { id: number; name: string; driver: string };
type Scope = { import_projects: boolean; import_accounts: boolean; import_archives: boolean };
type Status = 'available' | 'denied' | 'unavailable' | 'unsupported' | 'unchecked';
type Access = Record<'projects' | 'clips' | 'accounts' | 'metrics' | 'publications', Status> & {
    account_count: number | null;
    titles: string[];
    sample: string | null;
    project_id: number | null;
};
type Tab = 'driver' | 'access' | 'scope';
const props = defineProps<{
    connections: Connection[];
    connection: Connection;
    projects: { id: number; name: string; clips_count: number; cover: ProjectSummary['cover'] }[];
    drivers: Record<string, string>;
    scope: Scope;
    supportsAccounts: boolean;
    supportsArchives: boolean;
    access?: Access;
    lastAccess: Access | null;
}>();
const route = useRoute();
const tab = ref<Tab>(props.access ? 'access' : 'driver');
const activeProjectId = ref<number | null>(props.access?.project_id ?? props.projects[0]?.id ?? null);
const project = computed(() => props.projects.find((item) => item.id === activeProjectId.value));
const driver = computed(() => props.drivers[props.connection.driver] ?? props.connection.driver);
const keys = useForm({ api_key: '' });
const scope = useForm({ ...props.scope, import_accounts: props.supportsAccounts && props.scope.import_accounts });
const checking = ref(false);
const keyOpen = ref(false);
const keySaved = ref(false);
const keyInput = ref<InstanceType<typeof Input> | null>(null);
const keyToggle = ref<HTMLButtonElement | null>(null);
function closeKey() {
    keys.reset();
    keys.clearErrors();
    keyOpen.value = false;
}
async function toggleKey() {
    if (keys.processing) return;
    keySaved.value = false;
    if (keyOpen.value) {
        closeKey();
        keyToggle.value?.focus();
        return;
    }
    keyOpen.value = true;
    await nextTick();
    keyInput.value?.focus();
}
watch(tab, () => {
    closeKey();
    keySaved.value = false;
});
const tabs: { id: Tab; name: string }[] = [
    { id: 'driver', name: 'Драйвер' },
    { id: 'access', name: 'Доступ' },
    { id: 'scope', name: 'Область импорта' },
];
const labels: Record<Status, string> = {
    available: 'Чтение доступно',
    denied: 'Доступ запрещён',
    unavailable: 'Не удалось проверить',
    unsupported: 'Драйвер не предоставляет эти данные',
    unchecked: 'Не проверено',
};
const evidence = computed(() => {
    const checked = props.access ?? props.lastAccess;
    return checked?.project_id === null || checked?.project_id === activeProjectId.value ? checked : null;
});
const accessRows = computed(() => {
    const status = evidence.value;
    const resourceRows =
        status?.projects === status?.clips
            ? [{ name: 'Проекты и клипы', status: status?.projects ?? 'unchecked' }]
            : [
                  { name: 'Проекты', status: status?.projects ?? 'unchecked' },
                  { name: 'Клипы', status: status?.clips ?? 'unchecked' },
              ];
    return [
        ...resourceRows,
        { name: 'Список аккаунтов', status: status?.accounts ?? 'unchecked' },
        { name: 'Статистика', status: status?.metrics ?? 'unsupported' },
    ];
});
const readable = computed(() =>
    (['projects', 'clips', 'accounts'] as const)
        .filter((resource) => evidence.value?.[resource] === 'available')
        .map((resource) => ({ projects: 'Проекты', clips: 'Клипы', accounts: 'Аккаунты' })[resource])
        .join(', '),
);
watch(
    () => props.connection.id,
    () => {
        activeProjectId.value = props.access?.project_id ?? props.projects[0]?.id ?? null;
        keyOpen.value = keySaved.value = false;
        keys.reset();
        Object.assign(scope, props.scope, { import_accounts: props.supportsAccounts && props.scope.import_accounts });
        tab.value = props.access ? 'access' : 'driver';
    },
);
function check() {
    checking.value = true;
    router.get(route('clipping.access', props.connection.id), activeProjectId.value === null ? {} : { project_id: activeProjectId.value }, {
        preserveState: true,
        onSuccess: () => {
            tab.value = 'access';
        },
        onFinish: () => {
            checking.value = false;
        },
    });
}
function updateKey() {
    keys.patch(route('clipping.key.update', props.connection.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeKey();
            keySaved.value = true;
            keyToggle.value?.focus();
        },
        onError: () => {
            keys.reset();
            keyInput.value?.focus();
        },
    });
}
function updateScope() {
    scope
        .transform((data) => ({ ...data, project_id: activeProjectId.value, import_archives: data.import_projects && data.import_archives }))
        .patch(route('clipping.scope.update', props.connection.id));
}
function selectConnection(event: Event) {
    router.visit(route('clipping.edit', (event.target as HTMLSelectElement).value));
}
</script>
<template>
    <AppLayout :breadcrumbs="[{ title: 'Источники', href: route('clipping.index') }]">
        <Head :title="tab === 'driver' ? 'Выбор провайдера' : 'Подключение провайдера'" />
        <div class="workspace-page provider" :class="{ 'provider-detail': tab !== 'driver' }">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    :title="tab === 'driver' ? 'Выбор провайдера' : 'Подключение провайдера'"
                    :description="tab === 'driver' ? [project?.name, 'Параметры импорта'].filter(Boolean).join(' / ') : undefined"
                />
                <Button v-if="tab === 'driver'" :disabled="checking" @click="check">Проверить доступ</Button>
            </header>
            <nav v-if="tab !== 'driver'" aria-label="Настройки подключения" class="flex flex-wrap gap-2">
                <button
                    v-for="item in tabs"
                    :key="item.id"
                    class="provider-tab"
                    :aria-current="tab === item.id ? 'page' : undefined"
                    @click="tab = item.id"
                >
                    {{ item.id === 'driver' ? 'Подключение' : item.name }}
                </button>
            </nav>
            <div :class="['provider-grid', { 'provider-main': tab === 'driver' }]">
                <nav v-if="tab === 'driver'" aria-label="Настройки подключения" class="studio-panel provider-nav">
                    <h2>В этом разделе</h2>
                    <button
                        v-for="item in tabs"
                        :key="item.id"
                        class="provider-tab"
                        :aria-current="tab === item.id ? 'page' : undefined"
                        @click="tab = item.id"
                    >
                        {{ item.name }}
                    </button>
                </nav>
                <section v-if="tab === 'driver'" class="studio-panel studio-form">
                    <h2>Параметры</h2>
                    <Label for="driver-label">Драйвер</Label><Input id="driver-label" readonly :model-value="driver" />
                    <Label for="connection">Подключение</Label
                    ><select id="connection" class="list-select w-full" :value="connection.id" @change="selectConnection">
                        <option v-for="item in connections" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                    <Label for="linked-project">Проект Poster</Label
                    ><select v-model="activeProjectId" id="linked-project" class="list-select w-full" :disabled="!projects.length">
                        <option v-if="!projects.length" :value="null">Нет связанных проектов</option>
                        <option v-for="item in projects" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                    <Label for="data-label">Допустимые данные</Label
                    ><Input id="data-label" readonly :model-value="evidence ? readable || 'Нет подтверждённого доступа' : 'Не проверено'" />
                </section>
                <section v-else-if="tab === 'access'" class="studio-panel permissions" aria-live="polite">
                    <h2>Права драйвера</h2>
                    <div class="studio-row">
                        <span>Драйвер</span><strong>{{ driver }}</strong>
                        <button
                            type="button"
                            class="key-toggle text-sm underline"
                            ref="keyToggle"
                            aria-controls="connection-key"
                            :aria-expanded="keyOpen"
                            :disabled="keys.processing"
                            @click="toggleKey"
                        >
                            Обновить ключ
                        </button>
                    </div>
                    <form v-if="keyOpen" id="connection-key" class="provider-key" novalidate @submit.prevent="updateKey">
                        <Label for="new-key">Новый ключ API</Label>
                        <Input
                            ref="keyInput"
                            id="new-key"
                            v-model="keys.api_key"
                            type="password"
                            autocomplete="new-password"
                            maxlength="4096"
                            required
                            :disabled="keys.processing"
                            :aria-invalid="!!keys.errors.api_key"
                            :aria-describedby="keys.errors.api_key ? 'key-error' : undefined"
                        />
                        <InputError id="key-error" role="alert" :message="keys.errors.api_key" />
                        <div class="flex flex-wrap gap-2">
                            <Button type="submit" :disabled="keys.processing">Сохранить ключ</Button>
                            <Button type="button" variant="outline" :disabled="keys.processing" @click="toggleKey">Отмена</Button>
                        </div>
                    </form>
                    <p v-else-if="keySaved" role="status" class="provider-result permission" data-status="available">
                        <span class="permission-icon" aria-hidden="true">✓</span><strong>Ключ обновлён</strong>
                    </p>
                    <div v-for="row in accessRows" :key="row.name" class="provider-result permission" :data-status="row.status">
                        <span class="permission-icon" aria-hidden="true">{{ row.status === 'available' ? '✓' : '!' }}</span>
                        <details v-if="row.name.startsWith('Проекты') && evidence?.projects === 'available'" class="checked-projects">
                            <summary>
                                <strong>{{ row.name }}</strong>
                            </summary>
                            <ul>
                                <li v-for="(name, index) in evidence.titles" :key="index">{{ name }}</li>
                            </ul>
                            <p v-if="evidence.sample">Проверка чтения клипов: {{ evidence.sample }}</p>
                            <p v-if="evidence.sample">Публикации: {{ labels[evidence.publications ?? 'unchecked'] }}</p>
                        </details>
                        <strong v-else>{{ row.name }}</strong><span>{{
                            row.name === 'Статистика' && row.status === 'unsupported' ? 'Этот источник не предоставляет метрики' : labels[row.status]
                        }}</span>
                    </div>
                    <Button :disabled="checking" @click="check">Проверить подключение</Button>
                </section>
                <form v-else class="studio-panel scope-panel space-y-4" @submit.prevent="updateScope">
                    <h2>Область импорта</h2>
                    <label class="provider-choice"
                        ><input v-model="scope.import_projects" type="checkbox" /><span
                            ><strong>Проекты и клипы</strong
                            ><small>{{ project ? 'Клипов: ' + project.clips_count + ' · ' + project.name : 'Нет связанного проекта' }}</small></span
                        ></label
                    >
                    <label class="provider-choice"
                        ><input v-model="scope.import_accounts" type="checkbox" :disabled="!supportsAccounts" /><span
                            ><strong>Связи с аккаунтами</strong
                            ><small>{{
                                evidence?.account_count != null
                                    ? 'Доступно аккаунтов: ' + evidence.account_count
                                    : labels[evidence?.accounts ?? (supportsAccounts ? 'unchecked' : 'unsupported')]
                            }}</small></span
                        ></label
                    >
                    <InputError :message="scope.errors.import_accounts" />
                    <label class="provider-choice"
                        ><input
                            :checked="scope.import_projects && scope.import_archives"
                            type="checkbox"
                            :disabled="!supportsArchives || !scope.import_projects"
                            @change="scope.import_archives = ($event.target as HTMLInputElement).checked"
                        /><span
                            ><strong>Архивные проекты</strong
                            ><small :title="!supportsArchives ? 'Драйвер не передаёт архивные проекты' : undefined">{{
                                scope.import_archives && supportsArchives && scope.import_projects ? 'Включить' : 'Пропустить'
                            }}</small></span
                        ></label
                    >
                    <InputError :message="scope.errors.import_archives" />
                    <div class="provider-result"><strong>Предпросмотр</strong><span>Изменения сохранятся после подтверждения импорта.</span></div>
                    <Button :disabled="scope.processing">Посмотреть выбранное</Button>
                </form>
                <aside v-if="tab !== 'driver'" class="studio-panel space-y-2">
                    <ProjectArtwork v-if="project" :name="project.name" :cover="project.cover" class="aspect-[2.88] rounded-lg" />
                    <h2>{{ project?.name ?? connection.name }}</h2>
                    <small>Подключение</small>
                    <p>{{ driver }} · {{ connection.name }}</p>
                    <template v-if="project"
                        ><small>Клиппинг</small>
                        <p>Клипов: {{ project.clips_count }}</p></template
                    >
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
<style scoped>
.provider-key {
    display: grid;
    gap: 10px;
    border-block-end: 1px solid var(--border);
    padding: 12px 0 18px;
}
.provider-key input {
    height: 44px;
}
.provider-key button {
    min-height: 44px;
}

.provider-grid {
    display: grid;
    grid-template-columns: minmax(0, 2.36fr) minmax(0, 1fr);
    align-items: start;
    gap: 20px;
}
.provider-main {
    grid-template-columns: minmax(210px, 0.325fr) minmax(0, 1fr);
    gap: 16px;
}
.provider-tab {
    padding: 10px 14px;
    border-radius: 8px;
    background: var(--muted);
    text-align: left;
}
.provider-tab[aria-current='page'] {
    background: color-mix(in srgb, var(--primary) 14%, var(--card));
    color: var(--primary);
}
.provider-nav {
    display: grid;
    gap: 6px;
}
.provider-nav .provider-tab:not([aria-current]) {
    background: transparent;
}
.provider-result {
    padding: 10px 14px;
    border-inline-start: 3px solid var(--border);
    border-radius: 5px;
    background: var(--muted);
}
.provider-result span,
.provider-result small,
.provider-choice small {
    display: block;
    color: var(--muted-foreground);
}
.provider-choice {
    display: flex;
    align-items: start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}
.provider-choice input {
    margin-top: 5px;
    accent-color: var(--primary);
}
.provider-main .studio-form {
    max-width: none;
}
@media (max-width: 1099px) {
    .provider header {
        flex-direction: column;
    }
    .provider-grid {
        grid-template-columns: 1fr;
    }
    .provider-main {
        gap: 22px;
    }
    .provider-nav {
        display: flex;
        flex-wrap: wrap;
        padding: 0;
        gap: 8px;
        background: transparent;
        border: none;
    }
    .provider-nav h2,
    .provider-grid aside {
        display: none;
    }
    .provider-nav .provider-tab:not([aria-current]) {
        background: var(--muted);
    }
}
.provider-tab {
    font-size: 14px;
    line-height: 20px;
}
.provider-detail {
    padding-top: 16px;
    gap: 20px;
}
.provider-main .studio-form {
    min-height: 468px;
    display: block;
}
.provider-main .studio-form :is(input, select) {
    height: 48px;
    font-size: 16px;
    background-color: var(--background);
}
.provider-main .studio-form label {
    display: block;
    font-weight: 400;
    color: var(--muted-foreground);
    margin: 12px 0 6px;
    line-height: 20px;
}
.provider-main .studio-form h2 {
    margin-bottom: 24px;
}
.provider-detail .studio-panel {
    min-width: 0;
}
.provider header button,
.permissions > button:first-of-type,
.scope-panel > button {
    min-height: 44px;
    font-weight: 700;
}
.permissions .studio-row {
    justify-content: start;
    display: grid;
    grid-template-columns: 36% 1fr;
    border-top: 0;
    border-bottom: 1px solid var(--border);
    padding-block: 10px 16px;
    color: var(--muted-foreground);
    font-size: 14px;
}
.permissions .studio-row strong {
    font-weight: 400;
    color: var(--foreground);
}
.permission {
    position: relative;
    margin: 8px 0;
    padding: 9px 14px 9px 42px;
    min-height: 69px;
}
.permission[data-status='available'] {
    border-color: var(--primary);
}
.permission[data-status='available'] strong,
.permission[data-status='available'] .permission-icon {
    color: var(--primary);
}
.permission[data-status='unsupported'],
.permission[data-status='denied'] {
    border-color: #e8aa80;
}
.permission[data-status='unsupported'] strong,
.permission[data-status='denied'] strong,
.permission[data-status='unsupported'] .permission-icon,
.permission[data-status='denied'] .permission-icon {
    color: #e8aa80;
}
.permission-icon {
    position: absolute;
    left: 10px;
    top: 13px;
    display: grid !important;
    place-items: center;
    width: 20px;
    height: 20px;
    border: 1px solid currentColor;
    border-radius: 50%;
    font-size: 13px;
}
.permission > span:last-child,
.provider-projects {
    font-size: 14px;
}
.provider-projects {
    margin-top: 10px;
}
.provider-projects summary {
    cursor: pointer;
}
.provider-detail aside {
    padding: 16px;
}
@media (min-width: 1100px) {
    .provider-main {
        grid-template-columns: 276px minmax(0, 852px);
    }
    .provider-nav {
        min-height: 240px;
    }
}
@media (min-width: 1920px) {
    .provider-grid {
        max-width: 1236px;
        grid-template-columns: minmax(0, 860px) minmax(0, 354px);
    }
    .provider-main {
        max-width: 1112px;
        grid-template-columns: 276px minmax(0, 820px);
    }
}
@media (max-width: 767px) {
    .provider-detail {
        padding-top: 15px;
        gap: 19px;
    }
    .provider-tab {
        font-size: 13px;
        padding: 10px 11px;
    }
    .provider-main {
        gap: 20px;
    }
    .provider-main .studio-form {
        min-height: 468px;
    }
    .permissions .studio-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}
.checked-projects summary {
    cursor: pointer;
    list-style: none;
}
.checked-projects summary:hover {
    text-decoration: underline;
}
.scope-panel .provider-choice {
    padding-block: 7px;
    margin: 0;
    min-height: 62px;
}
.scope-panel .provider-choice small {
    font-size: 14px;
    line-height: 20px;
}
.scope-panel .provider-result {
    margin-top: 8px;
}
@media (min-width: 1100px) {
    .provider:not(.provider-detail) {
        padding-top: 23px;
    }
}
@media (min-width: 768px) and (max-width: 1099px) {
    .provider-main {
        gap: 24px;
    }
    .provider-main .studio-form {
        min-height: 467px;
    }
}
@media (max-width: 767px) {
    .provider-main {
        gap: 17px;
    }
    .provider-detail {
        padding-top: 14px;
    }
    .scope-panel .provider-choice {
        padding-block: 7px;
    }
}
.permissions {
    min-height: 431px;
}
.permissions .studio-row {
    position: relative;
}
.key-toggle {
    position: absolute;
    right: 0;
    top: 10px;
    color: var(--muted-foreground);
}
.provider-detail .scope-panel .provider-result span {
    font-size: 14px;
}
@media (max-width: 767px) {
    .permissions {
        min-height: 443px;
    }
    .scope-panel {
        min-height: 414px;
    }
    .permissions .studio-row {
        padding-bottom: 14px;
    }
}
@media (min-width: 768px) and (max-width: 1919px) {
    .provider-detail .provider-grid {
        padding-left: 1px;
    }
}
@media (min-width: 1100px) {
    .provider-detail aside {
        min-height: 288px;
    }
    .provider-detail aside > div:first-child {
        height: 106px;
    }
}
@media (min-width: 1920px) {
    .provider-detail .provider-grid {
        gap: 22px;
        grid-template-columns: minmax(0, 859px) minmax(0, 354px);
    }
}
.scope-panel {
    min-height: 415px;
}
@media (max-width: 767px) {
    .scope-panel {
        min-height: 414px;
    }
    .scope-panel .provider-choice {
        padding-block: 6px;
    }
}
@media (min-width: 1100px) and (max-width: 1919px) {
    .provider-detail .provider-grid {
        grid-template-columns: minmax(0, 2.355fr) minmax(0, 1fr);
    }
}
</style>
