<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResource } from '@/types/content';
import type { SeriesSummary } from '@/types/series';
import { Head, Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ series: PaginatedResource<SeriesSummary>; filters: { active: boolean; updated: boolean } }>();
const route = useRoute();
const breadcrumbs = [{ title: 'Сериалы', href: route('series.index') }];
const plurals = new Intl.PluralRules('ru');
function progress(series: SeriesSummary) {
    if (series.archived_at) return 'В архиве';
    const count = series.accepted_episodes_count ?? 0;
    if (count === 0) return 'Черновик';
    if (series.episode_limit)
        return count + ' из ' + series.episode_limit + (plurals.select(series.episode_limit) === 'one' ? ' эпизода' : ' эпизодов');
    const form = plurals.select(count);
    return count + ' ' + (form === 'one' ? 'эпизод' : form === 'few' ? 'эпизода' : 'эпизодов');
}

function listUrl(active: boolean, updated: boolean) {
    return route('series.index', { ...(active && { active: 1 }), ...(updated && { updated: 1 }) });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Мои сериалы" />
        <div class="workspace-page series-catalog">
            <div class="flex flex-col items-start justify-between gap-4 lg:flex-row">
                <Heading title="Мои сериалы" description="Сериалы студии / Все состояния" />
                <Button as-child class="h-11 font-bold"><Link :href="route('projects.create', { format: 'series' })">Создать сериал</Link></Button>
            </div>
            <nav aria-label="Фильтры сериалов" class="catalog-tabs flex flex-wrap gap-2">
                <Link :href="listUrl(false, filters.updated)" :aria-current="!filters.active ? 'true' : undefined" preserve-scroll>Все</Link>
                <Link :href="listUrl(true, filters.updated)" :aria-current="filters.active ? 'true' : undefined" preserve-scroll>Активные</Link>
                <Link :href="listUrl(filters.active, !filters.updated)" :aria-current="filters.updated ? 'true' : undefined" preserve-scroll
                    >По дате изменения</Link
                >
            </nav>
            <section aria-labelledby="series-catalog">
                <h2 id="series-catalog" class="mb-[19px] text-xl font-bold">В производстве</h2>
                <div v-if="props.series.data.length" class="grid grid-cols-1 gap-4 min-[700px]:grid-cols-2 min-[1100px]:grid-cols-3">
                    <article v-for="item in props.series.data" :key="item.id" class="bg-card overflow-hidden rounded-xl border">
                        <Link
                            :href="route('series.show', { project: item.project_id, series: item.id })"
                            :aria-label="'Открыть сериал ' + item.name"
                            class="focus-visible:ring-ring block h-full focus-visible:ring-2 focus-visible:ring-inset"
                        >
                            <ProjectArtwork :name="item.project?.name ?? item.name" :cover="item.project?.cover" class="aspect-[1.85] w-full" />
                            <div class="space-y-2 px-4 pt-3 pb-5">
                                <h3 class="text-xl font-bold break-words">{{ item.name }}</h3>
                                <p class="text-muted-foreground text-sm">{{ progress(item) }}</p>
                            </div>
                        </Link>
                    </article>
                </div>
                <EmptyState
                    v-else
                    title="Пока нет сериалов"
                    :description="filters.active ? 'Попробуйте открыть все сериалы.' : 'Создайте первый сериал и заполните его паспорт.'"
                />
            </section>
            <footer class="text-muted-foreground flex flex-wrap items-center gap-4 text-sm">
                <span role="status"
                    >Показано {{ series.meta.current_page > 1 ? series.meta.from + '–' : '' }}{{ series.meta.to ?? 0 }} из
                    {{ series.meta.total }}</span
                >
                <Link v-if="series.links.prev" :href="series.links.prev" class="underline">Предыдущая страница</Link>
                <Link v-if="series.links.next" :href="series.links.next" class="underline">Следующая страница</Link>
            </footer>
        </div>
    </AppLayout>
</template>

<style scoped>
.series-catalog {
    gap: 22px;
}
@media (min-width: 768px) and (max-width: 1099px) {
    .series-catalog {
        gap: 21px;
    }
}
.catalog-tabs a {
    display: inline-flex;
    align-items: center;
    min-height: 40px;
    padding: 0 12px;
    border: 1px solid var(--border);
    background: var(--muted);
    color: var(--muted-foreground);
    border-radius: 9px;
    font-size: 14px;
}
.catalog-tabs a[aria-current] {
    background: color-mix(in srgb, var(--primary) 12%, var(--background));
    color: var(--primary);
}
</style>
