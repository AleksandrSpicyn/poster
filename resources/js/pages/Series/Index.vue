<script setup lang="ts">
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import Heading from '@/components/Heading.vue';
import ListPanel from '@/components/ListPanel.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResource } from '@/types/content';
import type { SeriesSummary } from '@/types/series';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ series: PaginatedResource<SeriesSummary> }>();
const route = useRoute();
const breadcrumbs = [{ title: 'Сериалы', href: route('series.index') }];

function changePage(page: number) {
    router.get(route('series.index'), { page }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Мои сериалы" />
        <div class="workspace-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading title="Мои сериалы" description="Сериалы студии / Все состояния" />
                <Button as-child class="h-11"
                    ><Link :href="route('projects.create', { format: 'series' })"
                        ><Plus class="size-4" aria-hidden="true" />Создать сериал</Link
                    ></Button
                >
            </div>
            <ListPanel
                :meta="props.series.meta"
                :count="props.series.data.length"
                :loading="false"
                error=""
                empty-title="Пока нет сериалов"
                empty-description="Создайте первый сериал и заполните его паспорт."
                @page="changePage"
                @refresh="router.reload()"
            >
                <template #filters><p class="text-muted-foreground text-sm">Сериалы студии</p></template>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="item in props.series.data"
                        :key="item.id"
                        class="bg-card flex min-w-0 flex-col gap-3 overflow-hidden rounded-xl border pb-4"
                    >
                        <Link :href="route('series.show', { project: item.project_id, series: item.id })" tabindex="-1" aria-hidden="true"
                            ><ProjectArtwork :name="item.project?.name ?? item.name" :cover="item.project?.cover" class="aspect-[1.85] w-full"
                        /></Link>
                        <div class="min-w-0 space-y-1 px-4">
                            <h2 class="font-semibold break-words">
                                <Link :href="route('series.show', { project: item.project_id, series: item.id })" class="hover:underline">{{
                                    item.name
                                }}</Link>
                            </h2>
                            <p class="text-muted-foreground line-clamp-2 text-sm">{{ item.specification.premise }}</p>
                            <p class="text-muted-foreground text-xs">
                                {{ item.project?.name }} · {{ item.characters_count }} персонажей · {{ item.episodes_count }} эпизодов<span
                                    v-if="item.archived_at"
                                >
                                    · В архиве</span
                                >
                            </p>
                        </div>
                        <Button as-child variant="outline" class="h-11 shrink-0"
                            ><Link :href="route('series.show', { project: item.project_id, series: item.id })">Открыть сериал</Link></Button
                        >
                    </article>
                </div>
            </ListPanel>
        </div>
    </AppLayout>
</template>
