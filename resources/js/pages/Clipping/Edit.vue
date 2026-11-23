<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    connection: { id: number; name: string; driver: string; catalog_checked_at: string | null; catalog_error: string | null };
    projects: { id: number; name: string; clips_count: number }[];
    scope: { import_projects: boolean; import_accounts: boolean; import_archives: boolean };
    drivers: Record<string, string>;
    access?: { projects: string; clips: string; source: string | null };
    supportsAccounts: boolean;
}>();
const route = useRoute();
const key = useForm({ api_key: '' });
const sync = useForm({});
const scope = useForm({ ...props.scope, import_projects: true, import_accounts: props.supportsAccounts && props.scope.import_accounts });
const editing = ref(false);
usePoll(15000, { only: ['connection', 'projects'] });
function updateKey() {
    key.patch(route('clipping.key.update', props.connection.id), {
        onSuccess: () => {
            key.reset();
            editing.value = false;
        },
    });
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
            { title: connection.name, href: route('clipping.edit', connection.id) },
        ]"
    >
        <Head :title="connection.name" />
        <div class="workspace-page">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    :title="connection.name"
                    :description="drivers[connection.driver] !== connection.name ? drivers[connection.driver] : undefined"
                />
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child
                        ><Link :href="route('clipping.history', { connection: connection.id })">История импорта</Link></Button
                    >
                    <Button
                        v-if="props.scope.import_projects"
                        :disabled="sync.processing"
                        @click="sync.post(route('clipping.sync', connection.id), { preserveScroll: true })"
                        >Обновить сейчас</Button
                    >
                    <Button v-else :disabled="scope.processing" @click="scope.patch(route('clipping.scope.update', connection.id))"
                        >Включить автоимпорт</Button
                    >
                </div>
            </header>
            <p v-if="access" role="status">
                {{
                    access.projects === 'available' && access.clips === 'available'
                        ? 'Площадка подтвердила доступ к проектам и клипам.'
                        : 'Проверьте ключ и права на чтение проектов и клипов.'
                }}
            </p>
            <p v-if="connection.catalog_error" role="alert" class="text-destructive">{{ connection.catalog_error }}</p>
            <p v-if="sync.recentlySuccessful || key.recentlySuccessful" role="status">
                Запрос принят. Ход обновления можно посмотреть в истории импорта.
            </p>
            <section class="studio-panel">
                <h2>Импортированные проекты</h2>
                <p v-if="!props.scope.import_projects">Автоимпорт выключен.</p>
                <p v-else-if="!projects.length" class="text-muted-foreground">
                    {{
                        connection.catalog_error
                            ? 'Проектов пока нет.'
                            : connection.catalog_checked_at
                              ? 'На площадке пока нет проектов. Poster проверит её снова через час.'
                              : 'Poster проверяет площадку. Здесь появятся проекты и их клипы.'
                    }}
                </p>
                <ul class="divide-y">
                    <li v-for="item in projects" :key="item.id">
                        <Link class="flex items-center justify-between gap-3 py-3" :href="route('projects.show', item.id)"
                            ><span>{{ item.name }}</span
                            ><small class="text-muted-foreground">Клипов: {{ item.clips_count }}</small></Link
                        >
                    </li>
                </ul>
            </section>
            <div>
                <Button v-if="!editing" variant="outline" @click="editing = true">Заменить ключ API</Button>
                <form v-else class="studio-panel studio-form max-w-xl" @submit.prevent="updateKey">
                    <Label for="new-key">Новый ключ API</Label
                    ><Input id="new-key" v-model="key.api_key" type="password" autocomplete="new-password" required maxlength="4096" /><InputError
                        :message="key.errors.api_key"
                    />
                    <div class="flex gap-2">
                        <Button :disabled="key.processing">Сохранить ключ</Button
                        ><Button
                            type="button"
                            variant="outline"
                            @click="
                                editing = false;
                                key.reset();
                                key.clearErrors();
                            "
                            >Отмена</Button
                        >
                    </div>
                </form>
            </div>
        </div></AppLayout
    >
</template>
