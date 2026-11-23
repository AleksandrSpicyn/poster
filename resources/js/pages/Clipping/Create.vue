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
const form = useForm({ name: Object.values(props.drivers)[0] ?? '', driver: Object.keys(props.drivers)[0] ?? '', api_key: '' });
function submit() {
    form.post(route('clipping.store'), { onSuccess: () => form.reset('api_key') });
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
            { title: 'Подключить площадку', href: route('clipping.create') },
        ]"
    >
        <Head title="Подключить площадку" />
        <div class="workspace-page">
            <Heading
                :title="Object.keys(drivers).length === 1 ? 'Подключить ' + drivers[form.driver] : 'Подключить площадку'"
                description="После подключения Poster создаст проекты и загрузит клипы. При следующих обновлениях новые клипы попадут в те же проекты."
            />
            <form class="studio-panel studio-form" @submit.prevent="submit">
                <Label v-if="Object.keys(drivers).length > 1" for="driver">Площадка</Label
                ><select v-if="Object.keys(drivers).length > 1" v-model="form.driver" id="driver" class="list-select" required>
                    <option v-for="(label, key) in drivers" :key="key" :value="key">{{ label }}</option></select
                ><InputError :message="form.errors.driver" /> <Label for="connection-name">Название источника</Label
                ><Input v-model="form.name" id="connection-name" required maxlength="255" /><InputError :message="form.errors.name" />
                <Label for="api-key">Ключ API</Label
                ><Input v-model="form.api_key" type="password" id="api-key" autocomplete="new-password" required maxlength="4096" /><InputError
                    :message="form.errors.api_key"
                />
                <Button type="submit" :disabled="form.processing">Подключить и импортировать</Button>
            </form>
        </div>
    </AppLayout>
</template>
