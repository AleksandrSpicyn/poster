<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import type { HomepageProps } from '@/types/homepage';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, useTemplateRef } from 'vue';
import forestCourier from '../../assets/studio/forest-courier.webp';

const workflow = useTemplateRef<HTMLDetailsElement>('workflow');
const tiktok = useTemplateRef<HTMLDetailsElement>('tiktok');

function showWorkflow() {
    if (workflow.value) workflow.value.open = true;
}

onMounted(() => {
    if (window.location.hash === '#workspace') showWorkflow();
    if (window.location.hash === '#tiktok' && tiktok.value) tiktok.value.open = true;
});

defineProps<{ homepage: HomepageProps; channelsUrl: string }>();
</script>

<template>
    <div class="public-frame">
        <Head title="Студия короткого видео">
            <meta name="description" :content="homepage.hero.summary" />
        </Head>
        <PublicHeader workspace-link />
        <main>
            <section class="hero">
                <div>
                    <h1>Студия короткого <strong>видео</strong></h1>
                    <p>Сценарии, кадры, монтаж и выпуск на подключённых каналах.</p>
                    <a class="process-link" @click="showWorkflow" :href="homepage.hero.secondary_cta.href">Посмотреть процесс</a>
                </div>
                <img :src="forestCourier" alt="" />
            </section>
            <details ref="workflow" id="workspace" class="workflow">
                <summary>Серия от сценария до публикации</summary>
                <ol>
                    <li v-for="item in homepage.services.items" :key="item">{{ item }}</li>
                </ol>
            </details>
            <section class="workflow"><Link :href="channelsUrl">Подключённые каналы</Link></section>
            <details ref="tiktok" id="tiktok" class="workflow">
                <summary>Подключение TikTok</summary>
                <ol>
                    <li v-for="item in homepage.process.items" :key="item">{{ item }}</li>
                </ol>
                <p>During Sandbox testing, participating creators use a private TikTok account and select “Only me”.</p>
                <Link :href="channelsUrl" class="underline">Connect TikTok in your workspace</Link>
            </details>
        </main>
        <footer id="contact" class="flex flex-wrap justify-between gap-4 border-t py-6 text-sm">
            <div>
                <p>Poster · {{ homepage.footer.legal_name }}</p>
                <a :href="homepage.contact.cta_href" class="underline">{{ homepage.contact.email }}</a>
            </div>
            <nav aria-label="Юридическая информация" class="flex gap-5 underline">
                <Link :href="route('policy')">Privacy Policy</Link>
                <Link :href="route('terms')">Terms of Service</Link>
            </nav>
        </footer>
    </div>
</template>

<style scoped>
.hero {
    display: grid;
    gap: 24px;
    padding-block: 22px 24px;
}
.hero h1 {
    margin-top: 14px;
    font-size: 34px;
    line-height: 1.42;
    font-weight: 700;
}
.hero strong {
    display: block;
    font-size: 47px;
    color: var(--ring);
}
.hero p {
    margin: 13px 0 20px;
    font-size: 18px;
    line-height: 1.42;
    color: var(--muted-foreground);
}
.hero img {
    width: 100%;
    height: 216px;
    border-radius: 12px;
    object-fit: cover;
    object-position: 50% 54%;
}
.process-link {
    display: inline-flex;
    min-height: 44px;
    align-items: center;
    padding: 0 16px;
    border-radius: 10px;
    color: var(--primary-foreground);
    background: var(--primary);
    font-size: 14px;
    font-weight: 700;
}
.workflow {
    border-top: 1px solid var(--border);
    padding: 14px 0;
}
.workflow summary,
.workflow > a:first-child {
    font-size: 17px;
    font-weight: 700;
    cursor: pointer;
}
.workflow ol {
    list-style: decimal;
    padding: 16px 0 8px 24px;
}
.workflow li,
.workflow p {
    margin-bottom: 8px;
    color: var(--muted-foreground);
}
@media (min-width: 710px) {
    .hero {
        grid-template-columns: 49% 1fr;
        padding-top: 31px;
    }
    .hero h1 {
        font-size: 49px;
    }
    .hero strong {
        font-size: 76px;
    }
    .hero img {
        height: 410px;
    }
}
</style>
