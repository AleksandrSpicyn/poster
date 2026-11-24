<script setup lang="ts">
import { cn } from '@/lib/utils';
import { useEventListener, useMediaQuery } from '@vueuse/core';
import { TooltipProvider } from 'reka-ui';
import { computed, type HTMLAttributes, ref, watch } from 'vue';
import {
    provideSidebarContext,
    SIDEBAR_COOKIE_MAX_AGE,
    SIDEBAR_COOKIE_NAME,
    SIDEBAR_KEYBOARD_SHORTCUT,
    SIDEBAR_WIDTH,
    SIDEBAR_WIDTH_ICON,
} from './utils';

const props = withDefaults(
    defineProps<{
        defaultOpen?: boolean;
        open?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        defaultOpen: true,
        open: undefined,
    },
);

const emits = defineEmits<{
    'update:open': [open: boolean];
}>();

const isMobile = useMediaQuery('(max-width: 699px)');
const openMobile = ref(false);
watch(isMobile, (mobile) => {
    if (!mobile) openMobile.value = false;
});

const tablet = useMediaQuery('(min-width: 700px) and (max-width: 1099px)');
const tabletOpen = ref(false);
const desktopOpen = ref(props.defaultOpen);
const sidebarOpen = computed(() => (tablet.value ? tabletOpen.value : (props.open ?? desktopOpen.value)));

function setOpen(value: boolean) {
    if (tablet.value) {
        tabletOpen.value = value;
        return;
    }
    if (props.open === undefined) desktopOpen.value = value;
    emits('update:open', value);
    document.cookie = `${SIDEBAR_COOKIE_NAME}=${value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`;
}

function setOpenMobile(value: boolean) {
    openMobile.value = value;
}

function toggleSidebar() {
    return isMobile.value ? setOpenMobile(!openMobile.value) : setOpen(!sidebarOpen.value);
}

useEventListener('keydown', (event: KeyboardEvent) => {
    if (event.key === SIDEBAR_KEYBOARD_SHORTCUT && (event.metaKey || event.ctrlKey)) {
        event.preventDefault();
        toggleSidebar();
    }
});

const state = computed(() => (sidebarOpen.value ? 'expanded' : 'collapsed'));

provideSidebarContext({
    state,
    open: sidebarOpen,
    setOpen,
    isMobile,
    openMobile,
    setOpenMobile,
    toggleSidebar,
});
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <div
            data-slot="sidebar-wrapper"
            :style="{
                '--sidebar-width': SIDEBAR_WIDTH,
                '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
            }"
            :class="cn('group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full', props.class)"
            v-bind="$attrs"
        >
            <slot />
        </div>
    </TooltipProvider>
</template>
