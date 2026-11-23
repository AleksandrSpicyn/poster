<script setup lang="ts">
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import { Button } from '@/components/ui/button';
import { platformNames } from '@/types/analytics';
import type { ProjectPageProps } from '@/types/projects';
import { ChartColumn, Clock, Film, Globe } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
type Clips = NonNullable<ProjectPageProps['opusClips']>;
const props = defineProps<{
    clips: Clips;
    project: ProjectPageProps['project'];
    loading: boolean;
    error: string;
    active: boolean;
}>();
const emit = defineEmits<{ page: [number]; refresh: []; active: []; reset: []; select: [boolean] }>();
const selectedId = ref<number | null>(null);
const selected = computed(() => props.clips.data.find((c) => c.id === selectedId.value));
watch(selected, (clip) => emit('select', !!clip));
function readHash() {
    const value = location.hash.match(/^#clip-(\d+)$/);
    selectedId.value = value ? Number(value[1]) : null;
}
onMounted(() => {
    readHash();
    window.addEventListener('hashchange', readHash);
});
onUnmounted(() => window.removeEventListener('hashchange', readHash));
function duration(ms: number | null) {
    if (ms === null) return null;
    const s = Math.round(ms / 1000);
    return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
}
function publicationViews(platform: string, mediaId: string | null) {
    return selected.value?.observations.find((item) => item.platform.toLowerCase() === platform.toLowerCase() && item.media_id === mediaId)?.metrics
        .views;
}
const postStatus: Record<string, string> = {
    published: 'Публикация принята',
    scheduled: 'Запланировано',
    pending: 'Ожидает публикации',
    processing: 'Обрабатывается',
    failed: 'Ошибка публикации',
    draft: 'Черновик',
    cancelled: 'Отменено',
};
const metricLabels: Record<string, string> = {
    views: 'Просмотры',
    estimatedMinutesWatched: 'Время просмотра, мин',
    engagedViews: 'Заинтересованные просмотры',
    averageViewDuration: 'Среднее время просмотра, с',
    averageViewPercentage: 'Средний процент просмотра',
    shares: 'Поделились',
    reach: 'Охват',
    saved: 'Сохранения',
    likes: 'Отметки «Нравится»',
    comments: 'Комментарии',
};
</script>
<template>
    <section class="clip-library" aria-label="Клипы проекта" :aria-busy="loading">
        <div v-if="error" role="alert" class="mb-4">
            <p>{{ error }}</p>
            <Button variant="outline" @click="emit('refresh')">Повторить</Button>
        </div>
        <template v-if="!selected">
            <nav aria-label="Фильтры клипов" class="flex flex-wrap gap-2">
                <Button variant="secondary" :aria-pressed="!active" @click="emit('reset')">Все</Button>
                <Button variant="secondary" :aria-pressed="active" @click="emit('active')">Активные</Button>
            </nav>
            <h2>Клипы проекта</h2>
            <ul class="clip-grid">
                <li v-for="clip in clips.data" :key="clip.id">
                    <a :href="'#clip-' + clip.id" :aria-label="'Открыть клип ' + (clip.title || 'без названия')" class="clip-card">
                        <ProjectArtwork :name="project.name" :cover="project.cover" class="clip-cover" />
                        <strong>{{ clip.title || 'Клип без названия' }}</strong>
                        <span>{{ [duration(clip.duration_ms), clip.source].filter(Boolean).join(' · ') }}</span>
                    </a>
                </li>
            </ul>
            <p v-if="!clips.data.length">Клипы появятся после импорта.</p>
            <footer>
                <p>Показано {{ clips.meta.to ?? 0 }} из {{ clips.meta.total }}</p>
                <div v-if="clips.meta.last_page > 1" class="flex gap-3">
                    <Button variant="outline" :disabled="loading || clips.meta.current_page === 1" @click="emit('page', clips.meta.current_page - 1)"
                        >Назад</Button
                    >
                    <Button
                        variant="outline"
                        :disabled="loading || clips.meta.current_page === clips.meta.last_page"
                        @click="emit('page', clips.meta.current_page + 1)"
                        >Далее</Button
                    >
                </div>
            </footer>
        </template>
        <article v-else class="clip-detail">
            <header class="clip-intro">
                <h1>{{ selected.title || 'Клип без названия' }}</h1>
                <p class="clip-meta">
                    <span><Film aria-hidden="true" />Импортированный клип</span
                    ><span v-if="selected.duration_ms !== null"><Clock aria-hidden="true" />{{ duration(selected.duration_ms) }}</span>
                </p>
            </header>
            <div class="clip-columns">
                <section>
                    <h2>Публикации</h2>
                    <div class="clip-posts clip-panel">
                        <p v-if="!selected.publications.length" class="text-muted-foreground py-5">Источник пока не вернул публикации.</p>
                        <div v-for="(post, index) in selected.publications" :key="index" class="clip-post">
                            <span class="tile-icon" aria-hidden="true"
                                ><img
                                    v-if="platformNames[post.platform.toLowerCase()]"
                                    :src="'/icons/' + post.platform.toLowerCase() + '.svg'"
                                    alt="" /><Globe v-else
                            /></span>
                            <div>
                                <h3>{{ platformNames[post.platform.toLowerCase()] ?? post.platform }}</h3>
                                <p class="clip-status">{{ postStatus[post.status?.toLowerCase() ?? ''] ?? 'Площадка уточняет статус' }}</p>
                                <small v-if="post.publish_at">{{ new Date(post.publish_at).toLocaleDateString('ru') }}</small>
                                <small v-if="publicationViews(post.platform, post.media_id) != null"
                                    >{{ publicationViews(post.platform, post.media_id)?.toLocaleString('ru') }} просмотров</small
                                >
                            </div>
                            <a
                                v-if="post.url"
                                class="clip-action"
                                :href="post.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                :aria-label="'Открыть публикацию · ' + (platformNames[post.platform.toLowerCase()] ?? post.platform)"
                                >Открыть ↗</a
                            >
                        </div>
                    </div>
                </section>
                <section>
                    <h2>Источник</h2>
                    <div class="clip-provider clip-panel">
                        <h3>OpusClip</h3>
                        <template v-if="selected.score !== null"
                            ><p>Оценка источника</p>
                            <div class="clip-meter" aria-hidden="true">
                                <i :style="{ width: Math.min(100, Math.max(0, selected.score)) + '%' }" />
                            </div>
                            <strong>{{ selected.score }} / 100</strong></template
                        >
                        <a class="clip-action" :href="selected.source_url" target="_blank" rel="noopener noreferrer">Открыть источник ↗</a>
                    </div>
                </section>
            </div>
            <section class="clip-observations">
                <h2>Наблюдения площадок</h2>
                <div v-if="!selected.observations.length" class="clip-empty clip-panel">
                    <span class="tile-icon" aria-hidden="true"><ChartColumn /></span>
                    <div>
                        <strong>Пока нет наблюдений</strong>
                        <p>Метрики появятся после первого ответа площадки.</p>
                    </div>
                </div>
                <div v-else class="clip-panel px-5">
                    <section v-for="row in selected.observations" :key="row.platform + row.media_id" class="py-4">
                        <h3>{{ platformNames[row.platform.toLowerCase()] ?? row.platform }} · {{ row.date }}</h3>
                        <dl>
                            <div v-for="(value, key) in row.metrics" :key="key" class="studio-row">
                                <dt>{{ metricLabels[key] ?? 'Дополнительный показатель' }}</dt>
                                <dd>{{ value.toLocaleString('ru') }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </section>
        </article>
    </section>
</template>
<style scoped>
.clip-library nav button {
    min-height: 40px;
    font-size: 14px;
}
.clip-library nav [aria-pressed='true'] {
    color: var(--ring);
    background: color-mix(in srgb, var(--primary) 12%, var(--card));
}
.clip-library h2 {
    font-size: 20px;
    font-weight: 700;
    margin-block: 12px;
}
.clip-library > nav + h2 {
    margin-block: 16px 20px;
}
.clip-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.clip-card {
    display: block;
    overflow: hidden;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
}
.clip-cover {
    width: calc(100% + 2px);
    margin-inline: -1px;
    aspect-ratio: 1.85;
    max-height: 246px;
}
.clip-card strong,
.clip-card span {
    display: block;
    margin-inline: 14px;
}
.clip-card strong {
    font-size: 18px;
    margin-top: 12px;
}
.clip-card span {
    font-size: 14px;
    color: var(--muted-foreground);
    margin: 4px 14px 24px;
}
.clip-library footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 16px;
    color: var(--muted-foreground);
    font-size: 13px;
}

.clip-detail {
    line-height: 1.45;
}
.clip-intro {
    padding-bottom: 32px;
}
.clip-intro h1 {
    max-width: 36ch;
    font-size: 40px;
    line-height: 1.4;
    letter-spacing: -0.035em;
    font-weight: 700;
    overflow-wrap: anywhere;
}
.clip-meta {
    display: flex;
    gap: 16px;
    align-items: center;
    margin-top: 12px;
    color: var(--muted-foreground);
    font-size: 14px;
}
.clip-meta span {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.clip-meta svg {
    width: 19px;
    height: 19px;
}
.clip-meta span + span {
    border-left: 1px solid var(--border);
    padding-left: 16px;
    font-family: var(--font-mono);
}
.clip-columns {
    display: grid;
    grid-template-columns: minmax(0, 1.58fr) minmax(0, 1fr);
    gap: 24px;
}
.clip-columns > section {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.clip-detail h2 {
    font-size: 20px;
    line-height: 28px;
    margin: 0 0 12px;
}
.clip-detail h3 {
    font-size: 18px;
    line-height: 1.4;
    font-weight: 700;
}
.clip-panel {
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 12px;
}
.clip-columns .clip-panel {
    flex: 1;
}
.clip-posts {
    display: grid;
    padding-inline: 20px;
}
.clip-post {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-block: 18px;
    min-width: 0;
}
.clip-post + .clip-post {
    border-top: 1px solid var(--border);
}
.tile-icon {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex: none;
    background: var(--muted);
    border-radius: 10px;
}
.tile-icon :is(svg, img) {
    width: 23px;
    height: 23px;
}
.clip-post small,
.clip-status,
.clip-provider p,
.clip-empty p {
    display: block;
    color: var(--muted-foreground);
    font-size: 14px;
    margin-top: 4px;
}
.clip-status::before {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    margin-right: 7px;
    border-radius: 50%;
    background: var(--muted-foreground);
}
.clip-action {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    color: var(--ring);
    font-size: 14px;
    font-weight: 700;
}
.clip-action:hover {
    text-decoration: underline;
}
.clip-post .clip-action {
    margin-left: auto;
}
.clip-provider {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}
.clip-provider p {
    margin-top: 10px;
}
.clip-meter {
    width: 100%;
    height: 8px;
    background: var(--muted);
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    margin: 8px 0;
}
.clip-meter i {
    display: block;
    height: 100%;
    background: var(--primary);
}
.clip-provider strong {
    font-size: 20px;
    font-variant-numeric: tabular-nums;
}
.clip-provider .clip-action {
    margin-top: auto;
}
.clip-observations {
    margin-top: 28px;
}
.clip-empty {
    display: flex;
    gap: 14px;
    align-items: center;
    padding: 20px;
}
@media (max-width: 1099px) {
    .clip-library > nav + h2 {
        margin-bottom: 22px;
    }
    .clip-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .clip-columns {
        gap: 16px;
        grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr);
    }
    .clip-post {
        gap: 9px;
        flex-wrap: wrap;
    }
}
@media (max-width: 699px) {
    .clip-library > nav + h2 {
        margin-bottom: 24px;
    }
    .clip-grid,
    .clip-columns {
        grid-template-columns: 1fr;
    }
    .clip-columns {
        gap: 20px;
    }
    .clip-intro {
        padding-bottom: 24px;
    }
    .clip-intro h1 {
        font-size: 30px;
    }
    .clip-posts {
        padding-inline: 14px;
    }
    .clip-post {
        display: grid;
        grid-template-columns: 40px minmax(0, 1fr) auto;
        gap: 10px;
    }
    .tile-icon {
        width: 38px;
        height: 38px;
    }
    .clip-post h3 {
        font-size: 16px;
    }
    .clip-action {
        font-size: 13px;
    }
    .clip-status {
        font-size: 12px;
    }
    .clip-provider,
    .clip-empty {
        padding: 16px;
    }
    .clip-observations {
        margin-top: 20px;
    }
}
</style>
