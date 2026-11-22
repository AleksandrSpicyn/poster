<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const route = useRoute();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Оформление',
        href: route('appearance', undefined, false),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Оформление" />

        <SettingsLayout title="Оформление" description="Учётная запись / Внешний вид">
            <div class="space-y-6">
                <HeadingSmall title="Тема интерфейса" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
