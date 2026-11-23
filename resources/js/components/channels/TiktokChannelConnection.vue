<script setup lang="ts">
import DisconnectChannelDialog from '@/components/channels/DisconnectChannelDialog.vue';
import { Button } from '@/components/ui/button';
import type { ManagedChannel } from '@/types/channels';
import { computed } from 'vue';
const props = defineProps<{ channel: ManagedChannel; requested: string[]; tab: string; processing: boolean; unsaved: boolean; error?: string }>();
defineEmits<{ authorize: []; save: []; disconnect: [] }>();
const names: Record<string, string> = {
    'user.info.basic': 'Профиль',
    'video.list': 'Просмотр видео',
    'video.upload': 'Отправка во входящие',
    'video.publish': 'Прямая публикация',
};
const sections = { platform: 'Площадка', authorization: 'Авторизация', account: 'Проверка аккаунта' };
const rights = computed<Record<string, boolean>>(() => ({
    'user.info.basic': props.channel.connected,
    'video.list': props.channel.tiktok_statistics,
    'video.upload': props.channel.tiktok_inbox,
    'video.publish': props.channel.tiktok_direct,
}));
const partial = computed(() => props.channel.connected && props.requested.some((scope) => !rights.value[scope]));
const state = computed(() =>
    !props.channel.is_active
        ? 'Канал приостановлен'
        : !props.channel.connected
          ? 'Ожидает авторизации'
          : partial.value
            ? 'Подключено с ограничениями'
            : 'Аккаунт подтверждён',
);
const canAuthorize = computed(() => !props.processing && !props.unsaved && props.channel.oauth_configured);
</script>
<template>
    <div class="grid gap-4">
        <nav class="workspace-tabs" aria-label="Этапы подключения TikTok">
            <a v-for="(label, key) in sections" :key="key" :href="'#' + key" :aria-current="tab === key ? 'page' : undefined">{{ label }}</a>
        </nav>
        <div class="grid gap-4">
            <section v-if="tab === 'platform'" class="studio-panel connection-fields">
                <h2>Параметры</h2>
                <p class="studio-row">
                    <span>Аккаунт</span><strong>{{ channel.name }} · TikTok</strong>
                </p>
                <p class="studio-row">
                    <span>Запрашиваемые права</span><span>{{ requested.map((scope) => names[scope]).join(', ') }}</span>
                </p>
                <p class="studio-row">
                    <span>Состояние</span><strong>{{ state }}</strong>
                </p>
                <Button as-child><a href="#authorization">Авторизовать в TikTok</a></Button>
            </section>
            <section v-else-if="tab === 'authorization'" class="studio-panel space-y-4">
                <h2>{{ error ? 'Авторизация' : 'Авторизация у площадки' }}</h2>
                <div v-if="error" role="alert" class="notice">
                    <strong>Доступ не предоставлен</strong>
                    <p>{{ error }}</p>
                </div>
                <div class="studio-row">
                    <span>{{ error ? 'Аккаунт' : 'Канал' }}</span><strong>{{ channel.name }} · TikTok</strong>
                </div>
                <div v-if="!error" class="notice">
                    <strong>Авторизация у площадки</strong>
                    <p>Войдите в нужный аккаунт и выберите разрешения. После подтверждения вы вернётесь в Poster.</p>
                </div>
                <div class="studio-row">
                    <span>{{ error ? 'Соединение' : 'Запрошено' }}</span><strong>{{
                        error ? (channel.connected ? state : 'Отключено · права не получены') : requested.map((scope) => names[scope]).join(', ')
                    }}</strong>
                </div>
                <Button :disabled="!canAuthorize" @click="$emit('authorize')">{{ error ? 'Подключить повторно' : 'Перейти к авторизации' }}</Button>
                <a v-if="unsaved" class="block underline" href="#channel-name">Сохраните изменения в настройках перед подключением.</a>
                <a v-else-if="!channel.oauth_configured" class="block underline" href="#channel-name">Указать данные приложения</a>
            </section>
            <section v-else class="studio-panel space-y-3">
                <h2>Предоставленные права</h2>
                <div class="notice">
                    <strong>{{ state }}</strong>
                    <p>{{ channel.account_name ?? channel.name }} · TikTok</p>
                </div>
                <div v-for="scope in requested.filter((item) => item !== 'user.info.basic')" :key="scope" class="studio-row">
                    <strong>{{ names[scope] }}</strong><span>{{ rights[scope] ? 'Доступно' : 'Нет доступа' }}</span>
                </div>
                <Button v-if="partial || !channel.connected" as-child><a href="#authorization">Повторить авторизацию</a></Button>
                <Button v-else :disabled="processing" @click="$emit('save')">Сохранить канал</Button>
            </section>
            <DisconnectChannelDialog
                v-if="channel.connected"
                :channel="channel"
                :processing="processing"
                :error="error"
                label="Отключить аккаунт"
                @confirm="$emit('disconnect')"
            />
        </div>
    </div>
</template>
