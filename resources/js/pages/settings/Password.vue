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
    { title: 'Профиль', href: route('profile.edit') },
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
            <form id="password-form" class="account-form" @submit.prevent="updatePassword">
                <h2>Параметры</h2>

                <div class="grid">
                    <Label for="current_password">Текущий пароль</Label>
                    <Input
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        type="password"
                        :aria-invalid="!!form.errors.current_password"
                        :aria-describedby="form.errors.current_password ? 'current_password-error' : undefined"
                        autocomplete="current-password"
                        placeholder="Текущий пароль"
                    />
                    <InputError id="current_password-error" :message="form.errors.current_password" />
                </div>

                <div class="grid">
                    <Label for="password">Новый пароль</Label>
                    <Input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        :aria-invalid="!!form.errors.password"
                        :aria-describedby="form.errors.password ? 'password-error' : undefined"
                        autocomplete="new-password"
                        placeholder="Новый пароль"
                    />
                    <InputError id="password-error" :message="form.errors.password" />
                </div>

                <div class="grid">
                    <Label for="password_confirmation">Подтверждение</Label>
                    <Input
                        id="password_confirmation"
                        :aria-invalid="!!form.errors.password_confirmation"
                        :aria-describedby="form.errors.password_confirmation ? 'confirmation-error' : undefined"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        placeholder="Подтверждение"
                    />
                    <InputError id="confirmation-error" :message="form.errors.password_confirmation" />
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
        </SettingsLayout>
    </AppLayout>
</template>
