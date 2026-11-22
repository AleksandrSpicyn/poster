<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    channelId: number;
    account: string;
    accountId: string;
    creator: { nickname: string; privacy: string[]; comment: boolean; duet: boolean; stitch: boolean; duration: number } | null;
    inbox: boolean;
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
            { title: 'Posts', href: route('posts') },
            { title: 'TikTok', href: route('posts.tiktok.create', channelId) },
        ]"
    >
        <Head title="Publish to TikTok" />
        <div class="workspace-page">
            <h1 class="text-2xl font-semibold">Publish to {{ creator?.nickname ?? account }}</h1>
            <p v-if="!videos.data.length">Attach this channel to a project with a finished video.</p>
            <form v-else class="grid max-w-2xl gap-4" @submit.prevent="form.post(route('posts.tiktok.store', channelId))">
                <label
                    >Video
                    <select v-model="form.media_id" required class="list-select">
                        <option disabled :value="null">Choose a video</option>
                        <option v-for="item in videos.data" :key="item.id" :value="item.id">{{ item.label }}</option>
                    </select></label
                >
                <nav class="flex gap-3" aria-label="Video pages">
                    <Link v-if="videos.prev_page_url" :href="videos.prev_page_url">Previous</Link
                    ><Link v-if="videos.next_page_url" :href="videos.next_page_url">Next</Link>
                </nav>
                <video v-if="video" :src="video.url" controls class="max-h-96" />
                <label
                    >Delivery
                    <select v-model="form.mode" class="list-select">
                        <option v-if="inbox" value="inbox">TikTok inbox</option>
                        <option v-if="creator" value="direct">Direct Post</option>
                    </select></label
                >
                <p v-if="form.mode === 'inbox'">Finish editing and publishing from the notification in the TikTok app.</p>
                <template v-if="form.mode === 'direct' && creator">
                    <label>Caption <textarea v-model="form.title" maxlength="2200" class="list-select w-full" /></label>
                    <label
                        >Visibility
                        <select v-model="form.privacy" required class="list-select">
                            <option disabled value="">Choose visibility</option>
                            <option v-for="value in creator.privacy" :key="value">{{ value }}</option>
                        </select></label
                    >
                    <label><input type="checkbox" v-model="form.comment" :disabled="!creator.comment" /> Allow comments</label>
                    <label><input type="checkbox" v-model="form.duet" :disabled="!creator.duet" /> Allow Duet</label>
                    <label><input type="checkbox" v-model="form.stitch" :disabled="!creator.stitch" /> Allow Stitch</label>
                    <label><input type="checkbox" v-model="form.disclose" /> Disclose commercial content</label>
                    <template v-if="form.disclose"
                        ><label><input type="checkbox" v-model="form.own_brand" /> My brand</label
                        ><label><input type="checkbox" v-model="form.partner" /> Paid partnership</label></template
                    >
                    <label><input type="checkbox" v-model="form.ai" /> AI-generated content</label>
                </template>
                <label
                    ><input type="checkbox" v-model="form.consent" required /> I agree to send this video to {{ account }} with the selected
                    settings.</label
                >
                <p v-if="form.mode === 'direct'">
                    By confirming, you accept the
                    <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener"
                        >TikTok Music Usage Confirmation</a
                    ><span v-if="form.partner">
                        and the
                        <a href="https://www.tiktok.com/legal/page/global/bc-policy/en" target="_blank" rel="noopener"
                            >Branded Content Policy</a
                        ></span
                    >.
                </p>
                <ul v-if="form.hasErrors" role="alert">
                    <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                </ul>
                <Button type="submit" :disabled="!video || !form.consent || form.processing || (!creator && !inbox)">{{
                    form.mode === 'direct' ? 'Publish to TikTok' : 'Send to TikTok inbox'
                }}</Button>
            </form>
        </div>
    </AppLayout>
</template>
