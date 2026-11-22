<script setup lang="ts">
import AppLogo from './AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useNavigation } from '@/composables/useNavigation';
import { computed } from 'vue';
import { Settings } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const route = useRoute();

const { main, footer: footerNavItems } = useNavigation();
const groups = computed(() => [
    { title: 'Студия', items: main.value.filter((item) => ['Обзор', 'Проекты', 'Сериалы', 'Генерации'].includes(item.title)) },
    { title: 'Доставка', items: main.value.filter((item) => ['Публикации', 'Каналы', 'Источники'].includes(item.title)) },
    {
        title: 'Аккаунт',
        items: [
            {
                title: 'Настройки',
                href: route('profile.edit'),
                icon: Settings,
                isActive: route().current('profile.*') || route().current('password.edit') || route().current('appearance'),
            },
        ],
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader class="px-4 pt-4 pb-0 group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:px-1">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')" aria-label="Poster — обзор студии">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <div class="mt-3 w-full group-data-[collapsible=icon]:hidden"><NavUser /></div>
        </SidebarHeader>

        <SidebarContent class="mt-6 gap-2">
            <NavMain v-for="group in groups" :key="group.title" :title="group.title" :items="group.items" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <div class="hidden group-data-[collapsible=icon]:block"><NavUser /></div>
            <slot />
        </SidebarFooter>
    </Sidebar>
</template>
