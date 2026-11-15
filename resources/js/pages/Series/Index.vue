<script setup lang="ts">
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
const breadcrumbs = [{ title: 'Series', href: route('series.index') }];

function changePage(page: number) {
    router.get(route('series.index'), { page }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Series" />
        <div class="workspace-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading title="Series" description="Recurring characters, shared story worlds and connected episodes." />
                <Button as-child class="h-11"
                    ><Link :href="route('projects.create')"><Plus class="size-4" aria-hidden="true" />New series project</Link></Button
                >
            </div>
            <ListPanel
                :meta="props.series.meta"
                :count="props.series.data.length"
                :loading="false"
                error=""
                empty-title="No series yet"
                empty-description="Create a project and choose the Series content type."
                @page="changePage"
                @refresh="router.reload()"
            >
                <template #filters><p class="text-muted-foreground text-sm">All series, including archived stories</p></template>
                <div class="divide-y">
                    <article
                        v-for="item in props.series.data"
                        :key="item.id"
                        class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0 space-y-1">
                            <h2 class="font-semibold break-words">
                                <Link :href="route('projects.show', item.project_id)" class="hover:underline">{{ item.name }}</Link>
                            </h2>
                            <p class="text-muted-foreground line-clamp-2 text-sm">{{ item.specification.premise }}</p>
                            <p class="text-muted-foreground text-xs">
                                {{ item.project?.name }} · {{ item.characters_count }} characters · {{ item.episodes_count }} episodes<span
                                    v-if="item.archived_at"
                                >
                                    · Archived</span
                                >
                            </p>
                        </div>
                        <Button as-child variant="outline" class="h-11 shrink-0"
                            ><Link :href="route('projects.show', item.project_id)">Open project</Link></Button
                        >
                    </article>
                </div>
            </ListPanel>
        </div>
    </AppLayout>
</template>
