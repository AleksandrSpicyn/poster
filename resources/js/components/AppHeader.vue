<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useNavigation } from '@/composables/useNavigation';
import type { BreadcrumbItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, UserRound } from '@lucide/vue';
import { useRoute } from 'ziggy-js';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), { breadcrumbs: () => [] });
const route = useRoute();
const page = usePage();
const { main } = useNavigation();
</script>

<template>
    <header class="bg-card border-b">
        <div class="flex min-h-18 w-full items-center gap-6 px-4">
            <Sheet>
                <SheetTrigger as-child
                    ><Button variant="ghost" size="icon" class="lg:hidden" aria-label="Open navigation"><Menu class="size-5" /></Button
                ></SheetTrigger>
                <SheetContent side="left" class="p-6"
                    ><SheetHeader><SheetTitle>Poster navigation</SheetTitle></SheetHeader>
                    <nav class="mt-6 grid gap-2">
                        <Link
                            v-for="item in main"
                            :key="item.href"
                            :href="item.href"
                            class="hover:bg-accent rounded-lg px-3 py-3 text-sm font-medium"
                            >{{ item.title }}</Link
                        >
                    </nav></SheetContent
                >
            </Sheet>
            <Link :href="route('dashboard')" class="flex items-center gap-2"><AppLogo /></Link>
            <nav class="hidden items-center gap-2 lg:flex" aria-label="Main navigation">
                <Link
                    v-for="item in main"
                    :key="item.href"
                    :href="item.href"
                    class="text-muted-foreground hover:bg-accent hover:text-foreground rounded-lg px-3 py-2 text-sm font-medium"
                    :class="{ 'bg-accent text-primary': page.url.split('?')[0] === item.href }"
                    >{{ item.title }}</Link
                >
            </nav>
            <DropdownMenu
                ><DropdownMenuTrigger as-child
                    ><Button variant="outline" size="icon" class="ml-auto" aria-label="Open account menu"
                        ><UserRound class="size-4" /></Button></DropdownMenuTrigger
                ><DropdownMenuContent align="end" class="w-56"><UserMenuContent :user="page.props.auth.user" /></DropdownMenuContent
            ></DropdownMenu>
        </div>
        <div v-if="breadcrumbs.length > 1" class="w-full border-t px-4 py-3"><Breadcrumbs :breadcrumbs="breadcrumbs" /></div>
    </header>
</template>
