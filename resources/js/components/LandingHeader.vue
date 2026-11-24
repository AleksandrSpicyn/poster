<script setup lang="ts">
import { useRoute } from '@/composables/useRoute';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps<{ pricing?: boolean }>();
const emit = defineEmits<{ workflow: [] }>();
const route = useRoute();
const page = usePage<SharedData>();

function homeSection(section: string) {
    return (props.pricing ? route('home') : '') + '#' + section;
}
</script>
<template>
    <header class="head" :class="{ pricing }">
        <div class="outer nav">
            <component :is="pricing ? Link : 'a'" class="logo" :href="pricing ? route('home') : '#main'" aria-label="Poster, главная"
                ><i aria-hidden="true"><b></b><b></b><b></b></i>Poster</component
            >
            <nav aria-label="Навигация">
                <component :is="pricing ? Link : 'a'" :href="homeSection('features')">Возможности</component>
                <component :is="pricing ? Link : 'a'" :href="homeSection('workflow')" @click="emit('workflow')">Рабочий процесс</component>
                <Link :href="route('pricing')" :aria-current="pricing ? 'page' : undefined">Тарифы</Link>
            </nav>
            <a class="login" :href="route(page.props.auth.user ? 'dashboard' : 'login')">{{
                page.props.auth.user ? 'Моя студия' : 'Войти в студию'
            }}</a>
        </div>
    </header>
</template>
<style scoped>
:global(html:has(.landing, .pricing-view)) {
    overflow-y: scroll;
}
a:focus-visible {
    outline: 3px solid var(--lime);
    outline-offset: 5px;
}
.outer {
    max-width: 1780px;
    margin-inline: auto;
    padding-inline: var(--pad);
}
.head {
    border-bottom: 1px solid #696276;
}
.nav {
    display: flex;
    align-items: center;
    gap: clamp(25px, 4.2vw, 80px);
    min-height: calc(clamp(70px, 5.2vw, 92px) - 1px);
}
.logo {
    display: inline-flex;
    gap: clamp(0px, calc(1vw - 8.4px), 9px);
    align-items: center;
    line-height: 1;
    flex-basis: calc(3.66em + 52px);
    font-size: clamp(29px, 3vw, 48px);
    letter-spacing: -0.072em;
    font-weight: 700;
}
.logo i {
    display: inline-flex;
    gap: 4px;
    transform: skew(-24deg) scaleX(1.08);
    margin-inline: 5px;
}
.logo b {
    height: 0.74em;
    width: 0.17em;
    background: currentColor;
}
.nav nav {
    display: flex;
    gap: clamp(18px, 3.2vw, 54px);
}
.nav nav a,
.login {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    font-weight: 700;
    letter-spacing: normal;
}
.nav nav a {
    font-size: clamp(14px, 1.4vw, 22px);
}
.nav nav a:hover {
    color: #d8c5ff;
}
.login {
    margin-left: auto;
    border: 1.5px solid currentColor;
    border-radius: 11px;
    padding: 6px 20px;
}
.login:hover {
    background: var(--ink);
    color: var(--bg);
}

.head.pricing {
    background: #191723;
    border-color: #464051;
}
.nav nav a[aria-current] {
    color: #d7c1ff;
}

@media (prefers-reduced-motion: no-preference) {
    .login {
        transition:
            background-color 0.17s,
            color 0.17s;
    }
}
@media (max-width: 900px) {
    .nav {
        gap: 20px;
    }
    .nav nav {
        gap: 18px;
    }
}
@media (max-width: 700px) {
    .nav {
        min-height: 61px;
        gap: 8px;
    }
    .nav nav {
        display: none;
    }
    .login {
        padding: 5px 12px;
        font-size: 14px;
        border-radius: 9px;
    }
}
</style>
