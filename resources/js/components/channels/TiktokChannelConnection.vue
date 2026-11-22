<script setup lang="ts">
import DisconnectChannelDialog from '@/components/channels/DisconnectChannelDialog.vue';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ManagedChannel } from '@/types/channels';
import { computed } from 'vue';
const props = defineProps<{ channel: ManagedChannel; requested: string[]; tab: string; processing: boolean; unsaved: boolean; error?: string }>();
defineEmits<{ authorize: []; save: []; disconnect: [] }>();
const page = usePage<SharedData>();
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
    <div class="workspace-page connection" :class="{ detail: tab !== 'platform' }">
        <header>
            <Heading
                :title="tab === 'platform' ? 'TikTok в каналах' : tab === 'authorization' && !error ? 'Подключение канала' : 'Настройки канала'"
                :description="
                    tab === 'platform'
                        ? 'Мои каналы / TikTok / Подключение'
                        : tab === 'authorization'
                          ? error
                              ? 'Авторизация'
                              : 'Авторизация у площадки'
                          : undefined
                "
            />
            <Button v-if="tab === 'platform'" as-child class="h-11"><a href="#authorization">Авторизовать в TikTok</a></Button>
        </header>
        <nav v-if="tab === 'account'" aria-label="Разделы настройки канала" class="connection-tabs">
            <a href="#authorization">Авторизация у площадки</a><a href="#account" aria-current="page">Проверка прав</a
            ><a href="#connected-projects">Связанные проекты</a
            ><DisconnectChannelDialog
                v-if="channel.connected"
                :channel="channel"
                :processing="processing"
                :error="error"
                label="Отключить аккаунт"
                @confirm="$emit('disconnect')"
            />
        </nav>
        <div class="connection-layout">
            <nav v-if="tab === 'platform'" aria-label="Подключение канала">
                <h2>В этом разделе</h2>
                <a v-for="(label, key) in sections" :key="key" :href="'#' + key" :aria-current="tab === key ? 'page' : undefined">{{ label }}</a>
            </nav>
            <section v-if="tab === 'platform'" class="studio-panel connection-fields">
                <h2>Параметры</h2>
                <Label for="connection-platform">Площадка</Label><Input id="connection-platform" readonly model-value="TikTok" />
                <Label for="connection-name">Название</Label><Input id="connection-name" readonly :model-value="channel.name" />
                <Label for="connection-rights">Запрашиваемые права</Label
                ><Input id="connection-rights" readonly :model-value="requested.map((scope) => names[scope]).join(', ')" />
                <Label for="connection-state">Состояние</Label><Input id="connection-state" readonly :model-value="state" />
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
            <aside v-if="tab !== 'platform' && !error" class="studio-panel">
                <h2>{{ channel.name }}</h2>
                <small>Студия</small>
                <p>{{ page.props.auth.user.name }}</p>
                <small>Владелец</small>
                <p>{{ page.props.auth.user.name }}</p>
            </aside>
        </div>
    </div>
</template>
<style scoped>
.connection header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 16px;
}
.connection header > [data-slot="button"] {
    min-height: 44px;
    font-weight: 700;
}
.connection-layout {
    display: grid;
    gap: 16px;
    align-items: start;
}
.connection nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.connection nav h2 {
    display: none;
}
.connection nav a {
    padding: 10px 14px;
    border-radius: 8px;
    background: var(--muted);
    font-size: 14px;
    line-height: 20px;
}
.connection nav [aria-current] {
    color: var(--primary);
    background: var(--sidebar-accent);
}
.connection-fields {
    min-height: 468px;
}
.connection-fields h2 {
    margin-bottom: 24px;
}
.connection-fields label {
    display: block;
    margin: 12px 0 6px;
    line-height: 20px;
    color: var(--muted-foreground);
    font-weight: 400;
}
.connection-fields input {
    height: 48px;
    font-size: 16px;
    background: var(--background);
}
.notice {
    background: var(--muted);
    border-left: 3px solid var(--primary);
    padding: 12px;
    border-radius: 5px;
}
.notice p {
    color: var(--muted-foreground);
}
@media (min-width: 1100px) {
    .connection-layout {
        grid-template-columns: 276px minmax(0, 852px);
    }
    .connection nav {
        display: grid;
        align-content: start;
        gap: 6px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px 8px;
        min-height: 240px;
    }
    .connection nav h2 {
        display: block;
        font-size: 20px;
        font-weight: 700;
        margin: 0 14px 8px;
    }
    .connection-fields {
        padding-top: 18px;
    }
    .connection nav a[aria-current] {
        font-weight: 700;
    }
    .connection nav a:not([aria-current]) {
        color: var(--muted-foreground);
        background: transparent;
    }
}
@media (min-width: 1920px) {
    .connection-layout {
        grid-template-columns: 276px minmax(0, 820px);
    }
}
@media (max-width: 1099px) {
    .connection header {
        flex-direction: column;
    }
}

.connection.detail {
    gap: 20px;
    padding-top: 16px;
}
.connection-tabs {
    align-items: center;
}
.connection.detail .connection-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 0;
    border: 0;
    background: transparent;
    min-height: 0;
}
.connection.detail .connection-tabs a {
    background: var(--muted);
}
.connection.detail .connection-tabs [aria-current] {
    background: var(--sidebar-accent);
}
.connection.detail .connection-layout {
    gap: 20px;
    grid-template-columns: minmax(0, 2.36fr) minmax(0, 1fr);
}
.connection.detail .studio-row {
    display: grid;
    grid-template-columns: 36% 1fr;
    justify-content: start;
    font-size: 14px;
    border-top: 0;
    border-bottom: 1px solid var(--border);
}
.connection aside small {
    color: var(--muted-foreground);
}
.connection aside p {
    margin-bottom: 12px;
}
@media (min-width: 1920px) {
    .connection.detail .connection-layout {
        max-width: 1236px;
    }
}
@media (max-width: 1099px) {
    .connection.detail .connection-layout {
        grid-template-columns: 1fr;
    }
    .connection aside {
        display: none;
    }
}
</style>
