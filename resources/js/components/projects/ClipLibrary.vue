<script setup lang="ts">
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import { Link, router } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { platformNames } from '@/types/analytics';
import type { ProjectPageProps } from '@/types/projects';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
type Clips = NonNullable<ProjectPageProps['opusClips']>;
const props = defineProps<{
    clips: Clips;
    week: ProjectPageProps['clipWeek'];
    project: ProjectPageProps['project'];
    loading: boolean;
    error: string;
    updated: boolean;
    active: boolean;
}>();
const emit = defineEmits<{ page: [number]; refresh: []; sort: []; active: []; reset: []; select: [boolean] }>();
const route = useRoute();
const today = new Date().toISOString().slice(0, 10);
function shifted(date: string, days: number) {
    const d = new Date(date + 'T00:00:00Z');
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
}
function weekUrl(day: string) {
    return route('projects.show', { project: props.project.id, week: day });
}
const maxViews = computed(() => Math.max(1, ...(props.week?.clips.map((clip) => Math.abs(clip.views)) ?? [])));
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
        <div v-if="week" class="clip-detail">
            <section class="studio-panel grid gap-4">
                <h2>Тренды игры</h2>
                <label class="grid gap-2"
                    >Неделя<input
                        class="list-select"
                        type="date"
                        :value="week.from"
                        :max="today"
                        @change="
                            (event) => {
                                const el = event.target as HTMLInputElement;
                                if (el.value && el.validity.valid) router.visit(weekUrl(el.value));
                            }
                        "
                /></label>
                <p>
                    {{ new Date(week.from + 'T00:00:00Z').toLocaleDateString('ru') }} —
                    {{ new Date(week.to + 'T00:00:00Z').toLocaleDateString('ru') }}
                </p>
                <strong v-if="week.clips.length">Просмотры клипов за неделю</strong>
                <ul v-if="week.clips.length" class="grid gap-3">
                    <li v-for="(clip, index) in week.clips" :key="index">
                        <p class="flex justify-between">
                            <span>{{ clip.title }}</span><strong>{{ clip.views.toLocaleString('ru') }}</strong>
                        </p>
                        <div class="bg-muted mt-1 h-2 rounded">
                            <div class="bg-primary h-full rounded" :style="{ width: (Math.abs(clip.views) / maxViews) * 100 + '%' }" />
                        </div>
                    </li>
                </ul>
                <div v-else>
                    <strong>{{ week.observedOn ? 'Недостаточно наблюдений' : 'За неделю нет данных' }}</strong>
                    <p>
                        {{
                            week.observedOn
                                ? 'Нет сопоставимых замеров в начале и конце периода.'
                                : 'По клипам проекта нет наблюдений за выбранные даты.'
                        }}
                    </p>
                </div>
                <p v-if="week.observedOn" class="studio-row"><strong>Источник</strong>Наблюдения подключённых каналов · {{ week.observedOn }}</p>
                <nav class="flex flex-wrap gap-3" aria-label="Недели отчёта">
                    <Link :href="weekUrl(shifted(week.from, -7))">Предыдущая неделя</Link
                    ><Link v-if="shifted(week.from, 7) <= today" :href="weekUrl(shifted(week.from, 7))">Перейти к следующей неделе</Link>
                </nav>
                <Link :href="route('projects.show', project.id)">К библиотеке клипов</Link>
            </section>
            <aside class="studio-panel hidden min-[1100px]:block">
                <ProjectArtwork :name="project.name" :cover="project.cover" class="mb-3 aspect-[2.4] rounded-lg" /><strong>{{ project.name }}</strong>
                <p>Клиппинг</p>
            </aside>
        </div>
        <template v-else-if="!selected">
            <nav aria-label="Фильтры клипов" class="flex flex-wrap gap-2">
                <Button variant="secondary" :aria-pressed="!updated && !active" @click="emit('reset')">Все</Button>
                <Button variant="secondary" :aria-pressed="active" @click="emit('active')">Активные</Button>
                <Button variant="secondary" :aria-pressed="updated" @click="emit('sort')">По дате изменения</Button>
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
        <div v-else class="clip-detail clip-publications">
            <section class="studio-panel clip-sheet">
                <h2>{{ project.name }}</h2>
                <div class="clip-summary">
                    <ProjectArtwork :name="project.name" :cover="project.cover" class="h-14 w-20 rounded-lg" />
                    <strong>{{ [selected.title || 'Клип без названия', duration(selected.duration_ms)].filter(Boolean).join(' · ') }}</strong>
                </div>
                <p v-if="selected.score !== null" class="clip-score">
                    <span>Оценка источника</span><span>OpusClip · {{ selected.score }} / 100</span>
                </p>
                <div v-for="(post, index) in selected.publications" :key="index" class="clip-publication">
                    <a v-if="post.url" :href="post.url" class="text-primary font-bold" target="_blank" rel="noopener noreferrer"
                        >Открыть на {{ platformNames[post.platform.toLowerCase()] ?? post.platform }} ↗</a
                    >
                    <strong v-else>{{ platformNames[post.platform.toLowerCase()] ?? post.platform }}</strong>
                    <p v-if="publicationViews(post.platform, post.media_id) !== undefined">
                        {{ publicationViews(post.platform, post.media_id)?.toLocaleString('ru') }} просмотров
                    </p>
                    <p v-else-if="post.status">{{ postStatus[post.status.toLowerCase()] ?? 'Площадка уточняет статус' }}</p>
                </div>
                <p v-if="!selected.publications.length">Источник пока не вернул публикации.</p>
                <div v-if="selected.publications.length && !selected.observations.length" class="clip-empty">
                    <span aria-hidden="true">∅</span>
                    <strong>Пока нет наблюдений</strong>
                    <p>Сбор метрик по этому ролику ещё не получил ответ площадки.</p>
                </div>
            </section>
            <aside class="studio-panel clip-project hidden min-[1100px]:block">
                <ProjectArtwork :name="project.name" :cover="project.cover" class="rounded-lg" />
                <strong>{{ project.name }}</strong>
                <p>Клиппинг</p>
            </aside>
            <nav class="clip-links" aria-label="Переходы клипа">
                <a href="#clips">К библиотеке клипов</a>
                <a :href="selected.source_url" target="_blank" rel="noopener">Открыть источник</a>
                <details v-if="selected.observations.length || selected.publications.some((post) => post.publish_at)">
                    <summary>Наблюдения площадок</summary>
                    <p v-for="post in selected.publications.filter((post) => post.publish_at)" :key="post.url ?? post.platform">
                        {{ platformNames[post.platform.toLowerCase()] ?? post.platform }} · {{ new Date(post.publish_at!).toLocaleDateString('ru') }}
                    </p>
                    <div v-for="row in selected.observations" :key="row.platform + row.media_id">
                        <strong>{{ platformNames[row.platform.toLowerCase()] ?? row.platform }} · {{ row.date }}</strong>
                        <p v-for="(value, key) in row.metrics" :key="key" class="studio-row">
                            <span>{{ metricLabels[key] ?? 'Дополнительный показатель' }}</span>{{ value }}
                        </p>
                    </div>
                </details>
            </nav>
        </div>
    </section>
