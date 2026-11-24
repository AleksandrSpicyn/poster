<script setup lang="ts">
import { useRoute } from '@/composables/useRoute';
import { Link } from '@inertiajs/vue3';

const route = useRoute();
</script>
<template>
    <Link class="back" :href="route('home')">← На главную</Link>
</template>
<style scoped>
a:focus-visible {
    outline: 3px solid var(--lime);
    outline-offset: 4px;
}
.back {
    width: fit-content;
    display: inline-flex;
    min-height: 36px;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #d3c7e4;
    margin-bottom: 12px;
}
.back:hover {
    color: white;
}
@media (max-width: 700px) {
    .back {
        margin-bottom: 5px;
        min-height: 44px;
    }
}
</style>
