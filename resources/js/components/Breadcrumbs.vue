<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Link } from '@inertiajs/vue3';

import type { BreadcrumbItem as BreadcrumbEntry } from '@/types';

defineProps<{
    breadcrumbs: BreadcrumbEntry[];
}>();
</script>

<template>
    <Breadcrumb class="min-w-0" aria-label="Хлебные крошки">
        <BreadcrumbList class="flex-nowrap">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem class="min-w-0 first:shrink-0">
                    <template v-if="index === breadcrumbs.length - 1 && !item.href.startsWith('#')">
                        <BreadcrumbPage class="truncate" :title="item.title">{{ item.title }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <a v-if="item.href.startsWith('#')" :href="item.href" class="truncate" :title="item.title">{{ item.title }}</a>
                            <Link v-else :href="item.href" class="truncate" :title="item.title">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" class="shrink-0" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
