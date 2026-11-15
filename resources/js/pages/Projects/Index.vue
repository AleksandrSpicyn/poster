<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ListControls from '@/components/ListControls.vue';
import ListPanel from '@/components/ListPanel.vue';
import ProjectNotice from '@/components/projects/ProjectNotice.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatProjectDate } from '@/lib/formatProjectDate';
import type { BreadcrumbItem } from '@/types';
import type { ProjectsPageProps } from '@/types/projects';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, FolderOpen, Pencil, Plus, Search } from '@lucide/vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<ProjectsPageProps>();
const { filters, loading, error, applyFilters, changePage, refresh } = useListFilters('projects.index', () => ({
    search: page.props.filters.search,
    sort: page.props.filters.sort,
    perPage: page.props.projects.meta.per_page,
    page: page.props.projects.meta.current_page,
}));
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Projects', href: route('projects.index') }];

function clearSearch() {
    filters.search = '';
    applyFilters();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Projects" />
        <div class="workspace-page">
            <ProjectNotice />
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row">
                <Heading title="Projects" description="Creative directions for your video series, with their channels and generation history." />
                <Button as-child class="h-11 shrink-0"
                    ><Link :href="route('projects.create')"><Plus class="size-4" aria-hidden="true" />New project</Link></Button
                >
            </div>

            <ListPanel
                :meta="page.props.projects.meta"
                :count="page.props.projects.data.length"
                :loading="loading"
                :error="error"
                :empty-title="page.props.filters.search ? 'No matching projects' : 'Your next series starts here'"
                :empty-description="
                    page.props.filters.search
                        ? 'Try another name or a phrase from the base prompt.'
                        : 'Create a project to give your videos a name, a topic and a consistent creative direction.'
                "
                @page="changePage"
                @refresh="refresh"
            >
                <template #filters>
                    <form class="flex min-w-0 flex-1 basis-full flex-wrap items-end gap-3 lg:basis-auto" role="search" @submit.prevent="applyFilters">
                        <div class="grid min-w-48 flex-1 gap-1.5 sm:max-w-sm">
                            <label for="project-search" class="text-muted-foreground text-xs font-medium">Search projects</label>
                            <Input
                                id="project-search"
                                v-model="filters.search"
                                type="search"
                                placeholder="Name or base prompt"
                                class="h-11"
                                :maxlength="200"
                                :disabled="loading"
                            />
                        </div>
                        <Button type="submit" variant="outline" class="h-11" :disabled="loading"
                            ><Search class="size-4" aria-hidden="true" /><span class="sr-only sm:not-sr-only">Search</span></Button
                        >
                        <Button
                            v-if="filters.search || page.props.filters.search"
                            type="button"
                            variant="ghost"
                            class="h-11"
                            :disabled="loading"
                            @click="clearSearch"
                            >Clear</Button
                        >
                        <ListControls
                            :sort="filters.sort"
                            :per-page="filters.perPage"
                            :directions="page.props.sortDirections"
                            :disabled="loading"
                            @sort="
                                filters.sort = $event;
                                applyFilters();
                            "
                            @per-page="
                                filters.perPage = $event;
                                applyFilters();
                            "
                        />
                    </form>
                </template>

                <div class="divide-y">
                    <article
                        v-for="project in page.props.projects.data"
                        :key="project.id"
                        class="flex min-w-0 flex-col gap-5 px-4 py-5 sm:px-5 xl:flex-row xl:items-center xl:gap-8"
                    >
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <span class="bg-primary/10 text-primary mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-lg"
                                ><FolderOpen class="size-5" aria-hidden="true"
                            /></span>
                            <div class="min-w-0">
                                <h2 class="text-base font-semibold">
                                    <Link
                                        :href="route('projects.show', project.id)"
                                        class="text-foreground hover:text-primary focus-visible:ring-ring/50 rounded-sm break-words outline-none hover:underline focus-visible:ring-2"
                                        >{{ project.name }}</Link
                                    >
                                </h2>
                                <span
                                    v-if="!project.is_active"
                                    class="bg-muted text-muted-foreground mt-2 inline-block rounded-full px-2.5 py-1 text-xs"
                                    >Disabled</span
                                >
                                <p class="text-muted-foreground mt-1.5 line-clamp-2 max-w-2xl text-sm leading-relaxed break-words">
                                    {{ project.prompt }}
                                </p>
                                <p class="text-muted-foreground mt-2 text-xs">Created {{ formatProjectDate(project.created_at) }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-4 pl-13 xl:shrink-0 xl:gap-8 xl:pl-0">
                            <dl class="flex gap-5 text-sm sm:gap-8">
                                <div>
                                    <dt class="text-muted-foreground text-xs">Generations</dt>
                                    <dd class="mt-1 font-semibold tabular-nums">{{ project.prompts_count }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground text-xs">Ready videos</dt>
                                    <dd class="mt-1 font-semibold tabular-nums">{{ project.ready_prompts_count }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted-foreground text-xs">Channels</dt>
                                    <dd class="mt-1 font-semibold tabular-nums">{{ project.channels_count }}</dd>
                                </div>
                            </dl>
                            <div class="flex items-center gap-2">
                                <Button as-child variant="ghost" size="icon" class="size-11"
                                    ><Link :href="route('projects.edit', project.id)" :aria-label="'Edit ' + project.name"
                                        ><Pencil class="size-4" aria-hidden="true" /></Link
                                ></Button>
                                <Button as-child variant="outline" class="h-11"
                                    ><Link :href="route('projects.show', project.id)">Open<ArrowUpRight class="size-4" aria-hidden="true" /></Link
                                ></Button>
                            </div>
                        </div>
                    </article>
                </div>
            </ListPanel>
        </div>
    </AppLayout>
</template>
