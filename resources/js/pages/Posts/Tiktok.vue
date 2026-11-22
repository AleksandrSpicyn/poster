<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    channelId: number;
    account: string;
    accountId: string;
    creator: { nickname: string; privacy: string[]; comment: boolean; duet: boolean; stitch: boolean; duration: number } | null;
    inbox: boolean;
    directUnavailable: boolean;
    videos: { data: { id: number; label: string; url: string }[]; next_page_url: string | null; prev_page_url: string | null };
}>();
const route = useRoute();
const form = useForm({
    media_id: null as number | null,
    account_id: props.accountId,
    mode: props.inbox ? 'inbox' : 'direct',
    title: '',
    privacy: '',
    consent: false,
    comment: false,
    duet: false,
    stitch: false,
    disclose: false,
    own_brand: false,
    partner: false,
    ai: false,
});
watch(
    () => [props.accountId, props.inbox, props.creator] as const,
    ([account, inbox, creator]) => {
        if (form.account_id !== account) {
            form.account_id = account;
            form.privacy = '';
            form.own_brand = false;
            form.partner = false;
            form.disclose = false;
        }
        if (!inbox && creator) form.mode = 'direct';
        if (!creator && inbox) form.mode = 'inbox';
        if (!creator?.privacy.includes(form.privacy)) form.privacy = '';
        if (!creator?.comment) form.comment = false;
        if (!creator?.duet) form.duet = false;
        if (!creator?.stitch) form.stitch = false;
        form.consent = false;
    },
);
const rechecking = ref(false);
function retryCreator() {
    rechecking.value = true;
    router.reload({
        only: ['creator', 'directUnavailable', 'inbox', 'account', 'accountId'],
        onFinish: () => {
            rechecking.value = false;
        },
    });
}
const title = computed(() => (form.mode === 'direct' && props.creator ? 'Прямая публикация в TikTok' : 'Публикация в TikTok'));
const video = computed(() => props.videos.data.find((item) => item.id === form.media_id));
watch(
    () => [
        form.media_id,
        form.mode,
        form.title,
        form.privacy,
        form.disclose,
        form.comment,
        form.duet,
        form.stitch,
        form.own_brand,
        form.partner,
        form.ai,
    ],
    () => {
        form.consent = false;
    },
);
watch(
    () => form.disclose,
    (disclose) => {
        if (!disclose) {
            form.partner = false;
            form.own_brand = false;
        }
    },
);
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Публикации', href: route('posts') },
            { title: 'TikTok', href: route('posts.tiktok.create', channelId) },
        ]"
    >
        <Head :title="title" />
        <div class="workspace-page">
            <h1 class="text-3xl font-bold md:text-[40px]">{{ title }}</h1>
            <p class="text-muted-foreground">Аккаунт · {{ creator?.nickname ?? account }}</p>
            <section v-if="directUnavailable" class="studio-panel space-y-3" aria-label="Проверка аккаунта">
                <p role="status">Не удалось получить настройки прямой публикации для этого аккаунта.</p>
                <Button variant="outline" :disabled="rechecking" @click="retryCreator">{{ rechecking ? 'Проверяем…' : 'Проверить ещё раз' }}</Button>
            </section>
            <p v-if="!videos.data.length">Привяжите канал к проекту с готовым видео.</p>
            <div v-else-if="inbox || creator" class="grid items-start gap-4 md:grid-cols-[min(32%,380px)_minmax(0,1fr)]">
                <video
                    v-if="video"
                    :src="video.url"
                    aria-label="Предпросмотр ролика"
                    controls
                    preload="metadata"
                    class="aspect-[3/4] w-full rounded-xl bg-black object-contain"
                />
                <div v-else class="studio-panel">
                    <h2>Предпросмотр</h2>
                    <p>Выберите видео для просмотра.</p>
                </div>
                <form class="studio-panel grid min-w-0 gap-4" @submit.prevent="form.post(route('posts.tiktok.store', channelId))">
                    <label
                        >Видео
                        <select v-model="form.media_id" required class="list-select">
                            <option disabled :value="null">Выберите готовое видео</option>
                            <option v-for="item in videos.data" :key="item.id" :value="item.id">{{ item.label }}</option>
                        </select></label
                    >
                    <nav class="flex gap-3" aria-label="Страницы видео">
                        <Link v-if="videos.prev_page_url" :href="videos.prev_page_url">Назад</Link
                        ><Link v-if="videos.next_page_url" :href="videos.next_page_url">Далее</Link>
                    </nav>

                    <label
                        >Способ доставки
                        <select v-model="form.mode" class="list-select">
                            <option v-if="inbox" value="inbox">Во входящие TikTok</option>
                            <option v-if="creator" value="direct">Прямая публикация</option>
                        </select></label
                    >
                    <p v-if="form.mode === 'inbox'">Откройте уведомление в TikTok, чтобы завершить редактирование и опубликовать ролик.</p>
                    <template v-if="form.mode === 'direct' && creator">
                        <label>Подпись <textarea v-model="form.title" maxlength="2200" class="list-select w-full" /></label>
                        <label
                            >Видимость
                            <select v-model="form.privacy" required class="list-select">
                                <option disabled value="">Выберите видимость</option>
                                <option v-for="value in creator.privacy" :key="value">{{ value }}</option>
                            </select></label
                        >
                        <label><input type="checkbox" v-model="form.comment" :disabled="!creator.comment" /> Разрешить комментарии</label>
                        <label><input type="checkbox" v-model="form.duet" :disabled="!creator.duet" /> Разрешить дуэты</label>
                        <label><input type="checkbox" v-model="form.stitch" :disabled="!creator.stitch" /> Разрешить Stitch</label>
                        <label><input type="checkbox" v-model="form.disclose" /> Указать коммерческий характер публикации</label>
                        <template v-if="form.disclose"
                            ><label><input type="checkbox" v-model="form.own_brand" /> Собственный бренд</label
                            ><label><input type="checkbox" v-model="form.partner" /> Платное партнёрство</label></template
                        >
                        <label><input type="checkbox" v-model="form.ai" /> Создано с помощью ИИ</label>
                    </template>
                    <label
                        ><input type="checkbox" v-model="form.consent" required /> Подтверждаю отправку этого видео в {{ account }} с выбранными
                        настройками.</label
                    >
                    <p v-if="form.mode === 'direct'">
                        Подтверждая отправку, вы принимаете
                        <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener"
                            >TikTok Music Usage Confirmation</a
                        ><span v-if="form.partner">
                            и
                            <a href="https://www.tiktok.com/legal/page/global/bc-policy/en" target="_blank" rel="noopener"
                                >Branded Content Policy</a
                            ></span
                        >.
                    </p>
                    <ul v-if="form.hasErrors" role="alert">
                        <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                    </ul>
                    <Button type="submit" :disabled="!video || !form.consent || form.processing || (!creator && !inbox)">{{
                        form.mode === 'direct' ? 'Опубликовать в TikTok' : 'Отправить во входящие TikTok'
                    }}</Button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