</template>
<style scoped>
.clip-library nav button {
    min-height: 40px;
    font-size: 14px;
}
.clip-library nav [aria-pressed='true'] {
    color: var(--primary);
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
    display: grid;
    align-items: start;
    gap: 16px;
    grid-template-columns: minmax(0, 2.3fr) minmax(0, 1fr);
}
.clip-publications {
    gap: 16px 18px;
    grid-template-columns: minmax(0, 2.22fr) minmax(0, 1fr);
}
.clip-sheet {
    padding: 16px 23px 28px;
}
.clip-sheet:has(.clip-empty) {
    padding-bottom: 39px;
}
.clip-sheet h2 {
    margin: 0 0 17px;
    font-size: 22px;
    line-height: 30px;
}
.clip-summary {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.clip-summary strong {
    margin-top: 4px;
}
.clip-summary > :first-child {
    flex: 0 0 84px;
    height: 56px;
}
.clip-score {
    display: grid;
    grid-template-columns: 39% 1fr;
    align-items: center;
    min-height: 52px;
    font-size: 15px;
    border-bottom: 1px solid var(--border);
}
.clip-score span:first-child,
.clip-publication p,
.clip-empty p,
.clip-project p {
    color: var(--muted-foreground);
}
.clip-publication {
    padding-top: 5px;
    min-height: 54px;
}
.clip-publication p {
    margin-top: 3px;
    font-size: 13px;
}
.clip-empty {
    margin-top: 7px;
}
.clip-empty > span {
    display: grid;
    place-items: center;
    width: 35px;
    height: 32px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--muted);
}
.clip-empty strong,
.clip-project strong {
    display: block;
    font-size: 20px;
    margin-block: 7px 4px;
}
.clip-empty p {
    font-size: 15px;
}
.clip-project {
    padding: 13px 13px 14px;
}
.clip-project > :first-child {
    height: 133px;
}
.clip-project p {
    font-size: 14px;
}
.clip-links {
    grid-column: 1;
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 8px 16px;
    font-size: 14px;
    color: var(--muted-foreground);
}
.clip-links a {
    text-decoration: underline;
}
.clip-links summary {
    cursor: pointer;
}
@media (max-width: 1099px) {
    .clip-library > nav + h2 {
        margin-bottom: 22px;
    }
    .clip-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .clip-detail {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 699px) {
    .clip-library > nav + h2 {
        margin-bottom: 24px;
    }
    .clip-sheet:has(.clip-empty) {
        padding-bottom: 32px;
    }
    .clip-sheet {
        padding-bottom: 25px;
        padding-inline: 15px;
    }
    .clip-grid {
        grid-template-columns: 1fr;
    }
}
</style>
