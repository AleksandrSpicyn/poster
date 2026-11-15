<script setup lang="ts">
import { useRoute } from 'ziggy-js';
const route = useRoute();

import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Pagination, PaginationContent, PaginationItem } from '@/components/ui/pagination';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

/* TODO Typification */
const page = usePage<
    import('@/types').SharedData & {
        prompts: {
            data: any[];
            meta: {
                current_page: number;
                per_page: number;
                total: number;
            };
        };
        sortDirections: Record<string, string>;
        defaultSort: string;
        filters: {
            sort?: string;
        };
    }
>();

const pagination = computed(() => ({
    page: page.props.prompts.meta.current_page,
    itemsPerPage: page.props.prompts.meta.per_page,
    total: page.props.prompts.meta.total,
}));

const filters = ref({
    sort: page.props.filters.sort ?? page.props.defaultSort,
    perPage: page.props.prompts.meta.per_page ?? 10,
    page: page.props.prompts.meta.current_page,
});

// Сервер возвращает PromptResource с итоговым медиа; эта страница не запускает генерацию.
const applyFilters = () => {
    router.get(route('prompts'), filters.value, {
        preserveScroll: true,
        preserveState: true,
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Prompts',
        href: '/prompts',
    },
];
</script>

<template>
    <Head title="Prompts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="px-4 py-6">
            <!-- Filters -->
            <div class="flex flex-wrap gap-4">
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
                        <SelectItem v-for="(label, value) in page.props.sortDirections" :key="value" :value="value">
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Table -->
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-[80px]">ID</TableHead>
                        <TableHead class="max-w-[240px] truncate">Prompt</TableHead>
                        <TableHead>Project</TableHead>
                        <TableHead></TableHead>
                        <TableHead>Generated</TableHead>
                        <TableHead>Date</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="prompt in page.props.prompts.data" :key="prompt.id">
                        <TableCell>{{ prompt.id }}</TableCell>
                        <TableCell class="max-w-[240px] truncate">{{ prompt.title }}</TableCell>
                        <TableCell>{{ prompt.project }}</TableCell>
                        <TableCell>
                            <TableCell>
                                <Dialog>
                                    <DialogTrigger as-child v-if="!!prompt.url">
                                        <Button variant="secondary"> View </Button>
                                    </DialogTrigger>
                                    <DialogContent class="max-w-3xl">
                                        <DialogHeader>
                                            <DialogTitle>Watch video</DialogTitle>
                                        </DialogHeader>
                                        <video controls class="h-auto w-full" autoplay loop>
                                            <source :src="prompt.url" type="video/mp4" />
                                            Your browser does not support the video tag.
                                        </video>
                                    </DialogContent>
                                </Dialog>
                            </TableCell>
                        </TableCell>
                        <TableCell>
                            <Check v-if="prompt.generated" class="text-green-500" />
                        </TableCell>
                        <TableCell>{{ prompt.created_at }}</TableCell>
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
