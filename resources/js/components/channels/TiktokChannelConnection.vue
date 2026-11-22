<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ManagedChannel } from '@/types/channels';
import { computed } from 'vue';
const props = defineProps<{ channel: ManagedChannel; requested: string[]; tab: string; processing: boolean; unsaved: boolean; error?: string }>();
defineEmits<{ authorize: [] }>();
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
    <div class="workspace-page connection">
        <header>
            <Heading
                title="TikTok в каналах"
                :description="tab === 'platform' ? 'Мои каналы / TikTok / Подключение' : sections[tab as keyof typeof sections]"
            />
            <Button v-if="tab === 'platform'" as-child class="h-11"><a href="#authorization">Авторизовать в TikTok</a></Button>
        </header>
        <div class="connection-layout">
            <nav aria-label="Подключение канала">
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
                <h2>Авторизация</h2>
                <div v-if="error" role="alert" class="notice">
                    <strong>Доступ не предоставлен</strong>
                    <p>{{ error }}</p>
                </div>
                <div class="studio-row">
                    <span>Канал</span><strong>{{ channel.name }} · TikTok</strong>
                </div>
                <div class="notice">
                    <strong>Авторизация у площадки</strong>
                    <p>Войдите в нужный аккаунт и выберите разрешения. После подтверждения вы вернётесь в Poster.</p>
                </div>
                <div class="studio-row">
                    <span>Запрошено</span><strong>{{ requested.map((scope) => names[scope]).join(', ') }}</strong>
                </div>
                <Button :disabled="!canAuthorize" @click="$emit('authorize')">{{ error ? 'Подключить повторно' : 'Перейти к авторизации' }}</Button>
                <a v-if="unsaved" class="block underline" href="#channel-name">Сохраните изменения в настройках перед подключением.</a>
                <a v-else-if="!channel.oauth_configured" class="block underline" href="#channel-name">Указать данные приложения</a>
            </section>
            <section v-else class="studio-panel space-y-3">
                <h2>Проверка аккаунта</h2>
                <div class="notice">
                    <strong>{{ state }}</strong>
                    <p>{{ channel.account_name ?? channel.name }} · TikTok</p>
                </div>
                <div v-for="scope in requested.filter((item) => item !== 'user.info.basic')" :key="scope" class="studio-row">
                    <strong>{{ names[scope] }}</strong><span>{{ rights[scope] ? 'Доступно' : 'Нет доступа' }}</span>
                </div>
                <Button v-if="partial || !channel.connected" as-child><a href="#authorization">Повторить авторизацию</a></Button>
                <Button v-else as-child><a href="#channel-name">Вернуться в настройки</a></Button>
            </section>
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
        padding: 16px;
        min-height: 240px;
    }
    .connection nav h2 {
        display: block;
        font-size: 20px;
        font-weight: 700;
        margin: 0 8px 8px;
    }
    .connection nav a:not([aria-current]) {
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
</style>
