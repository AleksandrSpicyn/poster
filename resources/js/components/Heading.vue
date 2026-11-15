<script setup lang="ts">
interface Props {
    title: string;
    description?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ title }}</h1>
        <p v-if="description" class="text-muted-foreground max-w-2xl text-sm leading-relaxed">
            {{ description }}
        </p>
    </div>
</template>
