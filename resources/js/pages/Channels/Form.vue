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
import { Link2, LoaderCircle, Trash2 } from '@lucide/vue';
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
const basic = computed(
    () => !['overview', 'platform', 'authorization', 'account', 'connection-heading', 'connected-projects', 'oauth-settings'].includes(tab.value),
);
const connectionPage = computed(
    () => props.channel?.type === 'tiktok' && ['overview', 'connection-heading', 'platform', 'authorization', 'account'].includes(tab.value),
);
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
        <div class="workspace-page">
            <ProjectNotice />
            <header class="flex flex-wrap items-center justify-between gap-3">
                <Heading :title="title" />
                <Button v-if="basic || !channel" type="submit" form="studio-channel" :disabled="form.processing">{{
                    channel ? 'Сохранить' : 'Создать канал'
                }}</Button>
            </header>
            <nav aria-label="Разделы канала" class="workspace-tabs">
                <a href="#channel-name" :aria-current="basic ? 'page' : undefined">Основное</a>
                <a
                    v-if="channel || oauthSupported"
                    :href="channel ? (channel.type === 'tiktok' ? '#platform' : '#connection-heading') : '#oauth-settings'"
                    :aria-current="!basic && tab !== 'connected-projects' ? 'page' : undefined"
                    >Подключение</a
                >
                <a v-if="channel" href="#connected-projects" :aria-current="tab === 'connected-projects' ? 'page' : undefined">Проекты</a>
            </nav>
            <InputError :message="requestError || (!connectionPage ? page.props.errors.oauth : undefined)" />
            <TiktokChannelConnection
                v-if="channel && connectionPage"
                :channel="channel"
                :requested="requestedScopes ?? []"
                :tab="['overview', 'connection-heading'].includes(tab) ? 'platform' : tab"
                :processing="connection.processing"
                :unsaved="form.isDirty"
                :error="page.props.errors.oauth"
                @authorize="connect"
                @save="submit"
                @disconnect="disconnect"
            />
            <section v-else-if="channel && tab === 'connected-projects'" class="studio-panel">
                <h2>Связанные проекты</h2>
                <Link v-for="project in channel.projects" :key="project.id" :href="route('projects.show', project.id)" class="studio-row">
                    <strong>{{ project.name }}</strong><span>Открыть проект</span>
                </Link>
                <p v-if="!channel.projects?.length">Чтобы связать проект, выберите этот канал в настройках проекта.</p>
            </section>
            <div v-else class="grid gap-4">
                <form
                    v-show="basic || !channel"
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
                        ><select id="channel-type" v-model="form.type" :disabled="!!channel || form.processing" class="list-select w-full">
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
                        <select id="channel-state" v-model="form.is_active" class="list-select w-full">
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
                </form>
                <aside v-if="channel" class="min-w-0 space-y-5">
                    <section v-if="!basic" class="bg-card space-y-4 rounded-xl border p-5" aria-labelledby="connection-heading">
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
                    <section v-if="basic" class="space-y-3 rounded-xl border p-5">
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
    </AppLayout>
</template>
