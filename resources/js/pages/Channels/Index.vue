<script setup lang="ts">
import ChannelIcon from '@/components/ChannelIcon.vue';
import Heading from '@/components/Heading.vue';
import ListPanel from '@/components/ListPanel.vue';
import ProjectNotice from '@/components/projects/ProjectNotice.vue';
import { Button } from '@/components/ui/button';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData } from '@/types';
import type { ManagedChannel } from '@/types/channels';
import type { PaginatedResource } from '@/types/content';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Plus } from '@lucide/vue';
import { useRoute } from 'ziggy-js';

const page = usePage<SharedData & { channels: PaginatedResource<ManagedChannel> }>();
const route = useRoute();
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Channels', href: route('channels.index') }];
const { loading, error, changePage, refresh } = useListFilters('channels.index', () => ({
    page: page.props.channels.meta.current_page,
    sort: 'asc',
    perPage: 20,
}));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Channels" />
        <div class="workspace-page">
            <ProjectNotice />
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row">
                <Heading title="Channels" description="Connect your publishing accounts once, then choose them in any project." />
                <Button as-child class="h-11 shrink-0"
                    ><Link :href="route('channels.create')"><Plus class="size-4" aria-hidden="true" />New channel</Link></Button
                >
            </div>
            <ListPanel
                :meta="page.props.channels.meta"
                :count="page.props.channels.data.length"
                :loading="loading"
                :error="error"
                empty-title="Connect your first channel"
                empty-description="Add a YouTube channel or Instagram professional account to start publishing from your projects."
                @page="changePage"
                @refresh="refresh"
            >
                <template #filters><p class="text-muted-foreground text-sm">Shared publishing connections</p></template>
                <div class="divide-y">
                    <article
                        v-for="channel in page.props.channels.data"
                        :key="channel.id"
                        class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <ChannelIcon :channel="channel.type" />
                            <div class="min-w-0 space-y-1">
                                <Link :href="route('channels.edit', channel.id)" class="hover:text-primary text-base font-semibold break-words">{{
                                    channel.name
                                }}</Link>
                                <p class="text-muted-foreground text-sm">
                                    {{ channel.type_label }}<span v-if="channel.account_name"> · {{ channel.account_name }}</span>
                                </p>
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span
                                        class="rounded-full border px-2 py-1"
                                        :class="
                                            channel.is_active && channel.connected
                                                ? 'border-primary/20 bg-primary/10 text-primary'
                                                : 'text-muted-foreground'
                                        "
                                        >{{ !channel.is_active ? 'Disabled' : channel.connected ? 'Connected' : 'Connection needed' }}</span
                                    >
                                    <span class="text-muted-foreground"
                                        >{{ channel.posts_count ?? 0 }} posts · {{ channel.projects?.length ?? 0 }} projects</span
                                    >
                                </div>
                                <div v-if="channel.projects?.length" class="flex flex-wrap gap-x-3 gap-y-1 pt-1">
                                    <Link
                                        v-for="project in channel.projects"
                                        :key="project.id"
                                        :href="route('projects.show', project.id)"
                                        class="text-muted-foreground hover:text-foreground text-xs underline underline-offset-4"
                                        >{{ project.name }}</Link
                                    >
                                </div>
                            </div>
                        </div>
                        <Button as-child variant="outline" class="h-11 shrink-0 self-start sm:self-center"
                            ><Link :href="route('channels.edit', channel.id)">Manage<ArrowUpRight class="size-4" aria-hidden="true" /></Link
                        ></Button>
                    </article>
                </div>
            </ListPanel>
        </div>
    </AppLayout>
</template>
