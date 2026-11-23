<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ImportProjectCard from '@/components/ImportProjectCard.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import type { ProjectSummary } from '@/types/projects';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';
const props = defineProps<{
    connection: { id: number; name: string };
    preview: {
        id: number;
        external_id: string | null;
        source_name: string | null;
        project_id: number | null;
        accounts: { id: string; name: string; channel_id: number | null }[] | null;
        unmapped_accounts: { id: string; name: string; platform: string }[] | null;
        imported_at: string | null;
    };
    project: Pick<ProjectSummary, 'id' | 'name' | 'cover'> | null;
    projectName: string | null;
    accountNames: Record<string, string>;
    existingClipIds: string[];
    clips: {
        id: string;
        title: string | null;
        durationMs: number | null;
        publications: { platform: string; status: string | null; url: string | null }[];
    }[];
}>();
const route = useRoute(),
    form = useForm({}),
    page = usePage<SharedData>();
const empty = computed(() => props.preview.external_id !== null && !props.clips.length);
const confirmed = computed(() => props.preview.imported_at !== null);
const title = computed(() => (confirmed.value ? 'Предпросмотр импорта' : empty.value ? 'Выбор источника' : 'Сопоставление импорта'));
const name = computed(() => props.projectName || props.preview.source_name || 'Проект без названия');
const existing = computed(() => new Set(props.existingClipIds));
function change(step: string, account?: string) {
    return route('clipping.sources', { connection: props.connection.id, preview: props.preview.id, step, ...(account ? { account } : {}) });
}
function confirm() {
    form.post(route('clipping.import', [props.connection.id, props.preview.id]));
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
            { title: connection.name, href: route('clipping.edit', connection.id) },
            confirmed
                ? { title: 'История импорта', href: route('clipping.history') }
                : { title: 'Выбор источника', href: route('clipping.sources', connection.id) },
            { title: title, href: route('clipping.preview.show', [connection.id, preview.id]) },
        ]"
    >
        <Head :title="title" />
        <div class="workspace-page import-review" :class="{ compact: confirmed || empty }">
            <header>
                <Heading :title="title" :description="confirmed ? 'Результат' : empty ? 'Предпросмотр' : name + ' / Предпросмотр'" /><Button
                    v-if="!confirmed && !empty"
                    :disabled="form.processing"
                    @click="confirm"
                    >Подтвердить импорт</Button
                >
            </header>
            <p
                v-for="error in [page.props.errors.project_id, page.props.errors.accounts].filter(Boolean)"
                :key="error"
                class="text-destructive"
                role="alert"
            >
                {{ error }}
            </p>
            <div v-if="confirmed || empty" class="review-columns">
                <section class="studio-panel state-panel">
                    <template v-if="confirmed"
                        ><h2>Результат</h2>
                        <div role="status" class="state-notice">
                            <strong>Импорт уже подтверждён</strong>
                            <p>{{ new Date(preview.imported_at!).toLocaleString('ru-RU') }}. Те же клипы повторно не добавятся.</p>
                        </div>
                        <div class="result-row">
                            <span>Проект Poster</span><strong>{{ name }}</strong>
                        </div>
                        <div class="result-row">
                            <span>Результат</span
                            ><span>Клипов: {{ clips.length }} · Сопоставлений аккаунтов: {{ preview.accounts?.length ?? 0 }}</span>
                        </div>
                        <Button disabled>Подтвердить импорт</Button
                        ><Link v-if="project" :href="route('projects.show', project.id)" class="project-link">Открыть клипы проекта ↗</Link
                        ><small>{{ name }}</small></template
                    >
                    <template v-else
                        ><h2>Предпросмотр</h2>
                        <span class="secondary">Проект провайдера</span>
                        <div class="source-name">{{ preview.source_name || 'Проект без названия' }}</div>
                        <span class="empty-symbol" aria-hidden="true">○</span><strong>В источнике нет клипов</strong>
                        <p>Подтвердите привязку. Новые клипы появятся после обновления.</p>
                        <Button variant="outline" as-child><Link :href="change('source')">Выбрать другой источник</Link></Button
                        ><Button :disabled="form.processing" @click="confirm">Подтвердить импорт</Button></template
                    >
                </section>
                <ImportProjectCard :name="name" :cover="project?.cover" />
            </div>
            <section v-else class="mappings">
                <h2>Источник → Poster</h2>
                <Link v-if="preview.external_id" class="map-row" :href="change('map-project')"
                    ><small>Источник</small><strong>{{ preview.source_name || 'Проект без названия' }}</strong
                    ><i aria-hidden="true">→</i><small class="target-label">В Poster</small><span>{{ projectName || 'Новый проект' }}</span></Link
                >
                <Link class="map-row" v-for="a in preview.accounts" :key="a.id" :href="change('map-channel', a.id)"
                    ><small>Источник</small><strong>{{ a.name || 'Аккаунт без названия' }}</strong
                    ><i aria-hidden="true">→</i><small class="target-label">В Poster</small
                    ><span>{{ accountNames[a.id] || 'Создать канал без права отправки' }}</span></Link
                >
                <Link v-for="account in preview.unmapped_accounts" :key="account.id" class="map-row" :href="change('map-channel', account.id)"
                    ><small>Источник</small><strong>{{ account.name || 'Аккаунт без названия' }}</strong
                    ><i aria-hidden="true">→</i><small class="target-label">В Poster</small><span class="unmapped">Выберите канал</span></Link
                >
                <details v-for="clip in clips" :key="clip.id">
                    <summary class="map-row">
                        <small>Источник</small><strong>{{ clip.title || 'Клип без названия' }}</strong
                        ><i aria-hidden="true">→</i><small class="target-label">В Poster</small
                        ><span>{{ existing.has(clip.id) ? 'Обновить клип' : 'Новый клип' }}</span>
                    </summary>
                    <div class="studio-panel">
                        <p v-if="clip.durationMs != null">Длительность: {{ (clip.durationMs / 1000).toLocaleString('ru') }} с</p>
                        <p v-for="(pub, i) in clip.publications" :key="i">{{ pub.platform }} · {{ pub.status || 'Статус неизвестен' }}</p>
                        <p v-if="!clip.publications.length">Публикаций нет</p>
                    </div>
                </details>
            </section>
        </div></AppLayout
    >
