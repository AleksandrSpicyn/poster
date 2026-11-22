<script setup lang="ts">
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

defineProps<{ workspaceLink?: boolean }>();
const route = useRoute();
const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
</script>
<template>
    <header class="access-header">
        <Link :href="route('home')" aria-label="Poster — на главную" class="brand">
            <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>Poster
        </Link>
        <Link v-if="workspaceLink" :href="route(user ? 'dashboard' : 'login')" class="workspace-link">
            {{ user ? 'Моя студия' : 'Войти' }}
        </Link>
    </header>
</template>
<style scoped>
.access-header {
    height: 71px;
    display: flex;
    align-items: center;
    border-bottom: 1px solid var(--border);
}
.brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 23px;
    font-weight: 700;
}
.brand-mark {
    display: flex;
    gap: 2px;
    padding-inline: 5px;
}
.brand-mark i {
    display: block;
    width: 5px;
    height: 20px;
    background: var(--ring);
    transform: skew(-24deg);
}

.workspace-link {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding: 0 16px;
    background: var(--card);
    border: 1px solid var(--input);
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
}
a:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 3px;
}
</style>
