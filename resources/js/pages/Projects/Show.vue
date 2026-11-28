<script setup lang="ts">
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import ClipLibrary from '@/components/projects/ClipLibrary.vue';
import ChannelIcon from '@/components/ChannelIcon.vue';
import EmptyState from '@/components/EmptyState.vue';
import VideoPreview from '@/components/VideoPreview.vue';
import Heading from '@/components/Heading.vue';
import ProjectNotice from '@/components/projects/ProjectNotice.vue';
import ProductionPanel from '@/components/ProductionPanel.vue';
import ProjectArchive from '@/components/projects/ProjectArchive.vue';
import SeriesWorkspace from '@/components/SeriesWorkspace.vue';
import { Button } from '@/components/ui/button';
import DeleteProjectDialog from '@/components/projects/DeleteProjectDialog.vue';
import { DropdownMenu, DropdownMenuTrigger, DropdownMenuContent, DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatProjectDate } from '@/lib/formatProjectDate';
import type { BreadcrumbItem } from '@/types';
import type { ProjectPageProps } from '@/types/projects';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Ellipsis } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useRoute } from '@/composables/useRoute';

const route = useRoute();
const page = usePage<ProjectPageProps>();
const { filters, loading, error, applyFilters, changePage, refresh } = useListFilters(
    'projects.show',
    () => ({
        sort: page.props.filters.sort,
        active: Number(!!page.props.filters.active),
        perPage: (page.props.opusClips ?? page.props.prompts).meta.per_page,
        page: (page.props.opusClips ?? page.props.prompts).meta.current_page,
    }),
    () => ({ project: page.props.project.id }),
);
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Проекты', href: route('projects.index') },
    { title: page.props.project.name, href: route('projects.show', page.props.project.id) },
    ...(viewingClip.value ? [{ title: 'Библиотека клипов', href: '#clips' }] : []),
    ...(managing.value ? [{ title: 'Управление проектом', href: page.url }] : []),
]);
const viewingClip = ref(false);

