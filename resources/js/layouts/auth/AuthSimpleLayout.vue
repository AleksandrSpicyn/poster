<script setup lang="ts">
import AppFeedback from '@/components/AppFeedback.vue';
import lunarLandscape from '../../../assets/studio/lunar-landscape.webp';
import PublicHeader from '@/components/PublicHeader.vue';

defineProps<{ title?: string; description?: string; feedback?: boolean }>();
</script>

<template>
    <div class="access">
        <PublicHeader />
        <main v-if="feedback" class="feedback-main">
            <h1>{{ title }}</h1>
            <section class="feedback-form">
                <h2>Учётная запись</h2>
                <AppFeedback /><slot />
            </section>
        </main>
        <main v-else class="access-main">
            <section class="access-form" aria-labelledby="access-title">
                <p class="access-section">Учётная запись</p>
                <h1 id="access-title">{{ title }}</h1>
                <p class="access-description">{{ description }}</p>
                <AppFeedback />
                <slot />
            </section>
            <img class="access-art" :src="lunarLandscape" alt="" />
        </main>
    </div>
</template>

<style scoped>
.access {
    width: min(calc(100% - 32px), 1280px);
    margin: 0 auto;
    min-height: 100svh;
}
.access-main {
    padding-block: 22px 40px;
}
.access-form {
    width: min(100%, 490px);
    margin: 0 auto;
    padding-top: 14px;
}
.access-section {
    font:
        700 12px/17px 'Poster Mono',
        monospace;
    color: var(--ring);
    text-transform: uppercase;
}
h1 {
    margin-top: 12px;
    font-size: 30px;
    line-height: 1.42;
    font-weight: 700;
}
.access-description {
    margin: 18px 0 29px;
    color: var(--muted-foreground);
    font-size: 16px;
    line-height: 23px;
}
.access-art {
    display: none;
}
.access-form :deep([data-slot='input']) {
    height: 48px;
    margin: 0;
    border-radius: 8px;
    padding-inline: 14px;
    font-size: 16px;
    background: var(--background);
}
.access-form :deep(label) {
    font-size: 14px;
    color: var(--muted-foreground);
}
.access-form :deep([data-slot='button']:not([aria-label])),
.access-form :deep(.access-cta) {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    min-height: 44px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    background: var(--primary);
    color: var(--primary-foreground);
}
.access :deep(:focus-visible) {
    outline: 2px solid var(--ring);
    outline-offset: 3px;
}
.access-form :deep([data-slot='checkbox']) {
    width: 20px;
    height: 20px;
}
@media (min-width: 700px) {
    .access {
        width: min(calc(100% - 48px), 1280px);
    }
    .access-main {
        padding-top: 31px;
    }
    h1 {
        font-size: 38px;
    }
}
@media (min-width: 1001px) {
    .access {
        width: min(calc(100% - 80px), 1280px);
    }
    .access-main {
        display: grid;
        grid-template-columns: 56% 44%;
    }
    .access-form {
        margin: 0 0 0 40px;
        width: min(490px, calc(100% - 80px));
    }
    .access-art {
        display: block;
        width: 100%;
        height: clamp(280px, calc(100svh - 162px), 610px);
        border-radius: 18px;
        object-fit: cover;
        object-position: 50% 54%;
    }
}
@media (min-width: 1920px) {
    .access {
        max-width: 1440px;
        width: calc(100% - 80px);
    }
}

.feedback-main {
    width: min(100%, 472px);
    margin: 0 auto;
    padding-block: 20px 40px;
}
.feedback-main h1 {
    margin: 0 0 16px;
    font-size: clamp(27px, 2.25vw, 34px);
    line-height: 1.3;
}
.feedback-form {
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--card);
    padding: 16px;
}
.feedback-form h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 16px;
}
.feedback-form :deep(input:not([type='checkbox'])) {
    min-height: 44px;
    width: 100%;
    background: var(--background);
}
.feedback-form :deep([data-slot='button']) {
    width: fit-content;
    justify-self: start;
    min-height: 44px;
}
.feedback-form :deep(.account-message) {
    padding: 12px 14px;
    border-inline-start: 3px solid var(--ring);
    background: var(--muted);
    border-radius: 5px;
}
.feedback-form :deep(.account-message[role='alert']) {
    border-inline-start-color: var(--destructive);
}
.feedback-form :deep(.account-message p) {
    margin-top: 4px;
    font-size: 14px;
    color: var(--muted-foreground);
}
</style>
