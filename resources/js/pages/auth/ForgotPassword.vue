<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { CircleCheck, LoaderCircle } from '@lucide/vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout
        :feedback="!!status"
        :title="status ? 'Восстановление доступа' : 'Восстановление пароля'"
        description="Отправим ссылку на указанный адрес"
    >
        <Head title="Восстановление пароля" />

        <div class="space-y-6">
            <form @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="email">{{ status ? 'Почта' : 'Email' }}</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        required
                        v-model="form.email"
                        :readonly="!!status"
                        autofocus
                        placeholder="studio@example.test"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div v-if="status" role="status" class="account-message mt-4">
                    <CircleCheck aria-hidden="true" /><strong>Запрос принят</strong>
                    <p>Если этот адрес связан с аккаунтом, на него придёт письмо со ссылкой.</p>
                </div>
                <div v-else class="mt-[18px] mb-6 flex items-center justify-start md:mt-[17px]">
                    <Button class="w-full" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        Отправить ссылку
                    </Button>
                </div>
            </form>

            <div class="text-muted-foreground text-sm">
                <TextLink :href="route('login')">Вернуться ко входу</TextLink>
                <p v-if="status" class="mt-1">Введите пароль после восстановления</p>
            </div>
        </div>
    </AuthLayout>
</template>
