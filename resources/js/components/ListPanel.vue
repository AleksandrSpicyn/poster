<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import type { PaginatedResource } from '@/types/content';
import { ArrowLeft, ArrowRight, RefreshCw } from '@lucide/vue';

defineProps<{
    meta: PaginatedResource<unknown>['meta'];
    count: number;
    loading: boolean;
    error: string;
    externalControls?: boolean;
    compactFooter?: boolean;
    emptyTitle: string;
    emptyDescription: string;
}>();
const emit = defineEmits<{ page: [value: number]; refresh: [] }>();
</script>

<template>
    <section class="bg-card text-card-foreground overflow-hidden rounded-xl border" :aria-busy="loading">
        <div v-if="!externalControls" class="flex flex-wrap items-end justify-between gap-4 border-b p-4 sm:p-5">
            <slot name="filters" />
            <Button variant="outline" size="sm" class="h-11 sm:h-9" :disabled="loading" @click="emit('refresh')"
                ><RefreshCw class="size-4" :class="{ 'motion-safe:animate-spin': loading }" aria-hidden="true" /> Обновить</Button
            >
        </div>
        <div
            v-if="error"
            role="alert"
            class="border-destructive/20 bg-destructive/5 text-destructive flex flex-wrap items-center justify-between gap-3 border-b px-5 py-3 text-sm"
        >
            <p>{{ error }}</p>
            <Button variant="outline" size="sm" class="h-11 sm:h-9" :disabled="loading" @click="emit('refresh')">Повторить</Button>
        </div>
        <div class="relative min-h-48">
            <div v-if="loading" role="status" class="bg-primary/25 absolute inset-x-0 top-0 z-10 h-0.5">
                <span class="bg-primary block h-full w-1/2 motion-safe:animate-pulse" /><span class="sr-only">Обновляем результаты…</span>
            </div>
            <div :class="{ 'opacity-50': loading }" class="transition-opacity motion-reduce:transition-none">
                <slot v-if="count" />
                <EmptyState v-else :title="emptyTitle" :description="emptyDescription" />
            </div>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 text-sm sm:px-5" :class="{ 'border-t': !compactFooter }">
            <p class="text-muted-foreground" aria-live="polite">
                <template v-if="compactFooter">Показано </template><template v-else>{{ meta.from ?? 0 }}–</template>{{ meta.to ?? 0 }} из
                {{ meta.total }}
            </p>
            <div v-if="!compactFooter || meta.last_page > 1" class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    class="size-11 sm:size-9"
                    aria-label="Предыдущая страница"
                    :disabled="loading || meta.current_page <= 1"
                    @click="emit('page', meta.current_page - 1)"
                    ><ArrowLeft class="size-4"
                /></Button>
                <span class="text-muted-foreground min-w-24 text-center text-xs">{{ meta.current_page }} из {{ meta.last_page }}</span>
                <Button
                    variant="outline"
                    size="icon"
                    class="size-11 sm:size-9"
                    aria-label="Следующая страница"
                    :disabled="loading || meta.current_page >= meta.last_page"
                    @click="emit('page', meta.current_page + 1)"
                    ><ArrowRight class="size-4"
                /></Button>
            </div>
        </footer>
    </section>
</template>
