<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    connection: { id: number; name: string };
    sources: { id: string; title: string | null }[];
    projects: { id: number; name: string }[];
    accounts: { id: string; external_id: string; name: string; platform: string }[];
    channels: { id: number; name: string; type: string }[];
    mappings: Record<string, number>;
    readsAccounts: boolean;
    accountError: string | null;
}>();
const route = useRoute();
const choices = reactive<Record<string, string>>(Object.fromEntries(Object.entries(props.mappings).map(([key, id]) => [key, String(id)])));
const form = useForm({ external_id: '', project_id: null as number | null, accounts: [] as { id: string; channel_id: number | null }[] });
function submit() {
    form.accounts = props.accounts
        .filter((account) => choices[account.id])
        .map((account) => ({ id: account.id, channel_id: choices[account.id] === 'new' ? null : Number(choices[account.id]) }));
    form.post(route('clipping.preview', props.connection.id));
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
            { title: connection.name, href: route('clipping.sources', connection.id) },
        ]"
    >
        <Head title="Выбор источника" />
        <div class="workspace-page">
            <Heading title="Выбор источника" :description="connection.name" />
            <form class="studio-panel studio-form" @submit.prevent="submit">
                <Label for="external-project">Проект провайдера</Label>
                <select id="external-project" v-model="form.external_id" required class="list-select">
                    <option value="" disabled>Выберите проект</option>
                    <option v-for="source in sources" :key="source.id" :value="source.id">{{ source.title || 'Проект без названия' }}</option>
                </select>
                <InputError :message="form.errors.external_id" />
                <p v-if="!sources.length">Доступных проектов нет.</p>
                <Label for="local-project">Проект Poster</Label>
                <select id="local-project" v-model="form.project_id" class="list-select">
                    <option :value="null">Использовать сопоставление или создать новый</option>
                    <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                </select>
                <InputError :message="form.errors.project_id" />
                <fieldset v-if="accounts.length" class="space-y-3">
                    <legend>Сопоставление каналов</legend>
                    <label v-for="account in accounts" :key="account.id" class="grid gap-1">
                        {{ account.name || 'Аккаунт без названия' }} · {{ account.platform }}
                        <select v-model="choices[account.id]" class="list-select" :disabled="!!mappings[account.id]">
                            <option :value="undefined">Пропустить</option>
                            <option v-if="['youtube', 'instagram', 'tiktok', 'vk'].includes(account.platform)" value="new">
                                Создать канал без права отправки
                            </option>
                            <option
                                v-for="channel in channels.filter((item) => item.type === account.platform)"
                                :key="channel.id"
                                :value="String(channel.id)"
                            >
                                {{ channel.name }}
                            </option>
                        </select>
                    </label>
                </fieldset>
                <InputError :message="form.errors.accounts" />
                <Button :disabled="!form.external_id || form.processing">Предпросмотр импорта</Button>
            </form>
            <section class="studio-panel">
                <h2>Аккаунты провайдера</h2>
                <p v-if="accountError" role="status">{{ accountError }}</p>
                <p v-else-if="!readsAccounts">Драйвер не предоставляет список аккаунтов.</p>
                <p v-else-if="!accounts.length">Доступных аккаунтов нет.</p>
                <article v-for="account in accounts" :key="account.id" class="studio-row">
                    <span>{{ account.name || 'Аккаунт без названия' }}</span><span>{{ account.platform }}</span>
                </article>
            </section>
        </div>
    </AppLayout>
</template>
