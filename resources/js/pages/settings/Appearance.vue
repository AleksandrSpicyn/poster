<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useAppearance } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Appearance } from '@/types';
import { Head } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const { appearance, updateAppearance } = useAppearance();
function select(event: Event) {
    updateAppearance((event.target as HTMLSelectElement).value as Appearance);
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Профиль', href: route('profile.edit') },
            { title: 'Оформление', href: route('appearance') },
        ]"
    >
        <Head title="Оформление" />
        <div class="workspace-page">
            <header class="flex flex-col items-start justify-between gap-4 min-[1100px]:flex-row">
                <Heading title="Оформление" description="Учётная запись / Внешний вид" />
                <select
                    class="bg-primary text-primary-foreground h-11 rounded-lg px-4 font-semibold"
                    aria-label="Текущая тема"
                    :value="appearance"
                    @change="select"
                >
                    <option value="system">Системная</option>
                    <option value="light">Светлая</option>
                    <option value="dark">Тёмная</option>
                </select>
            </header>
            <AppearanceTabs :appearance="appearance" @select="updateAppearance" />
        </div>
    </AppLayout>
</template>
