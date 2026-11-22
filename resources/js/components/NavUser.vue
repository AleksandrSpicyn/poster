<script setup lang="ts">
import { useInitials } from '@/composables/useInitials';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { type SharedData, type User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserMenuContent from './UserMenuContent.vue';

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);
const { getInitials } = useInitials();
const { isMobile, state } = useSidebar();
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton size="lg" class="bg-card h-[54px] rounded-[10px] border" aria-label="Меню аккаунта">
                        <span
                            class="bg-sidebar-accent text-sidebar-accent-foreground flex size-8 shrink-0 items-center justify-center rounded-lg font-bold"
                            >{{ getInitials(user.name) }}</span
                        >
                        <span class="flex flex-col gap-0.5 text-left"
                            ><strong>Моя студия</strong><small class="text-muted-foreground">Личный кабинет</small></span
                        >
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'bottom'"
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
