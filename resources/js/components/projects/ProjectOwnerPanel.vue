<script setup lang="ts">
defineProps<{ owner: string }>();
</script>

<template>
    <aside class="studio-panel hidden min-[1100px]:block">
        <h2 class="font-bold">Мои проекты</h2>
        <p class="mt-[7px]">Студия</p>
        <strong>{{ owner }}</strong>
        <p class="mt-[7px]">Рабочая область</p>
        <span>Моя студия</span>
    </aside>
</template>
