<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

defineProps<{
    feedback?: boolean;
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :feedback="feedback" :title="title" :description="description">
        <slot />
    </AuthLayout>
</template>
