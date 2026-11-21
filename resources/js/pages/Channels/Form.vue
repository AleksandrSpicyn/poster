<script setup lang="ts">
import ChannelIcon from '@/components/ChannelIcon.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProjectNotice from '@/components/projects/ProjectNotice.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ChannelType, SharedData } from '@/types';
import type { ManagedChannel } from '@/types/channels';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Link2, LoaderCircle, Save, Trash2, Unplug } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ channel: ManagedChannel | null; channelTypes: Record<string, string>; callbackUrl: string | null }>();
const route = useRoute();
const page = usePage<SharedData>();
const title = computed(() => (props.channel ? props.channel.name : 'New channel'));
const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Channels', href: route('channels.index') },
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
const deleteOpen = ref(false);
const requestError = ref('');
const deletingAllowed = computed(() => props.channel?.can_delete ?? false);
const oauthSupported = computed(() => ['youtube', 'instagram'].includes(form.type));
function submit() {
    requestError.value = '';
    form.submit(props.channel ? 'patch' : 'post', props.channel ? route('channels.update', props.channel.id) : route('channels.store'), {
        preserveScroll: 'errors',
        onSuccess: () => form.reset('client_secret'),
        onNetworkError: () => {
            requestError.value = 'Connection lost. Your changes are still here; try again.';
            return false;
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            requestError.value = 'The channel could not be saved. Please try again.';
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
    if (props.channel)
        deletion.delete(route('channels.destroy', props.channel.id), {
            onSuccess: () => {
                deleteOpen.value = false;
            },
        });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />
        <div class="workspace-page max-w-6xl">
            <ProjectNotice />
            <div>
                <Link
                    :href="route('channels.index')"
                    class="text-muted-foreground hover:text-foreground mb-4 inline-flex min-h-11 items-center gap-2 text-sm"
                    ><ArrowLeft class="size-4" aria-hidden="true" />Back to channels</Link
                >
                <Heading
                    :title="channel ? 'Channel settings' : 'New channel'"
                    description="Keep one connection for each publishing account and share it across your projects."
                />
            </div>
            <p v-if="requestError" role="alert" class="text-destructive text-sm">{{ requestError }}</p>
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <form class="bg-card min-w-0 space-y-6 rounded-xl border p-5 sm:p-7" :aria-busy="form.processing" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="channel-name">Channel name</Label
                        ><Input
                            id="channel-name"
                            v-model="form.name"
                            required
                            maxlength="255"
                            autocomplete="off"
                            :disabled="form.processing"
                            :aria-invalid="!!form.errors.name"
                            placeholder="For example, Wildlife Shorts"
                            class="h-11"
                        /><InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="channel-type">Platform</Label
                        ><select
                            id="channel-type"
                            v-model="form.type"
                            :disabled="!!channel || form.processing"
                            class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                        >
                            <option v-for="(label, value) in channelTypes" :key="value" :value="value">{{ label }}</option></select
                        ><InputError :message="form.errors.type" />
                    </div>
                    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border p-4"
                        ><input
                            v-model="form.is_active"
                            type="checkbox"
                            :disabled="form.processing"
                            class="accent-primary mt-1 size-4 shrink-0"
                        /><span class="text-sm"
                            ><span class="font-medium">Channel enabled</span
                            ><span class="text-muted-foreground mt-1 block"
                                >Disabling pauses publication to this account in every connected project.</span
                            ></span
                        ></label
                    >
                    <InputError :message="form.errors.is_active" />
                    <details
                        v-if="oauthSupported"
                        class="space-y-4 border-t pt-5"
                        :open="!!form.errors.client_id || !!form.errors.client_secret || (!!channel && !channel.oauth_configured)"
                    >
                        <summary class="cursor-pointer text-sm font-semibold">OAuth application settings</summary>
                        <p class="text-muted-foreground text-sm">
                            Leave both fields empty to use the shared application. To override it, enter your
                            {{ form.type === 'youtube' ? 'Google OAuth web application' : 'Instagram app with Instagram API login' }} credentials.
                            Changing them requires reconnecting the account.
                        </p>
                        <div class="grid gap-2">
                            <Label for="client-id">Client ID / App ID</Label
                            ><Input
                                id="client-id"
                                v-model="form.client_id"
                                maxlength="255"
                                autocomplete="off"
                                :disabled="form.processing"
                                :aria-invalid="!!form.errors.client_id"
                            /><InputError :message="form.errors.client_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="client-secret">Client secret / App secret</Label
                            ><Input
                                id="client-secret"
                                v-model="form.client_secret"
                                type="password"
                                maxlength="2048"
                                autocomplete="new-password"
                                :disabled="form.processing"
                                :aria-invalid="!!form.errors.client_secret"
                                :placeholder="channel?.has_client_secret ? 'Saved — leave empty to keep' : 'Enter the application secret'"
                                aria-describedby="secret-help"
                            />
                            <p id="secret-help" class="text-muted-foreground text-xs">
                                The saved secret is never displayed. Leave empty to keep it when the app ID stays the same.
                            </p>
                            <InputError :message="form.errors.client_secret" />
                        </div>
                        <div class="space-y-2">
                            <p class="text-sm font-medium">OAuth redirect URI</p>
                            <p class="bg-muted rounded-md p-3 font-mono text-xs leading-relaxed break-all select-all">
                                {{ callbackUrl ?? route('channels.provider-callback', form.type) }}
                            </p>
                            <p class="text-muted-foreground text-xs">Register this URI once per provider application for all its channels.</p>
                        </div>
                    </details>
                    <div class="flex flex-wrap justify-end gap-3 border-t pt-5">
                        <Button as-child variant="outline" class="h-11"><Link :href="route('channels.index')">Cancel</Link></Button
                        ><Button type="submit" :disabled="form.processing" class="h-11"
                            ><LoaderCircle v-if="form.processing" class="size-4 motion-safe:animate-spin" aria-hidden="true" /><Save
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />{{ form.processing ? 'Saving…' : channel ? 'Save changes' : 'Create channel' }}</Button
                        >
                    </div>
                </form>
                <aside v-if="channel" class="min-w-0 space-y-5">
                    <section class="bg-card space-y-4 rounded-xl border p-5" aria-labelledby="connection-heading">
                        <div class="flex items-center gap-3">
                            <ChannelIcon :channel="channel.type" />
                            <h2 id="connection-heading" class="font-semibold">Account connection</h2>
                        </div>
                        <div>
                            <p class="text-sm font-medium">
                                {{ channel.connected ? (channel.account_name ?? 'Connected account') : 'No account connected' }}
                            </p>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {{
                                    !channel.is_active
                                        ? 'This channel is disabled.'
                                        : channel.connected
                                          ? 'Account connected to Poster.'
                                          : 'Connect the account assigned to this channel.'
                                }}
                            </p>
                        </div>
                        <p v-if="channel.expires_at" class="text-muted-foreground text-xs">
                            Access expires {{ new Date(channel.expires_at).toLocaleDateString() }}. Reconnect before it expires.
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
                                />{{ channel.connected ? 'Reconnect' : 'Connect' }} {{ channel.type_label }}</Button
                            >
                            <p v-if="form.isDirty" class="text-muted-foreground text-xs">Save your changes before connecting.</p>
                            <p v-else-if="!channel.oauth_configured" class="text-muted-foreground text-xs">
                                Save your application credentials to connect.
                            </p>
                            <p v-if="channel.type === 'instagram'" class="text-muted-foreground text-xs leading-relaxed">
                                Requires an Instagram Business or Creator account.
                            </p>
                        </template>
                        <Button v-if="channel.connected" variant="outline" class="h-11 w-full" :disabled="connection.processing" @click="disconnect"
                            ><Unplug class="size-4" aria-hidden="true" />Disconnect from Poster</Button
                        >
                    </section>
                    <section class="bg-card space-y-3 rounded-xl border p-5">
                        <h2 class="text-sm font-semibold">Connected projects</h2>
                        <p v-if="!channel.projects?.length" class="text-muted-foreground text-sm">
                            Select this channel in a project's settings to start using it.
                        </p>
                        <Link
                            v-for="project in channel.projects"
                            :key="project.id"
                            :href="route('projects.edit', project.id)"
                            class="text-primary block py-2 text-sm break-words underline underline-offset-4"
                            >{{ project.name }}</Link
                        >
                    </section>
                    <section class="space-y-3 rounded-xl border p-5">
                        <h2 class="text-sm font-semibold">Delete channel</h2>
                        <p class="text-muted-foreground text-sm">
                            Detach from all projects first. Channels with publication history must be disabled to keep their records.
                        </p>
                        <InputError :message="deletionErrors.channel" />
                        <Dialog v-model:open="deleteOpen"
                            ><DialogTrigger as-child
                                ><Button variant="destructive" class="h-11" :disabled="!deletingAllowed"
                                    ><Trash2 class="size-4" aria-hidden="true" />Delete channel</Button
                                ></DialogTrigger
                            ><DialogContent
                                ><DialogHeader
                                    ><DialogTitle>Delete {{ channel.name }}?</DialogTitle
                                    ><DialogDescription
                                        >This removes the channel and its saved connection permanently.</DialogDescription
                                    ></DialogHeader
                                ><InputError :message="deletionErrors.channel" /><DialogFooter
                                    ><DialogClose as-child><Button variant="outline" :disabled="deletion.processing">Cancel</Button></DialogClose
                                    ><Button variant="destructive" :disabled="deletion.processing" @click="remove">{{
                                        deletion.processing ? 'Deleting…' : 'Delete channel'
                                    }}</Button></DialogFooter
                                ></DialogContent
                            ></Dialog
                        >
                    </section>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
