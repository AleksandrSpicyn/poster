<script setup lang="ts">
import { useId } from 'vue';

defineProps<{ sort: string; perPage: number; directions: Record<string, string>; disabled: boolean }>();
const emit = defineEmits<{ sort: [value: string]; perPage: [value: number] }>();
const id = useId();
</script>

<template>
    <div class="flex flex-wrap items-end gap-3">
        <slot />
        <div class="grid gap-1.5">
            <label :for="`${id}-sort`" class="text-muted-foreground text-xs font-medium">Порядок</label>
            <select
                :id="`${id}-sort`"
                :value="sort"
                :disabled="disabled"
                class="list-select"
                @change="emit('sort', ($event.target as HTMLSelectElement).value)"
            >
                <option v-for="(label, value) in directions" :key="value" :value="value">
                    {{ value === 'asc' ? 'Сначала старые' : value === 'desc' ? 'Сначала новые' : label }}
                </option>
            </select>
        </div>
        <div class="grid gap-1.5">
            <label :for="`${id}-per-page`" class="text-muted-foreground text-xs font-medium">На странице</label>
            <select
                :id="`${id}-per-page`"
                :value="perPage"
                :disabled="disabled"
                class="list-select"
                @change="emit('perPage', Number(($event.target as HTMLSelectElement).value))"
            >
                <option v-for="size in [10, 25, 50, 100]" :key="size" :value="size">{{ size }}</option>
            </select>
        </div>
    </div>
</template>
