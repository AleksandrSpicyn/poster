<script setup lang="ts">
import { useRoute } from '@/composables/useRoute';
import { Link } from '@inertiajs/vue3';

const route = useRoute();
</script>
<template>
    <footer>
        <div><Link :href="route('policy')">Privacy Policy</Link><Link :href="route('terms')">Terms of Service</Link></div>
    </footer>
</template>
<style scoped>
footer {
    border-top: 1px solid #725f8a;
}
footer div {
    display: flex;
    gap: clamp(22px, 3vw, 46px);
    max-width: 1780px;
    margin-inline: auto;
    padding: 10px var(--pad);
}
footer a {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    font-size: 12px;
}
a:focus-visible {
    outline: 3px solid var(--lime);
    outline-offset: 4px;
}
a:hover {
    text-decoration: underline;
}
</style>
