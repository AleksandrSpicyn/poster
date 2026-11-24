<script setup lang="ts">
import { useTiktokReceiptRefresh } from '@/composables/useTiktokReceiptRefresh';
import { tiktokPublicationTabs, useTiktokPublicationNavigation } from '@/composables/useTiktokPublicationNavigation';
import EpisodePreview from '@/components/episodes/EpisodePreview.vue';
import Heading from '@/components/Heading.vue';
import TiktokPublicationReceipt from '@/components/TiktokPublicationReceipt.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import type { TiktokPublicationProps } from '@/types/tiktokPublication';
import { useTiktokPublicationForm } from '@/composables/useTiktokPublicationForm';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, toRef, watch } from 'vue';
import { useFormApi } from '@/lib/api/useFormApi';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<TiktokPublicationProps>();
const submitForm = useFormApi();
const route = useRoute();
const page = usePage();
const { form, consentChanged } = useTiktokPublicationForm(props);
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
const { tab, video, finalReceipt, directDetails, selectTab } = useTiktokPublicationNavigation(
    toRef(() => props.videos.data),
    toRef(form, 'media_id'),
);
watch(directDetails, (open) => {
    if (open) consentChanged.value = false;
});

const title = computed(() =>
    finalReceipt.value ? 'Результат отправки' : directDetails.value ? 'Прямая публикация в TikTok' : 'Публикация в TikTok',
);
const { refreshingReceipt, receiptError, refreshReceipt } = useTiktokReceiptRefresh();
const project = computed(() => {
    if (video.value) return video.value.project;
    if (props.videos.next_page_url || props.videos.prev_page_url) return null;
    const first = props.videos.data[0]?.project;
    return first && props.videos.data.every((item) => item.project?.id === first.id) ? first : null;
});
const duration = ref(0);
const durationError = ref(false);
const videoSelect = ref<HTMLSelectElement>();
const tooLong = computed(() => form.mode === 'direct' && props.creator && duration.value > props.creator.duration);
watch(
    () => [form.mode, form.media_id, props.accountId],
    () => {
        directDetails.value = false;
    },
);
const canSubmit = computed(
    () =>
        video.value &&
        !video.value.receipt &&
        form.consent &&
        !form.processing &&
        !tooLong.value &&
        (form.mode === 'inbox'
            ? props.inbox
            : props.creator &&
              duration.value > 0 &&
              form.privacy &&
              (!form.disclose || form.own_brand || form.partner) &&
              (!form.partner || ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS'].includes(form.privacy))),
);
const delivery = computed(() => (form.mode === 'inbox' ? 'Черновик во входящих TikTok' : 'Прямая публикация'));
const statusLabels: Record<string, string> = {
    inbox: 'Отправлено во входящие',
    published: 'Опубликовано',
    planned: 'Запланировано',
    processing: 'Обрабатывается',
    submitting: 'Отправляется',
    publishing_container: 'Публикуется',
    submission_unknown: 'Результат отправки уточняется',
    failed: 'Ошибка отправки',
};
const availableInteractions = computed(() =>
    [props.creator?.comment && 'Комментарии', props.creator?.duet && 'Дуэты', props.creator?.stitch && 'Stitch'].filter(Boolean).join(' · '),
);
const privacyLabels: Record<string, string> = {
    SELF_ONLY: 'Только я',
    PUBLIC_TO_EVERYONE: 'Все',
    MUTUAL_FOLLOW_FRIENDS: 'Друзья',
    FOLLOWER_OF_CREATOR: 'Подписчики',
};
watch(
    () => form.media_id,
    () => {
        receiptError.value = '';
        duration.value = 0;
        durationError.value = false;
    },
);
function readDuration(event: Event) {
    const player = event.currentTarget;
    if (!(player instanceof HTMLVideoElement) || player.currentSrc !== video.value?.url) return;
    duration.value = Number.isFinite(player.duration) && !player.error ? player.duration : 0;
    durationError.value = duration.value <= 0;
}
function submit() {
    if (canSubmit.value)
        submitForm(form, ['posts.tiktok.store', props.channelId], {
            onError: () => {
                tab.value = 'Параметры';
            },
        });
}
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Публикации', href: route('posts') },
            { title: 'TikTok', href: route('posts.tiktok.create', channelId) },
            ...(finalReceipt ? [{ title: title, href: page.url }] : []),
        ]"
    >
        <Head :title="title" />
        <div v-if="finalReceipt && video" class="workspace-page receipt-screen">
            <Heading :title="title" description="Статус" />
            <TiktokPublicationReceipt
                :receipt="finalReceipt"
                :recipient="finalReceipt.current_recipient ? account : null"
                :title="video.label"
                :project="video.project"
                :assembly="video.assembly_number"
                :busy="refreshingReceipt"
                :error="receiptError"
                @refresh="refreshReceipt"
            />
        </div>
        <div v-else class="workspace-page" :class="{ 'direct-publication': directDetails }">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    :title="title"
                    :description="
                        directDetails
                            ? 'Общая форма выпуска / Канал TikTok · Direct Post'
                            : tab === 'Предпросмотр'
                              ? 'Общая форма выпуска / Канал TikTok'
                              : tab === 'Параметры'
                                ? 'Способ доставки'
                                : 'Статус отправки'
                    "
                />
                <Button v-if="directDetails" class="direct-action" form="tiktok-delivery-form" type="submit" :disabled="!canSubmit"
                    >Опубликовать</Button
                >
                <Button
                    v-else-if="tab === 'Предпросмотр' && video"
                    class="preview-action"
                    @click="
                        selectTab(video.receipt ? 'Статус' : 'Параметры');
                        directDetails = !video.receipt && form.mode === 'direct';
                    "
                    >{{ video.receipt ? 'Статус' : form.mode === 'inbox' ? 'Отправить во входящие' : 'Прямая публикация' }}</Button
                >
            </header>
            <section v-if="directUnavailable" class="studio-panel space-y-3" aria-label="Проверка аккаунта">
                <p role="status">Не удалось получить настройки прямой публикации для этого аккаунта.</p>
                <Button variant="outline" :disabled="rechecking" @click="retryCreator">{{ rechecking ? 'Проверяем…' : 'Проверить ещё раз' }}</Button>
            </section>
            <p v-if="!videos.data.length">Привяжите канал к проекту с готовым видео.</p>
            <template v-else-if="inbox || creator">
                <nav v-if="video && !directDetails" aria-label="Разделы публикации" class="delivery-tabs flex flex-wrap gap-2">
                    <Button
                        v-for="item in tiktokPublicationTabs"
                        :id="item === 'Статус' ? 'publication-status' : undefined"
                        :key="item"
                        :variant="tab === item ? 'secondary' : 'outline'"
                        :aria-current="tab === item ? 'page' : undefined"
                        @click="selectTab(item)"
                        >{{ item }}</Button
                    >
                </nav>
                <div v-if="tab === 'Предпросмотр' && video" class="preview-grid">
                    <EpisodePreview
                        :src="video.url"
                        label="Предпросмотр ролика"
                        player-label="Проигрыватель TikTok"
                        play-label="Воспроизвести ролик"
                        pause-label="Приостановить ролик"
                        class="tiktok-preview"
                    />
                    <section>
                        <h2>Предпросмотр</h2>
                        <p class="studio-row">
                            <strong>Видео</strong><span>{{ video.label }}</span>
                        </p>
                        <p class="studio-row">
                            <strong>Аккаунт</strong><span>{{ account }}</span>
                        </p>
                        <p class="studio-row">
                            <strong>Способ доставки</strong><span>{{ delivery }}</span>
                        </p>
                    </section>
                </div>
                <div v-else class="delivery-grid" :class="{ 'direct-details': directDetails }">
                    <video
                        v-if="directDetails && video"
                        :src="video.url"
                        playsinline
                        preload="auto"
                        aria-label="Предпросмотр ролика"
                        class="direct-video"
                        @loadedmetadata="readDuration"
                        @error="readDuration"
                    />
                    <section v-if="tab === 'Статус'" class="studio-panel grid gap-4" aria-live="polite">
                        <h2>Статус отправки</h2>
                        <strong>{{ video?.receipt ? statusLabels[video.receipt.status] : 'Видео ещё не отправлено' }}</strong>
                        <Button variant="outline" @click="router.reload({ only: ['videos'] })">Проверить статус</Button>
                    </section>
                    <form v-else id="tiktok-delivery-form" class="studio-panel grid min-w-0 content-start gap-4" @submit.prevent="submit">
                        <h2 v-if="directDetails">Аккаунт · {{ creator?.nickname ?? account }}</h2>
                        <template v-else>
                            <h2>Способ доставки</h2>
                            <p class="studio-row">
                                <strong>Аккаунт</strong><span>{{ account }}</span> · TikTok
                            </p>
                            <fieldset class="mode-switch">
                                <legend class="sr-only">Способ доставки</legend>
                                <label v-if="inbox"
                                    ><input v-model="form.mode" class="sr-only" type="radio" value="inbox" name="mode" />Во входящие</label
                                >
                                <label v-if="creator"
                                    ><input v-model="form.mode" class="sr-only" type="radio" value="direct" name="mode" />Прямая публикация</label
                                >
                            </fieldset>
                            <label
                                >Готовое видео
                                <select ref="videoSelect" v-model="form.media_id" required class="list-select">
                                    <option disabled :value="null">Выберите готовое видео</option>
                                    <option v-for="item in videos.data" :key="item.id" :value="item.id">{{ item.label }}</option>
                                </select></label
                            >
                            <nav class="flex gap-3" aria-label="Страницы видео">
                                <Link v-if="videos.prev_page_url" :href="videos.prev_page_url">Назад</Link
                                ><Link v-if="videos.next_page_url" :href="videos.next_page_url">Далее</Link>
                            </nav>

                            <video
                                v-if="video"
                                :key="video.id"
                                :src="video.url"
                                aria-label="Предпросмотр ролика"
                                controls
                                preload="metadata"
                                class="h-48 max-w-full rounded-md bg-black"
                                @loadedmetadata="readDuration"
                                @error="readDuration"
                            />
                            <div v-else>
                                <h2>Предпросмотр</h2>
                                <p>После выбора здесь появится ролик.</p>
                            </div>
                            <div v-if="tooLong && creator" class="grid gap-3">
                                <p class="studio-row"><strong>Лимит этого аккаунта</strong>{{ creator.duration }} с</p>
                                <div role="alert">
                                    <strong>Видео слишком длинное</strong>
                                    <p>Выберите сборку длительностью до {{ creator.duration }} секунд.</p>
                                </div>
                                <Button
                                    variant="outline"
                                    type="button"
                                    @click="
                                        form.media_id = null;
                                        videoSelect?.focus();
                                    "
                                    >Выбрать другое видео</Button
                                >
                            </div>
                        </template>
                        <p v-if="video && durationError && form.mode === 'direct'" role="alert">
                            Не удалось прочитать длительность ролика. Выберите другое видео.
                        </p>
                        <p v-if="video && form.mode === 'inbox'">
                            Откройте уведомление в TikTok, чтобы завершить редактирование и опубликовать ролик.
                        </p>
                        <template v-if="video && form.mode === 'direct' && creator && !tooLong">
                            <label>Подпись <textarea v-model="form.title" maxlength="2200" class="studio-field w-full" /></label>
                            <label
                                >Видимость
                                <select v-model="form.privacy" required class="list-select">
                                    <option disabled value="">Выберите из разрешённых автором</option>
                                    <option v-for="value in creator.privacy" :key="value" :value="value">
                                        {{ privacyLabels[value] ?? value }}
                                    </option>
                                </select></label
                            >
                            <details class="interactions">
                                <summary>
                                    Взаимодействия
                                    <span v-if="directDetails">{{ availableInteractions || 'Недоступны для этого аккаунта' }}</span>
                                </summary>
                                <div class="grid gap-2 py-2">
                                    <label><input v-model="form.comment" type="checkbox" :disabled="!creator.comment" /> Разрешить комментарии</label>
                                    <label><input v-model="form.duet" type="checkbox" :disabled="!creator.duet" /> Разрешить дуэты</label>
                                    <label><input v-model="form.stitch" type="checkbox" :disabled="!creator.stitch" /> Разрешить Stitch</label>
                                </div>
                            </details>
                            <label><input v-model="form.disclose" type="checkbox" /> Указать коммерческий характер публикации</label>
                            <template v-if="form.disclose"
                                ><label><input v-model="form.own_brand" type="checkbox" /> Собственный бренд</label
                                ><label><input v-model="form.partner" type="checkbox" /> Платное партнёрство</label></template
                            >
                            <label><input v-model="form.ai" type="checkbox" /> Создано с помощью ИИ</label>
                        </template>
                        <p v-if="video && consentChanged && !form.consent" role="status">
                            Подтвердите изменения. Предыдущее согласие относилось к другим настройкам.
                        </p>
                        <label v-if="video && !tooLong"
                            ><input v-model="form.consent" type="checkbox" required /> Подтверждаю отправку этого видео в {{ account }} с выбранными
                            настройками.</label
                        >
                        <p v-if="video && !tooLong && form.mode === 'direct'">
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
                        <Button v-if="directDetails" variant="outline" type="button" @click="directDetails = false"
                            >Вернуться к способу доставки</Button
                        >
                        <Button v-else type="submit" :disabled="!canSubmit">{{
                            form.mode === 'direct' ? 'Опубликовать в TikTok' : 'Отправить во входящие TikTok'
                        }}</Button>
                    </form>
                    <aside v-if="project && !directDetails" class="studio-panel hidden min-[1100px]:block">
                        <ProjectArtwork :name="project.name" :cover="project.cover" class="mb-3 aspect-[2.4] w-full rounded-lg" />
                        <h2>{{ project.name }}</h2>
                    </aside>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.receipt-screen {
    gap: 20px;
    padding-block-start: 18px;
}
@media (max-width: 767px) {
    .receipt-screen {
        padding-block-start: 12px;
    }
}
form > label:has(select),
form > label:has(textarea) {
    display: grid;
    gap: 6px;
    color: var(--muted-foreground);
    font-size: 14px;
}
form > label select,
form > label textarea {
    width: 100%;
    height: 48px;
    font-size: 16px;
    color: var(--foreground);
    background: var(--background);
}
form > button {
    justify-self: start;
}
.delivery-tabs button {
    height: 40px;
    padding-inline: 12px;
    border: 0;
    background: var(--muted);
    color: var(--muted-foreground);
    box-shadow: none;
}
form > button:disabled,
header > button:disabled {
    color: var(--muted-foreground);
    background: var(--muted);
    opacity: 1;
}
.delivery-tabs button[aria-current] {
    color: var(--ring);
    background: var(--sidebar-accent);
}
.preview-action,
.direct-action {
    min-height: 44px;
    font-weight: 700;
}
h2 {
    font-size: 20px;
    font-weight: 700;
}
.preview-grid section h2 {
    margin-bottom: 16px;
}
.preview-grid .studio-row {
    border-top: 0;
    border-bottom: 1px solid var(--border);
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1fr);
    text-align: left;
    padding-block: 14px;
}
.preview-grid .studio-row span {
    color: var(--muted-foreground);
    font-size: 14px;
}

