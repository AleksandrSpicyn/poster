<script setup lang="ts">
import AppFeedback from '@/components/AppFeedback.vue';
import lunarLandscape from '../../../assets/studio/lunar-landscape.webp';
import PublicHeader from '@/components/PublicHeader.vue';

defineProps<{ title?: string; description?: string; feedback?: boolean }>();
</script>

<template>
    <div class="access" :class="{ 'access-feedback': feedback }">
        <PublicHeader />
        <main :class="feedback ? 'feedback-main' : 'access-main'">
            <h1 v-if="feedback" id="feedback-title">{{ title }}</h1>
            <section :class="feedback ? 'feedback-form' : 'access-form'" :aria-labelledby="feedback ? 'feedback-title' : 'access-title'">
                <h2 v-if="feedback">Учётная запись</h2>
                <template v-else>
                    <p class="access-section">Учётная запись</p>
                    <h1 id="access-title">{{ title }}</h1>
                    <p class="access-description">{{ description }}</p>
                </template>
                <AppFeedback />
                <slot />
            </section>
            <img v-if="!feedback" class="access-art" :src="lunarLandscape" alt="" />
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

.access-feedback :deep(.access-header) {
    height: 64px;
}
.feedback-main {
    width: min(100%, 470px);
    margin: 0 auto;
    padding-block: 16px 40px;
}
.feedback-main h1 {
    margin: 0 0 20px;
    font-size: 30px;
    line-height: 43px;
}
.feedback-form {
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--card);
    padding: 13px 16px 20px;
}
.feedback-form h2 {
    font-size: 20px;
    line-height: 26px;
    font-weight: 700;
    margin: 0 0 16px;
}
.feedback-form :deep(form > .grid) {
    gap: 12px;
}
.feedback-form :deep(form > .grid > .grid) {
    gap: 8px;
}
.feedback-form :deep(label) {
    color: var(--muted-foreground);
    font-size: 14px;
    line-height: 20px;
}
.feedback-form :deep(input:not([type='checkbox'])) {
    height: 46px;
    margin: 0;
    width: 100%;
    background: var(--background);
    font-size: 15px;
}
.feedback-form :deep([data-slot='button']) {
    width: fit-content;
    justify-self: start;
    height: 44px;
    margin-top: 4px;
    font-weight: 700;
}
.feedback-form :deep(.account-message) {
    position: relative;
    padding: 8px 12px 14px 46px;
    background: var(--muted);
    border-radius: 8px;
}
.feedback-form :deep(.account-message strong),
.feedback-form :deep(a) {
    color: var(--ring);
    font-weight: 700;
}
.feedback-form :deep(a) {
    text-decoration: none;
}
.feedback-form :deep(a::after) {
    content: ' ↗';
}
.feedback-form :deep(.account-message[role='alert'] strong) {
    color: var(--destructive);
}
.feedback-form :deep(.account-message::before) {
    content: '';
    position: absolute;
    inset: 7px auto 7px 2px;
    width: 3px;
    border-radius: 2px;
    background: var(--ring);
}
.feedback-form :deep(.account-message[role='alert']::before) {
    background: var(--destructive);
}
.feedback-form :deep(.account-message svg) {
    position: absolute;
    top: 13px;
    left: 12px;
    width: 20px;
    height: 20px;
    color: var(--ring);
    stroke-width: 1.5;
}
.feedback-form :deep(.account-message[role='alert'] svg) {
    color: var(--destructive);
}
.feedback-form :deep(.account-message p) {
    margin-top: 7px;
    font-size: 15px;
    line-height: 18px;
    color: var(--muted-foreground);
}
.feedback-form :deep(.feedback-address) {
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}
.feedback-form :deep(.feedback-address p) {
    border: 0;
    padding: 0;
    min-height: 0;
}
@media (max-width: 639px) {
    .feedback-main {
        width: 100%;
    }
}
</style>
