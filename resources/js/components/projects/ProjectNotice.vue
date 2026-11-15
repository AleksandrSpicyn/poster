<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isError = computed(() => typeof page.flash.error === 'string');
const message = computed(() => (isError.value ? page.flash.error : page.flash.success));
</script>

<template>
    <div
        v-if="typeof message === 'string'"
        :role="isError ? 'alert' : 'status'"
        class="rounded-lg border px-4 py-3 text-sm"
        :class="isError ? 'border-destructive/20 bg-destructive/5 text-destructive' : 'border-primary/20 bg-primary/5 text-primary'"
    >
        {{ message }}
    </div>
</template>
