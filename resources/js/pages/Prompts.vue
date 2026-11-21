<script setup lang="ts">
import GenerationItem from '@/components/GenerationItem.vue';
import GenerationStage from '@/components/GenerationStage.vue';
import Heading from '@/components/Heading.vue';
import ListControls from '@/components/ListControls.vue';
import ListPanel from '@/components/ListPanel.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import VideoPreview from '@/components/VideoPreview.vue';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { PromptsPageProps } from '@/types/content';
import { Head, usePage } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<PromptsPageProps>();
const { filters, loading, error, changeSort, changePerPage, changePage, refresh } = useListFilters('prompts', () => ({
    sort: page.props.filters.sort ?? page.props.defaultSort,
    perPage: page.props.prompts.meta.per_page,
    page: page.props.prompts.meta.current_page,
}));
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Prompts', href: route('prompts') }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Prompts" />
        <div class="workspace-page">
            <Heading title="Prompts" description="Follow each idea from its first image to a finished video. Refresh to check the latest results." />
            <ListPanel
                :meta="page.props.prompts.meta"
                :count="page.props.prompts.data.length"
                :loading="loading"
                :error="error"
                empty-title="No generations yet"
                empty-description="Ideas and their results will appear here once a project starts generating videos."
                @page="changePage"
                @refresh="refresh"
            >
                <template #filters
                    ><ListControls
                        :sort="filters.sort"
                        :per-page="filters.perPage"
                        :directions="page.props.sortDirections"
                        :disabled="loading"
                        @sort="changeSort"
                        @per-page="changePerPage"
                /></template>
                <div class="divide-y md:hidden"><GenerationItem v-for="prompt in page.props.prompts.data" :key="prompt.id" :prompt="prompt" /></div>
                <Table class="hidden md:table">
                    <TableHeader
                        ><TableRow
                            ><TableHead class="min-w-64">Idea</TableHead><TableHead>Project</TableHead><TableHead class="min-w-44">Stage</TableHead
                            ><TableHead>Created</TableHead><TableHead class="text-right">Video</TableHead></TableRow
                        ></TableHeader
                    >
                    <TableBody>
                        <TableRow v-for="prompt in page.props.prompts.data" :key="prompt.id">
                            <TableCell class="max-w-sm whitespace-normal"
                                ><p class="line-clamp-2 leading-relaxed font-medium" :title="prompt.title">{{ prompt.title }}</p>
                                <span class="text-muted-foreground mt-1 block text-xs">#{{ prompt.id }}</span></TableCell
                            >
                            <TableCell>{{ prompt.project ?? 'Deleted project' }}</TableCell>
                            <TableCell><GenerationStage :prompt="prompt" /></TableCell>
                            <TableCell class="text-muted-foreground">{{ prompt.created_at ?? '—' }}</TableCell>
                            <TableCell class="text-right"><VideoPreview :prompt="prompt" /></TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </ListPanel>
            <p class="text-muted-foreground text-xs leading-relaxed">
                Stages reflect saved results. A waiting stage may also mean that a task needs attention; publication is tracked separately in Posts.
            </p>
        </div>
    </AppLayout>
</template>
