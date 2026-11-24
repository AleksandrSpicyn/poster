<script setup lang="ts">
import LandingFooter from '@/components/LandingFooter.vue';
import LandingHomeLink from '@/components/LandingHomeLink.vue';
import LandingHeader from '@/components/LandingHeader.vue';
import { Head } from '@inertiajs/vue3';
import { ArrowUpRight, Mail, Send } from '@lucide/vue';
import plane from '../../assets/studio/contact-plane.webp';

defineProps<{ email: string; telegram: string }>();
</script>

<template>
    <div class="contacts-view">
        <Head title="Контакты" />
        <LandingHeader section="contacts" />
        <main class="contact-main">
            <div class="contact-copy">
                <LandingHomeLink />
                <h1>Контакты</h1>
                <div v-if="email || telegram" class="channels">
                    <a v-if="email" :href="`mailto:${email}`">
                        <Mail aria-hidden="true" />
                        <span
                            ><small>Email</small><strong>{{ email }}</strong></span
                        >
                        <ArrowUpRight aria-hidden="true" />
                    </a>
                    <a v-if="telegram" :href="`https://t.me/${encodeURIComponent(telegram)}`">
                        <Send aria-hidden="true" />
                        <span><small>Telegram</small><strong>Открыть чат</strong></span>
                        <ArrowUpRight aria-hidden="true" />
                    </a>
                </div>
                <p v-else class="empty">Контакты пока не указаны</p>
            </div>
            <img class="plane" :src="plane" alt="" width="780" height="865" />
        </main>
        <LandingFooter />
    </div>
</template>

<style scoped>
.contacts-view {
    --bg: #100c1c;
    --ink: #fbf6ed;
    --lime: #c9fa48;
    --pad: clamp(20px, 3.4vw, 66px);
    min-height: 100svh;
    display: flex;
    flex-direction: column;
    background: radial-gradient(ellipse at 80% 58%, #291c3d 0%, transparent 54%), var(--bg);
    color: var(--ink);
    color-scheme: dark;
    font:
        16px/1.45 'Poster Sans',
        system-ui,
        sans-serif;
}
.contact-main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    align-items: center;
    flex: 1;
    width: 100%;
    max-width: 1780px;
    margin-inline: auto;
}
.contact-copy {
    padding: clamp(34px, 5vw, 80px) 0 clamp(34px, 5vw, 80px) var(--pad);
}
h1 {
    font-size: clamp(54px, 5.4vw, 88px);
    line-height: 0.98;
    letter-spacing: -0.066em;
    font-weight: 700;
}
.channels,
.empty {
    margin-top: clamp(28px, 4vw, 70px);
}
.empty {
    font-size: 14px;
}
a:focus-visible {
    outline: 3px solid var(--lime);
    outline-offset: 4px;
}
.channels a {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: clamp(12px, 1.55vw, 26px);
    padding: clamp(16px, 1.5vw, 25px) 6px;
    border-bottom: 1px solid #836b99;
}
.channels svg {
    width: clamp(26px, 2.7vw, 42px);
    height: auto;
    color: #ddbeed;
    stroke-width: 1.45;
}
.channels svg:last-child {
    width: clamp(20px, 1.8vw, 28px);
}
.channels small {
    display: block;
    color: #d4c7db;
    font-size: clamp(12px, 1.04vw, 17px);
}
.channels strong {
    display: block;
    overflow-wrap: anywhere;
    font-size: clamp(16px, 1.67vw, 27px);
    line-height: 1.2;
    letter-spacing: -0.038em;
}
.channels a:hover strong {
    text-decoration: underline;
}

.plane {
    display: block;
    width: min(100%, max(505px, calc((100svh - 145px) * 780 / 865)));
    height: auto;
    justify-self: center;
    mask-image:
        linear-gradient(to right, transparent, black 12%, black 93%, transparent),
        linear-gradient(to bottom, transparent, black 13%, black 88%, transparent);
    mask-composite: intersect;
}
@media (max-width: 700px) {
    .contact-main {
        grid-template-columns: minmax(0, 1fr);
    }
    .contact-copy {
        padding: 36px var(--pad) 0;
    }
    h1 {
        font-size: clamp(42px, 10vw, 65px);
    }
    .plane {
        width: min(100%, 480px);
    }
}
</style>
