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
import { computed } from 'vue';
import { useFormApi } from '@/lib/api/useFormApi';
import { useRoute } from '@/composables/useRoute';

const submitForm = useFormApi();

const route = useRoute();

const props = defineProps<{
    credentialsError: string;
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const invalidCredentials = computed(() => form.errors.email === props.credentialsError);

const submit = () => {
    submitForm(form, 'login.store', {
        preserveScroll: 'errors',
        onSuccess: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Вход" description="Для пользователей с доступом в Poster">
        <Head title="Войти" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <div class="grid gap-[19px]">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="studio@example.test"
                        :aria-invalid="!!form.errors.email"
                        :aria-describedby="invalidCredentials ? 'password-error' : form.errors.email ? 'email-error' : undefined"
                        @input="form.clearErrors('email')"
                    />
                    <InputError id="email-error" :message="invalidCredentials ? undefined : form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Пароль</Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        :aria-invalid="invalidCredentials || !!form.errors.password"
                        :aria-describedby="invalidCredentials || form.errors.password ? 'password-error' : undefined"
                        type="password"
                        required
                        autocomplete="current-password"
                        @input="invalidCredentials ? form.clearErrors('email', 'password') : form.clearErrors('password')"
                    />
                    <InputError id="password-error" :message="invalidCredentials ? 'Неверный email или пароль.' : form.errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model="form.remember" />
                        <span>Запомнить меня</span>
                    </Label>
                </div>

                <Button type="submit" class="-mt-px w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Войти
                </Button>
            </div>
        </form>
        <TextLink v-if="canResetPassword" :href="route('password.request')" class="mt-4 inline-flex items-center text-sm">Забыли пароль?</TextLink>
    </AuthBase>
</template>
