<script setup lang="ts">
import DisconnectChannelDialog from '@/components/channels/DisconnectChannelDialog.vue';
import TiktokChannelConnection from '@/components/channels/TiktokChannelConnection.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import ChannelIcon from '@/components/ChannelIcon.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProjectNotice from '@/components/projects/ProjectNotice.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ChannelType, SharedData } from '@/types';
import type { ManagedChannel } from '@/types/channels';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Link2, LoaderCircle, Save, Trash2 } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    channel: ManagedChannel | null;
    channelTypes: Record<string, string>;
    callbackUrl: string | null;
    requestedScopes?: string[];
}>();
const route = useRoute();
const page = usePage<SharedData>();
const title = computed(() => (props.channel ? props.channel.name : 'Новый канал'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Каналы', href: route('channels.index') },
    { title: title.value, href: props.channel ? route('channels.edit', props.channel.id) : route('channels.create') },
]);
const form = useForm({
    name: props.channel?.name ?? '',
    type: props.channel?.type ?? (Object.keys(props.channelTypes)[0] as ChannelType),
    client_id: props.channel?.client_id ?? '',
    client_secret: '',
    is_active: props.channel?.is_active ?? true,
});
const connection = useForm({});
const deletion = useForm({});
const deletionErrors = computed<Record<string, string | undefined>>(() => deletion.errors);
const requestError = ref('');
const replacingSecret = ref(false);
const deletingAllowed = computed(() => props.channel?.can_delete ?? false);
const oauthSupported = computed(() => ['youtube', 'instagram', 'tiktok'].includes(form.type));
const tab = ref('');
function readTab() {
    tab.value = window.location.hash.slice(1);
}
onMounted(() => {
    readTab();
    if (!tab.value && props.channel?.type === 'tiktok') {
        if (page.props.errors.oauth) tab.value = 'authorization';
        else if (page.flash.connectedChannelId === props.channel.id) tab.value = 'account';
    }
    window.addEventListener('hashchange', readTab);
});
onUnmounted(() => window.removeEventListener('hashchange', readTab));
const connectionPage = computed(() => props.channel?.type === 'tiktok' && ['platform', 'authorization', 'account'].includes(tab.value));
function revealInvalidField(event: Event) {
    const input = event.target;
    if (props.channel || !(input instanceof HTMLInputElement) || input.id !== 'channel-name' || tab.value !== 'oauth-settings') return;
    event.preventDefault();
    tab.value = 'channel-name';
    window.location.hash = 'channel-name';
    void nextTick(() => {
        input.focus();
        input.reportValidity();
    });
}
function submit() {
    requestError.value = '';
    form.submit(props.channel ? 'patch' : 'post', props.channel ? route('channels.update', props.channel.id) : route('channels.store'), {
        preserveScroll: 'errors',
        onSuccess: () => {
            form.reset('client_secret');
            replacingSecret.value = false;
        },
        onError: (errors) => {
            if (!props.channel) window.location.hash = errors.client_id || errors.client_secret ? 'oauth-settings' : 'channel-name';
        },
        onNetworkError: () => {
            requestError.value = 'Связь прервана. Повторите сохранение после подключения.';
            return false;
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            requestError.value = 'Не удалось сохранить канал. Повторите попытку.';
            return false;
        },
    });
}
function connect() {
    if (props.channel) connection.post(route('channels.connect', props.channel.id), { preserveScroll: true });
}
function disconnect() {
    if (props.channel) connection.delete(route('channels.disconnect', props.channel.id), { preserveScroll: true });
}
function remove() {
    if (props.channel) deletion.delete(route('channels.destroy', props.channel.id));
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />
        <TiktokChannelConnection
            v-if="channel && connectionPage"
            :channel="channel"
            :requested="requestedScopes ?? []"
            :tab="tab"
            :processing="connection.processing"
            :unsaved="form.isDirty"
            :error="page.props.errors.oauth"
            @authorize="connect"
            @save="submit"
            @disconnect="disconnect"
        />
        <div v-else-if="channel?.connected && (!tab || tab === 'overview')" class="workspace-page new-channel channel-overview">
            <ProjectNotice />
            <header class="channel-heading flex items-start justify-between gap-3">
                <Heading title="Настройки канала" :description="channel.name + ' / Подключение'" />
                <Button v-if="channel.type === 'tiktok'" as-child class="h-11"><a href="#authorization">Переподключить</a></Button>
                <Button
                    v-else-if="oauthSupported"
                    type="button"
                    class="h-11"
                    :disabled="connection.processing || !channel.oauth_configured"
                    @click="connect"
                    >Переподключить</Button
                >
            </header>
            <InputError :message="page.props.errors.oauth" />
            <div class="channel-layout grid items-start gap-4">
                <nav aria-label="Разделы канала" class="overview-nav">
                    <h2>В этом разделе</h2>
                    <a :href="channel.type === 'tiktok' ? '#platform' : '#overview'" aria-current="page">Подключение</a>
                    <a href="#connected-projects">Связанные проекты</a>
                    <DisconnectChannelDialog
                        :channel="channel"
                        :processing="connection.processing"
                        :error="page.props.errors.oauth"
                        label="Отключить аккаунт"
                        @confirm="disconnect"
                    />
                    <a href="#channel-name">Основное</a>
                </nav>
                <section class="studio-panel overview-fields">
                    <h2>Параметры</h2>
                    <dl>
                        <dt>Площадка</dt>
                        <dd>{{ channel.type_label }}</dd>
                        <dt>Владелец</dt>
                        <dd>{{ channel.account_name || channel.name }}</dd>
                        <dt>Права</dt>
                        <dd>
                            {{
                                channel.type === 'tiktok'
                                    ? [
                                          channel.tiktok_statistics && 'Статистика',
                                          channel.tiktok_inbox && 'Отправка во входящие',
                                          channel.tiktok_direct && 'Прямая публикация',
                                      ]
                                          .filter(Boolean)
                                          .join(' · ') || 'Нет действующих разрешений'
                                    : channel.permission_labels?.join(' · ') || 'Площадка не подтвердила список разрешений'
                            }}
                        </dd>
                        <dt>Проекты</dt>
                        <dd>
                            <a href="#connected-projects">{{
                                channel.projects?.map((project) => project.name).join(', ') || 'Нет связанных проектов'
                            }}</a>
                        </dd>
                    </dl>
                </section>
            </div>
        </div>
        <div v-else-if="channel && tab === 'connected-projects'" class="workspace-page linked-projects">
            <Heading :title="channel.name" description="Связанные проекты" />
            <div class="linked-layout">
                <section class="studio-panel">
                    <h2>Связанные проекты</h2>
                    <div class="linked-note" :class="{ connected: channel.connected }">
                        <strong>{{ channel.connected ? 'Аккаунт подключён' : 'Аккаунт не подключён' }}</strong>
                        <p>{{ channel.name }} · {{ channel.type_label }}</p>
                    </div>
                    <Link v-for="project in channel.projects" :key="project.id" :href="route('projects.edit', project.id)" class="studio-row">
                        <span>{{ project.name }}</span><span
                            >{{ project.content_mode === 'series' ? 'Сериал' : 'Короткие ролики' }} · владелец {{ page.props.auth.user.name }}</span
                        >
                    </Link>
                    <div class="linked-note">
                        <strong>Связи с проектами</strong>
                        <p>
                            {{ channel.projects?.length ? 'Каждая строка ссылается на этот же канал.' : 'Выберите этот канал в настройках проекта.' }}
                        </p>
                    </div>
                </section>
                <aside class="studio-panel">
                    <h3>{{ channel.name }}</h3>
                    <p>Студия</p>
                    <span>{{ page.props.auth.user.name }}</span>
                    <p>Владелец</p>
                    <span>{{ page.props.auth.user.name }}</span>
                </aside>
            </div>
        </div>
        <div v-else class="workspace-page" :class="{ 'new-channel': !channel }">
            <ProjectNotice />
            <div class="channel-heading flex items-start justify-between gap-3">
                <Heading
                    :title="channel ? 'Настройки канала' : 'Новый канал'"
                    :description="channel ? channel.name + ' / Подключение' : 'Подключение аккаунта для постинга'"
                />
                <Button type="submit" form="studio-channel" class="h-11" :disabled="form.processing">{{
                    channel ? 'Сохранить' : 'Создать канал'
                }}</Button>
            </div>
            <p v-if="requestError" role="alert" class="text-destructive text-sm">{{ requestError }}</p>
            <div class="channel-layout grid items-start gap-4 min-[1100px]:grid-cols-[min(28%,276px)_minmax(0,1fr)]">
                <nav
                    aria-label="Разделы канала"
                    class="min-[1100px]:bg-card flex flex-wrap gap-2 min-[1100px]:flex-col min-[1100px]:rounded-xl min-[1100px]:border min-[1100px]:p-5"
                >
                    <h2 class="hidden text-lg font-bold min-[1100px]:block">В этом разделе</h2>
                    <a
                        href="#channel-name"
                        class="rounded-lg p-3 text-sm"
                        :aria-current="!tab || tab === 'channel-name' ? 'page' : undefined"
                        :class="!tab || tab === 'channel-name' ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'bg-muted'"
                        >{{ channel ? 'Основное' : 'Создание' }}</a
                    >
                    <a
                        v-if="!channel"
                        href="#oauth-settings"
                        class="rounded-lg p-3 text-sm"
                        :aria-current="tab === 'oauth-settings' ? 'page' : undefined"
                        :class="tab === 'oauth-settings' ? 'bg-sidebar-accent text-sidebar-accent-foreground' : 'bg-muted'"
                        >Авторизация у площадки</a
                    >
                    <button
                        v-if="!channel"
                        class="bg-muted text-muted-foreground rounded-lg p-3 text-left text-sm"
                        disabled
                        title="Сначала создайте канал и подключите аккаунт"
                    >
                        Проверка прав
                    </button>
                    <a
                        v-if="channel"
                        :href="channel.type === 'tiktok' ? '#platform' : '#connection-heading'"
                        :aria-current="tab === 'connection-heading' ? 'page' : undefined"
                        class="bg-muted rounded-lg p-3 text-sm"
                        >Подключение</a
                    >
                    <a v-if="channel" href="#connected-projects" class="bg-muted rounded-lg p-3 text-sm">Проекты</a>
                </nav>
                <div class="min-w-0 space-y-4">
                    <form
                        id="studio-channel"
                        class="bg-card min-w-0 space-y-6 rounded-xl border p-5 sm:p-7"
                        :aria-busy="form.processing"
                        @invalid.capture="revealInvalidField"
                        @submit.prevent="submit"
                    >
                        <h2 v-if="!channel" class="text-xl font-bold">{{ tab === 'oauth-settings' ? 'Настройки приложения' : 'Параметры' }}</h2>
                        <div v-show="channel || tab !== 'oauth-settings'" class="grid gap-2">
                            <Label for="channel-name">Название</Label
                            ><Input
                                id="channel-name"
                                v-model="form.name"
                                required
                                maxlength="255"
                                autocomplete="off"
                                :disabled="form.processing"
                                :aria-invalid="!!form.errors.name"
                                placeholder="Например, Лесная почта"
                                class="h-11"
                            /><InputError :message="form.errors.name" />
                        </div>
                        <div v-show="channel || tab !== 'oauth-settings'" class="grid gap-2">
                            <Label for="channel-type">Площадка</Label
                            ><select
                                id="channel-type"
                                v-model="form.type"
                                :disabled="!!channel || form.processing"
                                class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                            >
                                <option v-for="(label, value) in channelTypes" :key="value" :value="value">{{ label }}</option></select
                            ><InputError :message="form.errors.type" />
                        </div>
                        <label v-if="channel" class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-4"
                            ><input
                                v-model="form.is_active"
                                type="checkbox"
                                :disabled="form.processing"
                                class="accent-primary mt-1 size-4 shrink-0"
                            /><span class="text-sm"
                                ><span class="font-medium">Канал активен</span
                                ><span class="text-muted-foreground mt-1 block"
                                    >При отключении публикации в этот канал приостанавливаются во всех проектах.</span
                                ></span
                            ></label
                        >
                        <div v-else-if="tab !== 'oauth-settings'" class="grid gap-2">
                            <Label for="channel-state">Состояние</Label>
                            <select id="channel-state" v-model="form.is_active" class="border-input bg-background h-12 rounded-md border px-3">
                                <option :value="true">Активен</option>
                                <option :value="false">Приостановлен</option>
                            </select>
                        </div>
                        <InputError :message="form.errors.is_active" />
                        <details
                            v-if="oauthSupported && (channel || tab === 'oauth-settings')"
                            class="space-y-4 border-t pt-5"
                            :open="!channel || !!form.errors.client_id || !!form.errors.client_secret || (!!channel && !channel.oauth_configured)"
                        >
                            <summary class="cursor-pointer text-sm font-semibold">Настройки приложения OAuth</summary>
                            <p class="text-muted-foreground text-sm">
                                Для общего приложения оставьте оба поля пустыми. Для собственного укажите данные
                                {{
                                    form.type === 'youtube'
                                        ? 'Google OAuth web application'
                                        : form.type === 'tiktok'
                                          ? 'TikTok Login Kit web app'
                                          : 'Instagram app with Instagram API login'
                                }}
                                и подключите аккаунт повторно.
                            </p>
                            <div class="grid gap-2">
                                <Label for="client-id">{{ form.type === 'tiktok' ? 'Client key' : 'Client ID / App ID' }}</Label><Input
                                    id="client-id"
                                    v-model="form.client_id"
                                    maxlength="255"
                                    autocomplete="off"
                                    :disabled="form.processing"
                                    :aria-invalid="!!form.errors.client_id"
                                /><InputError :message="form.errors.client_id" />
                            </div>
                            <div class="grid gap-2">
                                <template v-if="channel?.has_client_secret && !replacingSecret">
                                    <Label for="saved-secret">Сохранённый Client Secret</Label>
                                    <Input id="saved-secret" model-value="••••••••••••••••" readonly />
                                    <p class="text-muted-foreground text-sm">Секрет сохранён</p>
                                    <Button type="button" variant="outline" class="justify-self-start" @click="replacingSecret = true"
                                        >Заменить секрет</Button
                                    >
                                </template>
                                <template v-else>
                                    <Label for="client-secret">Client secret / App secret</Label
                                    ><Input
                                        id="client-secret"
                                        v-model="form.client_secret"
                                        type="password"
                                        maxlength="2048"
                                        autocomplete="new-password"
                                        :disabled="form.processing"
                                        :aria-invalid="!!form.errors.client_secret"
                                        :placeholder="channel?.has_client_secret ? 'Секрет сохранён' : 'Введите секрет приложения'"
                                        aria-describedby="secret-help"
                                    />
                                </template>
                                <p id="secret-help" class="text-muted-foreground text-xs">
                                    При прежнем ID оставьте поле пустым, чтобы сохранить действующий секрет.
                                </p>
                                <InputError :message="form.errors.client_secret" />
                            </div>
                            <div class="space-y-2">
                                <p class="text-sm font-medium">OAuth redirect URI</p>
                                <p class="bg-muted rounded-md p-3 font-mono text-xs leading-relaxed break-all select-all">
                                    {{
                                        callbackUrl ??
                                        (form.type === 'tiktok' ? route('channels.tiktok-redirect') : route('channels.provider-callback', form.type))
                                    }}
                                </p>
                                <p class="text-muted-foreground text-xs">Укажите этот адрес возврата в настройках приложения площадки.</p>
                            </div>
                        </details>
                        <div v-if="channel" class="flex flex-wrap justify-end gap-3 border-t pt-5">
                            <Button as-child variant="outline" class="h-11"><Link :href="route('channels.index')">Отмена</Link></Button
                            ><Button type="submit" :disabled="form.processing" class="h-11"
                                ><LoaderCircle v-if="form.processing" class="size-4 motion-safe:animate-spin" aria-hidden="true" /><Save
                                    v-else
                                    class="size-4"
                                    aria-hidden="true"
                                />{{ form.processing ? 'Сохранение…' : channel ? 'Сохранить изменения' : 'Создать канал' }}</Button
                            >
                        </div>
                    </form>
                    <aside v-if="channel" class="min-w-0 space-y-5">
                        <section class="bg-card space-y-4 rounded-xl border p-5" aria-labelledby="connection-heading">
                            <div class="flex items-center gap-3">
                                <ChannelIcon :channel="channel.type" />
                                <h2 id="connection-heading" class="font-semibold">Подключение аккаунта</h2>
                            </div>
                            <div>
                                <p class="text-sm font-medium">
                                    {{ channel.connected ? (channel.account_name ?? 'Подключённый аккаунт') : 'Аккаунт ещё не подключён' }}
                                </p>
                                <p class="text-muted-foreground mt-1 text-sm">
                                    {{
                                        !channel.is_active
                                            ? 'Канал приостановлен.'
                                            : channel.connected
                                              ? 'Аккаунт подключён к Poster.'
                                              : 'Авторизуйтесь под аккаунтом этого канала.'
                                    }}
                                </p>
                            </div>
                            <p v-if="channel.expires_at" class="text-muted-foreground text-xs">
                                Доступ действует до {{ new Date(channel.expires_at).toLocaleDateString() }}.
                            </p>
                            <p v-if="channel.type === 'tiktok' && channel.connected" class="text-sm">
                                {{
                                    [
                                        channel.tiktok_statistics && 'Статистика',
                                        channel.tiktok_inbox && 'Отправка во входящие',
                                        channel.tiktok_direct && 'Прямая публикация',
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || 'Нет действующих разрешений'
                                }}
                            </p>
                            <p v-if="channel.connected && channel.type !== 'tiktok'" class="text-muted-foreground text-sm">
                                Права: {{ channel.permission_labels?.join(' · ') || 'Площадка не подтвердила список разрешений' }}
                            </p>
                            <InputError :message="page.props.errors.oauth" />
                            <template v-if="oauthSupported">
                                <Button
                                    class="h-11 w-full"
                                    :disabled="connection.processing || form.isDirty || !channel.oauth_configured"
                                    @click="connect"
                                    ><LoaderCircle v-if="connection.processing" class="size-4 motion-safe:animate-spin" aria-hidden="true" /><Link2
                                        v-else
                                        class="size-4"
                                        aria-hidden="true"
                                    />{{ channel.connected ? 'Переподключить' : 'Подключить' }} {{ channel.type_label }}</Button
                                >
                                <p v-if="form.isDirty" class="text-muted-foreground text-xs">Сохраните изменения перед подключением.</p>
                                <p v-else-if="!channel.oauth_configured" class="text-muted-foreground text-xs">
                                    Сохраните данные приложения для подключения.
                                </p>
                                <p v-if="channel.type === 'instagram'" class="text-muted-foreground text-xs leading-relaxed">
                                    Нужен профессиональный аккаунт Instagram.
                                </p>
                            </template>
                            <DisconnectChannelDialog
                                v-if="channel.connected"
                                :channel="channel"
                                :processing="connection.processing"
                                :error="page.props.errors.oauth"
                                label="Отключить от Poster"
                                @confirm="disconnect"
                            />
                        </section>
                        <section class="bg-card space-y-3 rounded-xl border p-5">
                            <h2 tabindex="-1" id="connected-projects" class="text-sm font-semibold">Связанные проекты</h2>
                            <p v-if="!channel.projects?.length" class="text-muted-foreground text-sm">Выберите канал в настройках проекта.</p>
                            <Link
                                v-for="project in channel.projects"
                                :key="project.id"
                                :href="route('projects.edit', project.id)"
                                class="text-primary block py-2 text-sm break-words underline underline-offset-4"
                                >{{ project.name }}</Link
                            >
                        </section>
                        <section class="space-y-3 rounded-xl border p-5">
                            <h2 class="text-sm font-semibold">{{ deletingAllowed ? 'Удалить канал' : 'Удаление канала недоступно' }}</h2>
                            <template v-if="channel.projects?.length">
                                <p class="font-medium">Канал связан с проектами</p>
                                <p class="text-muted-foreground text-sm">
                                    Сначала уберите его из проектов {{ channel.projects.map((project) => '«' + project.name + '»').join(', ') }}.
                                </p>
                                <div class="studio-row">
                                    <span>Связанных проектов</span><span>{{ channel.projects.length }}</span>
                                </div>
                                <a class="text-primary inline-block py-2 text-sm underline" href="#connected-projects">Открыть связанные проекты</a>
                            </template>
                            <p v-else-if="!deletingAllowed" class="text-muted-foreground text-sm">
                                У канала есть история отправок. Вы можете приостановить публикации.
                            </p>
                            <InputError :message="deletionErrors.channel" />
                            <ConfirmationDialog
                                heading="Подтверждение удаления канала"
                                :title="'Удалить канал «' + channel.name + '»?'"
                                description="Канал больше не появится в списке получателей Poster."
                                action="Удалить канал"
                                destructive
                                :processing="deletion.processing"
                                :error="deletionErrors.channel"
                                @confirm="remove"
                            >
                                <template #trigger>
                                    <Button variant="destructive" class="h-11" :disabled="!deletingAllowed">
                                        <Trash2 class="size-4" aria-hidden="true" />Удалить канал
                                    </Button>
                                </template>
                                <div class="studio-row">
                                    <dt>Связанные проекты</dt>
                                    <dd>{{ channel.projects?.length ?? 0 }}</dd>
                                </div>
                                <div class="studio-row">
                                    <dt>Аккаунт площадки</dt>
                                    <dd>{{ channel.account_name || channel.name }} · {{ channel.type_label }}</dd>
                                </div>
                            </ConfirmationDialog>
                        </section>
                    </aside>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.channel-overview .channel-heading > [data-slot='button'] {
    font-weight: 700;
}
.overview-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: start;
}
.overview-nav h2 {
    display: none;
}
.overview-nav a,
.overview-nav :deep(button) {
    border: 0;
    border-radius: 8px;
    padding: 10px 12px;
    color: var(--muted-foreground);
    background: var(--muted);
    font-size: 14px;
    text-align: left;
}
.overview-nav [aria-current] {
    background: var(--sidebar-accent);
    color: var(--sidebar-accent-foreground);
    font-weight: 600;
}
.overview-fields {
    padding: 20px 24px 48px;
}
.overview-fields h2,
.overview-nav h2 {
    font-weight: 700;
    font-size: 20px;
}
.overview-fields dt {
    margin: 14px 0 6px;
    color: var(--muted-foreground);
    font-size: 14px;
}
.overview-fields dt:first-child {
    margin-top: 21px;
}
.overview-fields dd {
    padding: 10px 12px;
    border: 1px solid var(--input);
    border-radius: 8px;
    min-height: 48px;
    background: var(--background);
}
.overview-fields dd a {
    display: block;
}
@media (min-width: 1100px) {
    .overview-nav {
        display: grid;
        align-content: start;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
    }
    .new-channel nav.overview-nav {
        padding-inline: 8px;
    }
    .overview-nav h2 {
        display: block;
        padding: 0 14px 8px;
    }
    .overview-nav a:not([aria-current]),
    .overview-nav :deep(button) {
        background: transparent;
    }
    .overview-nav :deep(button) {
        justify-content: start;
        height: auto;
        font-weight: 400;
    }
}
@media (min-width: 768px) and (max-width: 1099px) {
    .overview-fields dt:first-child {
        margin-top: 24px;
    }
}
@media (max-width: 767px) {
    .overview-fields dt:first-child {
        margin-top: 27px;
    }
    .overview-fields {
        padding: 16px;
    }
}

.linked-projects {
    padding-top: 14px;
}
.linked-layout {
    display: grid;
    grid-template-columns: minmax(0, 2.36fr) minmax(0, 1fr);
    gap: 20px;
    align-items: start;
    width: min(100%, 1240px);
}
.linked-layout section {
    padding: 20px 24px 28px;
}
.linked-layout h2 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 20px;
}
.linked-layout aside {
    padding: 16px;
    min-height: 176px;
}
.linked-layout aside h3 {
    font-size: 18px;
    font-weight: 700;
}
.linked-layout aside p {
    color: var(--muted-foreground);
    font-size: 13px;
    margin-top: 12px;
}
.linked-layout .studio-row {
    min-height: 48px;
}
.linked-note {
    border-inline-start: 3px solid var(--muted-foreground);
    border-radius: 6px;
    background: var(--muted);
    padding: 10px 16px;
}
.linked-note:last-child {
    margin-top: 8px;
}
.linked-note.connected {
    border-color: var(--primary);
}
.linked-note.connected strong {
    color: var(--primary);
}
.linked-note p {
    color: var(--muted-foreground);
    margin-top: 4px;
    font-size: 14px;
}
@media (max-width: 1099px) {
    .linked-layout {
        grid-template-columns: minmax(0, 1fr);
    }
    .linked-layout aside {
        display: none;
    }
}
@media (max-width: 767px) {
    .linked-layout section {
        padding: 16px;
    }
}

.new-channel .channel-heading > [data-slot='button'],
.new-channel nav a[aria-current] {
    font-weight: 700;
}
.new-channel #studio-channel {
    padding: 24px;
    min-height: 378px;
}
.new-channel #studio-channel > * {
    margin: 0;
}
.new-channel #studio-channel > h2 {
    margin-bottom: 18px;
}
.new-channel #studio-channel > div.grid {
    gap: 6px;
    margin: 20px 0;
}
.new-channel #studio-channel :is(input, select) {
    background: var(--background);
    height: 48px;
    font-size: 16px;
}
.new-channel #studio-channel label {
    font-weight: 400;
    color: var(--muted-foreground);
}
@media (min-width: 1100px) {
    .new-channel .channel-layout {
        grid-template-columns: 276px minmax(0, 852px);
    }
    .new-channel nav {
        min-height: 240px;
        padding: 16px;
    }
    .new-channel nav .bg-muted {
        background: transparent;
    }
}
@media (min-width: 1920px) {
    .new-channel .channel-layout {
        grid-template-columns: 276px minmax(0, 820px);
    }
}
@media (max-width: 1099px) {
    .new-channel .channel-heading {
        flex-direction: column;
        gap: 16px;
    }
}
</style>
