<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

const message = ref('');
const unsubscribe: (() => void)[] = [];
onMounted(() => {
    unsubscribe.push(
        router.on('networkError', (event) => {
            event.preventDefault();
            message.value = 'Connection lost. Check your connection and try the action again.';
        }),
        router.on('httpException', (event) => {
            const status = event.detail.response.status;
            if (status !== 403 && status < 500) return;
            event.preventDefault();
            message.value = status === 403 ? 'You do not have access to this action.' : 'Something went wrong. Please try again in a moment.';
        }),
        router.on('success', () => {
            message.value = '';
        }),
    );
});
onUnmounted(() => unsubscribe.forEach((stop) => stop()));
</script>

<template>
    <div
        v-if="message"
        role="alert"
        class="border-destructive/25 bg-destructive/5 text-destructive mx-4 mt-4 flex items-center justify-between gap-4 rounded-lg border p-4 text-sm sm:mx-6"
    >
        <p>{{ message }}</p>
        <Button variant="ghost" size="icon" class="shrink-0" aria-label="Dismiss message" @click="message = ''"><X class="size-4" /></Button>
    </div>
</template>