.direct-video {
    width: 100%;
    height: min(55vw, 440px);
    object-fit: cover;
    border-radius: 10px;
    background: black;
}
@media (max-width: 767px) {
    .direct-publication {
        padding-top: 17px;
    }
    .direct-publication :deep(h1) {
        line-height: 40px;
    }
    .direct-video {
        height: 190px;
    }
}
.direct-details form h2 {
    margin-bottom: 0;
}
.direct-details .interactions summary {
    display: grid;
    gap: 6px;
    cursor: pointer;
    color: var(--muted-foreground);
    font-size: 14px;
}
.direct-details .interactions summary span {
    display: flex;
    align-items: center;
    min-height: 48px;
    padding-inline: 12px;
    border: 1px solid var(--input);
    border-radius: 8px;
    background: var(--background);
    color: var(--foreground);
    font-size: 16px;
}
@media (min-width: 768px) {
    .delivery-grid.direct-details {
        grid-template-columns: 30% minmax(0, 1fr);
        max-width: none;
    }
    .direct-details form {
        min-height: 608px;
    }
}

@media (min-width: 768px) and (max-width: 1099px) {
    .delivery-grid.direct-details {
        margin-top: 1px;
    }
    .direct-details form {
        row-gap: 15px;
    }
}
@media (min-width: 1100px) {
    .delivery-grid.direct-details {
        margin-top: -1px;
    }
}
@media (min-width: 1920px) {
    .delivery-grid.direct-details {
        grid-template-columns: 30% minmax(0, 820px);
    }
}
.mode-switch {
    display: flex;
    gap: 6px;
}
.mode-switch label {
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    cursor: pointer;
}
.mode-switch label:has(:checked) {
    color: var(--ring);
    background: color-mix(in srgb, var(--primary) 10%, var(--card));
}
.mode-switch label:focus-within {
    outline: 2px solid var(--ring);
}

.preview-grid,
.delivery-grid {
    display: grid;
    align-items: start;
    gap: 16px;
}
@media (min-width: 768px) {
    .preview-grid {
        grid-template-columns: min(34%, 390px) minmax(0, 1fr);
    }
}
@media (max-width: 1099px) {
    .preview-grid .studio-row {
        grid-template-columns: minmax(0, 1fr);
        gap: 3px;
        padding-block: 8px;
    }
}
@media (min-width: 1100px) {
    .delivery-grid {
        max-width: 1144px;
        grid-template-columns: minmax(0, 2.2fr) minmax(240px, 1fr);
    }
}
</style>
