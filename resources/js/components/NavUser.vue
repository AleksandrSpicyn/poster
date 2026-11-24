<script setup lang="ts">
import { getInitials } from '@/lib/getInitials';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountBalance from './AccountBalance.vue';
import UserMenuContent from './UserMenuContent.vue';

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const { setOpenMobile } = useSidebar();
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <UserMenuContent :user="user" @navigate="setOpenMobile(false)">
                <SidebarMenuButton size="lg" class="bg-card h-auto min-h-[54px] rounded-[10px] border group-data-[collapsible=icon]:min-h-0">
                    <span
                        class="bg-sidebar-accent text-sidebar-accent-foreground flex size-8 shrink-0 items-center justify-center rounded-lg font-bold"
                        >{{ getInitials(user.name) }}</span
                    >
                    <span class="flex flex-col gap-0.5 text-left group-data-[collapsible=icon]:hidden"
                        ><strong>Моя студия</strong><AccountBalance class="items-baseline gap-1.5"
                    /></span>
                    <span class="sr-only">Меню аккаунта</span>
                </SidebarMenuButton>
            </UserMenuContent>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
