<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ drivers: Record<string, string> }>();
const route = useRoute();
const form = useForm({ name: '', driver: Object.keys(props.drivers)[0] ?? '', api_key: '' });
function submit() {
    form.post(route('clipping.store'), { onSuccess: () => form.reset('api_key') });
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
            { title: 'Выбор провайдера', href: route('clipping.create') },
        ]"
    >
        <Head title="Выбор провайдера" />
        <div class="workspace-page">
            <Heading title="Выбор провайдера" description="Новое подключение клиппинга" />
            <form class="studio-panel studio-form" @submit.prevent="submit">
                <Label for="driver">Драйвер</Label
                ><select v-model="form.driver" id="driver" class="list-select" required>
                    <option v-for="(label, key) in drivers" :key="key" :value="key">{{ label }}</option></select
                ><InputError :message="form.errors.driver" /> <Label for="connection-name">Название подключения</Label
                ><Input v-model="form.name" id="connection-name" required maxlength="255" /><InputError :message="form.errors.name" />
                <Label for="api-key">Ключ API</Label
                ><Input v-model="form.api_key" type="password" id="api-key" autocomplete="new-password" required maxlength="4096" /><InputError
                    :message="form.errors.api_key"
                />
                <Button type="submit" :disabled="form.processing">Сохранить подключение</Button>
            </form>
        </div>
    </AppLayout>
</template>
