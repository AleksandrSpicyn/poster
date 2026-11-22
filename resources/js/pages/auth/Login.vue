<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Вход" description="Для пользователей с доступом в Poster">
        <Head title="Войти" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-[18px]">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" type="email" required autofocus autocomplete="email" v-model="form.email" placeholder="studio@example.test" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Пароль</Label>
                    <Input id="password" type="password" required autocomplete="current-password" v-model="form.password" />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model="form.remember" />
                        <span>Запомнить меня</span>
                    </Label>
                </div>

                <Button type="submit" class="w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Войти
                </Button>
            </div>
        </form>
        <TextLink v-if="canResetPassword" :href="route('password.request')" class="mt-4 inline-flex items-center text-sm">Забыли пароль?</TextLink>
    </AuthBase>
</template>