const managing = computed(() => new URL(page.url, page.props.ziggy.url).searchParams.get('section') === 'management');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="page.props.project.name" />
        <div
            class="workspace-page"
            :class="{
                'clip-project-page': !!page.props.opusClips && !viewingClip,
                'selected-clip': viewingClip,
                'short-project': !page.props.opusClips && !page.props.series,
            }"
        >
            <ProjectNotice />
            <div
                v-if="!viewingClip"
                class="project-header flex flex-col items-start justify-between"
                :class="page.props.series ? 'gap-4 sm:flex-row' : 'gap-3 min-[1100px]:flex-row'"
            >
                <div class="w-full min-w-0 flex-1 space-y-3 [&_h1]:break-words">
                    <div class="project-title-row flex items-start gap-2">
                        <Heading
                            :title="
                                managing
                                    ? 'Управление проектом'
                                    : page.props.opusClips
                                      ? 'Библиотека клипов'
                                      : page.props.series
                                        ? page.props.project.name
                                        : 'Проект коротких роликов'
                            "
                            :description="
                                viewingClip
                                    ? page.props.project.name
                                    : page.props.project.name + (page.props.opusClips ? ' / Источники и клипы' : ' / Производство')
                            "
                        />
                        <DropdownMenu v-if="!page.props.series && !page.props.opusClips">
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="icon" aria-label="Меню проекта"><Ellipsis aria-hidden="true" /></Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem as-child>
                                    <Link :href="route('prompts', { project: page.props.project.id })">Вся история</Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem as-child
                                    ><Link :href="route('projects.show', { project: page.props.project.id, section: 'management' })"
                                        >Управление проектом</Link
                                    ></DropdownMenuItem
                                >
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
                <div v-if="page.props.opusClips && !viewingClip && !managing" class="flex flex-wrap gap-2">
                    <Button as-child variant="outline" class="h-11"
                        ><Link :href="route('projects.show', { project: page.props.project.id, section: 'management' })"
                            >Открыть настройки</Link
                        ></Button
                    >
                    <Button as-child class="h-11 shrink-0"><Link :href="route('clipping.index')">Импортировать</Link></Button>
                </div>
                <div v-if="!page.props.opusClips" id="project-heading-actions" class="flex flex-wrap gap-2">
                    <template v-if="page.props.series">
                        <Button as-child variant="outline"
                            ><Link
                                :href="route('series.show', { project: page.props.project.id, series: page.props.series.id, section: 'production' })"
                                >Производство</Link
                            ></Button
                        >
                        <Button as-child variant="outline"
                            ><Link
                                :href="route('series.show', { project: page.props.project.id, series: page.props.series.id, section: 'materials' })"
                                >Материалы</Link
                            ></Button
                        >
                    </template>
                    <Button as-child variant="default" class="h-11 shrink-0"
                        ><Link :href="route('projects.edit', page.props.project.id)">Открыть настройки</Link></Button
                    >
                </div>
            </div>

            <section v-if="page.props.opusClips && !managing" aria-label="Библиотека клипов" class="space-y-4">
                <ClipLibrary
                    :clips="page.props.opusClips"
                    :project="page.props.project"
                    :loading="loading"
                    :error="error"
                    :active="!!page.props.filters.active"
                    @select="viewingClip = $event"
                    @active="
                        filters.active = Number(!filters.active);
                        applyFilters();
                    "
                    @reset="
                        filters.active = 0;
                        applyFilters();
                    "
                    @page="changePage"
                    @refresh="refresh"
                />
            </section>
            <SeriesWorkspace
                v-else-if="page.props.series && page.props.characters"
                actions-target="#project-heading-actions"
                :project="page.props.project"
                :series="page.props.series"
                :characters="page.props.characters"
                :episodes="page.props.episodes"
            />
            <section
                v-else-if="!page.props.opusClips"
                class="grid min-w-0 items-start gap-4 md:grid-cols-[min(34%,400px)_minmax(0,1fr)]"
                aria-labelledby="project-history-heading"
            >
                <ProjectArtwork :name="page.props.project.name" :cover="page.props.project.cover" class="h-[210px] w-full rounded-xl md:h-[393px]" />
                <div class="min-w-0">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 id="project-history-heading" class="text-xl font-semibold">История роликов</h2>
                    </div>
                    <div v-for="prompt in page.props.prompts.data.slice(0, 3)" :key="prompt.id">
                        <VideoPreview v-if="prompt.url" :prompt="prompt">
                            <template #trigger>
                                <button type="button" class="studio-history-row" :aria-label="'Предпросмотр · ' + prompt.title">
                                    <strong>{{ prompt.title }}</strong><span class="text-primary">Собрано</span>
                                </button>
                            </template>
                        </VideoPreview>
                        <div v-else class="studio-history-row">
                            <strong>{{ prompt.title }}</strong><span class="text-muted-foreground">{{
                                prompt.generated ? 'Собрано' : prompt.stage === 'image' ? 'Запланировано' : 'В работе'
                            }}</span>
                        </div>
                    </div>
                    <p v-if="!page.props.prompts.data.length" class="text-muted-foreground text-sm">Здесь появятся ролики проекта.</p>
                </div>
            </section>

            <p
                v-if="!page.props.project.is_active && !page.props.opusClips"
                role="status"
                class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm"
            >
                Проект приостановлен. Для возобновления производства включите его в настройках.
            </p>

            <div v-if="page.props.series || managing" class="contents">
                <div v-if="page.props.opusClips" class="space-y-3 py-3">
                    <Link :href="route('projects.edit', page.props.project.id)" class="text-sm underline">Открыть настройки проекта</Link>
                    <details v-for="report in page.props.gamingTrends" :key="report.game" class="bg-card rounded-xl border p-5">
                        <summary class="cursor-pointer font-medium">{{ report.game }} · Лучшие видео YouTube за неделю</summary>
                        <p class="text-muted-foreground my-2 text-xs">Обновлено {{ formatProjectDate(report.checkedAt) }}</p>
                        <ol class="list-inside list-decimal space-y-2 text-sm">
                            <li v-for="{ title, channel, views, views_per_day, url } in report.entries" :key="url">
                                <a :href="url" target="_blank" rel="noopener noreferrer" class="underline">{{ title }}</a> · {{ channel }} ·
                                {{ views }} просмотров<span v-if="views_per_day != null">
                                    · {{ views_per_day }} просмотров в день с даты публикации</span
                                >
                            </li>
                        </ol>
                        <p v-if="!report.entries.length" class="text-muted-foreground text-sm">За эту неделю видео не найдены.</p>
                    </details>
                </div>
                <dl class="bg-card grid divide-x rounded-xl border" :class="page.props.opusClips ? 'grid-cols-2' : 'grid-cols-3'">
                    <div class="px-4 py-4 sm:px-6">
                        <dt class="text-muted-foreground text-xs sm:text-sm">
                            {{ page.props.opusClips ? 'Импортированные клипы' : page.props.series ? 'Эпизоды' : 'Генерации' }}
                        </dt>
                        <dd class="mt-2 text-2xl font-semibold tabular-nums">
                            {{ page.props.opusClips?.meta.total ?? page.props.series?.episodes_count ?? page.props.project.prompts_count }}
                        </dd>
                    </div>
                    <div v-if="!page.props.opusClips" class="px-4 py-4 sm:px-6">
                        <dt class="text-muted-foreground text-xs sm:text-sm">{{ page.props.series ? 'Персонажи' : 'Готовые видео' }}</dt>
                        <dd class="mt-2 text-2xl font-semibold tabular-nums">
                            {{ page.props.series?.characters_count ?? page.props.project.ready_prompts_count }}
                        </dd>
                    </div>
                    <div class="px-4 py-4 sm:px-6">
                        <dt class="text-muted-foreground text-xs sm:text-sm">Каналы</dt>
                        <dd class="mt-2 text-2xl font-semibold tabular-nums">{{ page.props.project.channels_count }}</dd>
                    </div>
                </dl>

                <ProductionPanel
                    v-if="
                        !page.props.opusClips &&
                        page.props.auth.can_manage_series &&
                        !page.props.series &&
                        page.props.project.content_mode === 'short'
                    "
                    :project-id="page.props.project.id"
                    :run="page.props.project.latest_production"
                    :project-enabled="page.props.project.is_active"
                />

                <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(16rem,1fr)]">
                    <section class="bg-card min-w-0 overflow-hidden rounded-xl border" aria-labelledby="project-brief-heading">
                        <div class="border-b px-5 py-4">
                            <h2 id="project-brief-heading" class="font-semibold">{{ page.props.series ? 'Мир и завязка' : 'Базовый промпт' }}</h2>
                            <p class="text-muted-foreground mt-1 text-sm">Используется при подготовке новых идей.</p>
                        </div>
                        <div
                            tabindex="0"
                            role="region"
                            :aria-label="page.props.series ? 'Мир и завязка' : 'Базовый промпт'"
                            class="focus-visible:ring-ring/50 max-h-72 overflow-y-auto p-5 text-sm leading-7 break-words whitespace-pre-wrap outline-none focus-visible:ring-2 focus-visible:ring-inset"
                        >
                            {{ page.props.series?.specification.premise ?? page.props.project.prompt }}
                        </div>
                    </section>
                    <section class="bg-card min-w-0 overflow-hidden rounded-xl border" aria-labelledby="project-channels-heading">
                        <div class="border-b px-5 py-4">
                            <h2 id="project-channels-heading" class="font-semibold">Каналы публикации</h2>
                            <p class="text-muted-foreground mt-1 text-sm">Аккаунты этого проекта.</p>
                        </div>
                        <ul v-if="page.props.project.channels.length" class="divide-y">
                            <li v-for="channel in page.props.project.channels" :key="channel.id" class="flex items-center gap-3 px-5 py-4">
                                <ChannelIcon :channel="channel.type" class="size-5 shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium break-words">{{ channel.name }}</p>
                                    <p class="text-muted-foreground mt-0.5 text-xs">{{ channel.type_label }}</p>
                                </div>
                                <Link v-if="page.props.opusClips" :href="route('channels.edit', channel.id)" class="text-sm underline"
                                    >Настроить подключение</Link
                                >
                                <span v-else class="text-muted-foreground shrink-0 text-xs tabular-nums">Публикации: {{ channel.posts_count }}</span>
                            </li>
                        </ul>
                        <EmptyState v-else title="Пока нет подключённых каналов" description="Готовые ролики доступны в истории проекта." />
                    </section>
                </div>

                <dl class="text-muted-foreground flex flex-wrap gap-x-6 gap-y-2 text-xs">
                    <div class="flex gap-1">
                        <dt>Создан</dt>
                        <dd>{{ formatProjectDate(page.props.project.created_at) }}</dd>
                    </div>
                    <div class="flex gap-1">
                        <dt>Изменён</dt>
                        <dd>{{ formatProjectDate(page.props.project.updated_at) }}</dd>
                    </div>
                </dl>
                <ProjectArchive :project-id="page.props.project.id" :archived="!!page.props.project.archived_at" />
                <section
                    class="flex flex-col items-start justify-between gap-4 rounded-xl border p-5 sm:flex-row sm:items-center"
                    aria-labelledby="delete-project-heading"
                >
                    <div>
                        <h2 id="delete-project-heading" class="text-sm font-semibold">Удалить проект</h2>
                        <p class="text-muted-foreground mt-1 max-w-prose text-sm">
                            {{
                                page.props.project.can_delete ? 'Пустой проект можно удалить.' : 'Удаление доступно для пустых проектов без каналов.'
                            }}
                        </p>
                    </div>
                    <DeleteProjectDialog :project="page.props.project" />
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.project-header > [data-slot='button'] {
    font-weight: 700;
}
@media (min-width: 1100px) {
    :global([data-slot='sidebar-inset']:has(.selected-clip)) {
        --clip-gutter: clamp(28px, 4vw, 64px);
        --studio-content-width: calc(100% - 2 * var(--clip-gutter));
        --studio-content-max: calc(1360px - 2 * var(--clip-gutter));
    }
}
@media (min-width: 1920px) {
    :global([data-slot='sidebar-inset']:has(.selected-clip)) {
        --studio-content-max: calc(1568px - 2 * var(--clip-gutter));
    }
}
.selected-clip {
    padding-block-start: 32px;
    gap: 14px;
}
.short-project {
    gap: 23px;
}
@media (min-width: 768px) and (max-width: 1099px) {
    .short-project {
        gap: 25px;
    }
}
@media (max-width: 767px) {
    .clip-project-page {
        padding-top: 16px;
    }
    .selected-clip {
        padding-block-start: 20px;
        gap: 13px;
    }
    .short-project {
        padding-top: 15px;
        gap: 28px;
    }
    .short-project [aria-labelledby='project-history-heading'] {
        row-gap: 18px;
    }
    .short-project #project-history-heading {
        margin-bottom: 3px;
    }
    .short-project .studio-history-row {
        min-height: 68px;
    }
}
</style>
