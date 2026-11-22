<script setup lang="ts">
import ChannelIcon from '@/components/ChannelIcon.vue';
import Heading from '@/components/Heading.vue';
import ListControls from '@/components/ListControls.vue';
import ListPanel from '@/components/ListPanel.vue';
import PublicationItem from '@/components/PublicationItem.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { PostsPageProps } from '@/types/content';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<PostsPageProps>();
const { filters, loading, error, applyFilters, changeSort, changePerPage, changePage, refresh } = useListFilters('posts', () => ({
    type: page.props.filters.type ?? page.props.allTypes,
    sort: page.props.filters.sort ?? page.props.defaultSort,
    perPage: page.props.posts.meta.per_page,
    page: page.props.posts.meta.current_page,
}));
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Posts', href: route('posts') }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Posts" />
        <div class="workspace-page">
            <Heading title="Posts" description="Published videos across your channels. Your complete publishing history in one place." />
            <section v-if="page.props.destinations.length" class="flex flex-wrap gap-3" aria-label="Create a TikTok publication">
                <Link
                    v-for="channel in page.props.destinations"
                    :key="channel.id"
                    :href="route('posts.tiktok.create', channel.id)"
                    class="list-select"
                >
                    Publish to {{ channel.name }}
                </Link>
            </section>
            <section v-if="page.props.requests.length" class="space-y-2">
                <h2 class="font-semibold">Recent publication requests</h2>
                <ul class="divide-y">
                    <li v-for="item in page.props.requests" :key="item.id" class="py-2">
                        #{{ item.id }} · {{ item.channel_name }} · {{ item.status }} <span v-if="item.error_message">{{ item.error_message }}</span>
                        <Form
                            v-if="item.status === 'submission_unknown'"
                            :action="route('posts.requests.resolve', item.id)"
                            method="post"
                            v-slot="{ errors, processing }"
                        >
                            <label>External video ID <input name="external_id" class="list-select" required maxlength="128" /></label>
                            <button type="submit" class="list-select" :disabled="processing">Verify publication</button>
                            <p v-if="errors.external_id || errors.publication" role="alert">{{ errors.external_id ?? errors.publication }}</p>
                        </Form>
                    </li>
                </ul>
            </section>
            <ListPanel
                :meta="page.props.posts.meta"
                :count="page.props.posts.data.length"
                :loading="loading"
                :error="error"
                empty-title="No posts to show"
                empty-description="Published videos will appear here. If a channel filter is selected, try choosing all channels."
                @page="changePage"
                @refresh="refresh"
            >
                <template #filters>
                    <ListControls
                        :sort="filters.sort"
                        :per-page="filters.perPage"
                        :directions="page.props.sortDirections"
                        :disabled="loading"
                        @sort="changeSort"
                        @per-page="changePerPage"
                    >
                        <div class="grid gap-1.5">
                            <label for="post-channel" class="text-muted-foreground text-xs font-medium">Channel</label>
                            <select id="post-channel" v-model="filters.type" class="list-select" :disabled="loading" @change="applyFilters">
                                <option :value="page.props.allTypes">All channels</option>
                                <option v-for="(label, value) in page.props.types" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </div>
                    </ListControls>
                </template>
                <div class="divide-y md:hidden"><PublicationItem v-for="post in page.props.posts.data" :key="post.id" :post="post" /></div>
                <Table class="hidden md:table">
                    <TableHeader
                        ><TableRow
                            ><TableHead>Publication</TableHead><TableHead>Channel</TableHead><TableHead>Published</TableHead
                            ><TableHead class="text-right">Link</TableHead></TableRow
                        ></TableHeader
                    >
                    <TableBody>
                        <TableRow v-for="post in page.props.posts.data" :key="post.id">
                            <TableCell class="font-medium">Post #{{ post.id }}</TableCell>
                            <TableCell
                                ><span class="inline-flex items-center gap-2"
                                    ><ChannelIcon :channel="post.channel" />{{
                                        post.channel ? (page.props.types[post.channel] ?? post.channel) : 'Deleted channel'
                                    }}</span
                                ></TableCell
                            >
                            <TableCell class="text-muted-foreground">{{ post.created_at ?? '—' }}</TableCell>
                            <TableCell class="text-right"
                                ><a
                                    v-if="post.url"
                                    :href="post.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-primary inline-flex min-h-9 items-center gap-1.5 font-medium hover:underline"
                                    >Open post<ExternalLink class="size-3.5" aria-hidden="true" /><span class="sr-only">
                                        #{{ post.id }} (opens in a new tab)</span
                                    ></a
                                ><span v-else class="text-muted-foreground">Link unavailable</span></TableCell
                            >
                        </TableRow>
                    </TableBody>
                </Table>
            </ListPanel>
        </div>
    </AppLayout>
</template>
