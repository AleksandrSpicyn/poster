<script setup lang="ts">
import { Pagination, PaginationContent, PaginationItem } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage<{
    posts: {
        data: any[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        type?: string;
        sort?: string;
    };
    types: Record<string, string>;
}>();

const pagination = computed(() => ({
    page: page.props.posts.current_page,
    itemsPerPage: page.props.posts.per_page,
    total: page.props.posts.total,
}));

const filters = ref({
    type: page.props.filters.type ?? 'All',
    sort: page.props.filters.sort ?? 'desc',
    perPage: page.props.posts.per_page ?? 10,
    page: page.props.posts.current_page,
});

const types = computed(() => page.props.types);

const applyFilters = () => {
    router.get('/posts', filters.value, {
        preserveScroll: true,
        preserveState: true,
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Posts',
        href: '/posts',
    },
];
</script>

<template>
    <Head title="Posts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-6">
            <!-- Filters -->
            <div class="flex flex-wrap gap-4">
                <Select v-model="filters.type" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="All Types" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="All">All Types</SelectItem>
                        <SelectItem v-for="(label, key) in types" :key="key" :value="key">
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="filters.perPage" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-[140px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="10">10</SelectItem>
                        <SelectItem :value="25">25</SelectItem>
                        <SelectItem :value="50">50</SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="filters.sort" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-[140px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="desc">Newest</SelectItem>
                        <SelectItem value="asc">Oldest</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Table -->
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[80px]">ID</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>URL</TableHead>
                        <TableHead>Date</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="post in page.props.posts.data" :key="post.id">
                        <TableCell>{{ post.id }}</TableCell>
                        <TableCell>
                            <img :src="`/icons/${post.channel.type}.svg`" :alt="post.channel.type" class="h-5 w-5" />
                        </TableCell>
                        <TableCell>
                            <a :href="post.url" target="_blank" class="text-blue-600 underline">
                                {{ post.url }}
                            </a>
                        </TableCell>
                        <TableCell>{{ post.created_at ? new Date(post.created_at).toLocaleString() : '' }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <!-- Pagination -->
            <div class="flex justify-center pt-4">
                <Pagination
                    v-model:page="filters.page"
                    :items-per-page="pagination.itemsPerPage"
                    :total="pagination.total"
                    @update:page="applyFilters"
                >
                    <PaginationContent v-slot="{ items }">
                        <template v-for="(item, index) in items" :key="index">
                            <PaginationItem v-if="item.type === 'page'" :value="item.value" :is-active="item.value === filters.page">
                                {{ item.value }}
                            </PaginationItem>
                        </template>
                    </PaginationContent>
                </Pagination>
            </div>
        </div>
    </AppLayout>
</template>