</template>
<style scoped>
.import-review header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 1rem;
}
.import-review header [data-slot='button'] {
    min-height: 44px;
    font-weight: 700;
}
.import-review h2 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 12px;
}
.map-row {
    display: grid;
    grid-template-columns: minmax(0, 0.86fr) 64px minmax(0, 1fr);
    align-items: center;
    min-height: 68px;
    padding: 12px 16px;
    gap: 16px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin: 10px 0;
    cursor: pointer;
    overflow-wrap: anywhere;
}
.map-row small {
    display: none;
}
.map-row:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
.map-row .unmapped {
    color: var(--warning);
}
.map-row i {
    color: var(--muted-foreground);
    font-style: normal;
}
.map-row:hover {
    border-color: var(--primary);
}
.map-row span,
.secondary,
.state-panel small,
.state-panel p {
    color: var(--muted-foreground);
}
.compact {
    padding-top: 14px;
}
.review-columns {
    display: grid;
    grid-template-columns: minmax(0, 2.23fr) minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}
.state-panel {
    display: grid;
    justify-items: start;
    gap: 10px;
}
.state-panel h2 {
    margin: 0;
}
.empty-symbol {
    font-size: 20px;
    color: var(--muted-foreground);
}
.state-notice {
    border-left: 3px solid var(--primary);
    background: var(--muted);
    width: 100%;
    padding: 12px;
    border-radius: 6px;
}
.result-row {
    display: grid;
    grid-template-columns: 36% minmax(0, 1fr);
    gap: 12px;
    border-bottom: 1px solid var(--border);
    width: 100%;
    padding: 10px 0;
}
.result-row span:first-child {
    color: var(--muted-foreground);
}
.source-name {
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 12px;
    width: 100%;
    background: var(--background);
}
.project-link {
    color: var(--ring);
    font-weight: 700;
}
.state-panel [data-slot='button'] {
    min-height: 44px;
    font-weight: 700;
}
@media (max-width: 1099px) {
    .review-columns {
        grid-template-columns: 1fr;
    }
    .import-review header {
        display: grid;
        justify-items: start;
    }
}
@media (max-width: 767px) {
    .map-row {
        grid-template-columns: 1fr;
        align-content: start;
        min-height: 138px;
        gap: 4px;
    }
    .map-row small {
        display: block;
        color: var(--muted-foreground);
        font-size: 12px;
        line-height: 18px;
    }
    .map-row .target-label {
        margin-top: 10px;
    }
    .map-row i {
        display: none;
    }
    .result-row {
        grid-template-columns: 1fr;
    }
}
</style>
