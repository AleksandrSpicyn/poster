<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Безопасность',
        href: route('password.edit', undefined, false),
    },
];

const passwordInput = ref<InstanceType<typeof Input> | null>(null);
const currentPasswordInput = ref<InstanceType<typeof Input> | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }

            if (errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Безопасность" />

        <SettingsLayout title="Безопасность" description="Учётная запись / Пароль">
            <template #action
                ><Button form="password-form" type="submit" :disabled="form.processing">{{
                    form.processing ? 'Сохранение…' : 'Обновить пароль'
                }}</Button></template
            >
            <div class="space-y-6">
                <h2>Параметры</h2>

                <form id="password-form" @submit.prevent="updatePassword">
                    <div class="grid">
                        <Label for="current_password">Текущий пароль</Label>
                        <Input
                            id="current_password"
                            ref="currentPasswordInput"
                            v-model="form.current_password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            placeholder="Текущий пароль"
                        />
                        <InputError :message="form.errors.current_password" />
                    </div>

                    <div class="grid">
                        <Label for="password">Новый пароль</Label>
                        <Input
                            id="password"
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Новый пароль"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid">
                        <Label for="password_confirmation">Подтверждение</Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            placeholder="Подтверждение"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" role="status" class="text-primary text-sm">Пароль обновлён.</p>
                        </Transition>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
