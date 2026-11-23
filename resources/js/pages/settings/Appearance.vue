<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { useAppearance } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';

const route = useRoute();
const { appearance, updateAppearance } = useAppearance();
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Профиль', href: route('profile.edit') },
            { title: 'Оформление', href: route('appearance') },
        ]"
    >
        <Head title="Оформление" />
        <SettingsLayout title="Оформление" description="Тема интерфейса">
            <AppearanceTabs :appearance="appearance" @select="updateAppearance" />
        </SettingsLayout>
    </AppLayout>
</template>
