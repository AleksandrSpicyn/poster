<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <div class="bg-primary text-primary-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
            <AppLogoIcon class="size-6" />
        </div>
        <div class="ml-1 grid flex-1 text-left">
            <span class="text-base leading-tight font-semibold tracking-tight">Poster</span
            ><span class="text-muted-foreground mt-0.5 text-xs">Video workspace</span>
        </div>
    </div>
</template>
