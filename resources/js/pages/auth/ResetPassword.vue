<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { ref } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();

interface Props {
    token: string;
    email: string;
}

const props = defineProps<Props>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const mismatch = ref(false);
const submit = () => {
    mismatch.value = form.password !== form.password_confirmation;
    if (mismatch.value) return;
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AuthLayout :feedback="mismatch" title="Новый пароль" description="Для учётной записи студии">
        <Head title="Новый пароль" />

        <form @submit.prevent="submit">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Почта</Label>
                    <Input id="email" type="email" name="email" autocomplete="email" v-model="form.email" class="mt-1 block w-full" readonly />
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Новый пароль</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        v-model="form.password"
                        @input="mismatch = false"
                        class="mt-1 block w-full"
                        autofocus
                        required
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Повторите пароль</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        @input="mismatch = false"
                        :aria-invalid="mismatch || !!form.errors.password_confirmation"
                        :aria-describedby="mismatch ? 'password-mismatch' : undefined"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <div v-if="mismatch" id="password-mismatch" role="alert" class="account-message">
                    <strong>Пароли не совпадают</strong>
                    <p>Введите одинаковое значение в обоих полях.</p>
                </div>
                <Button type="submit" class="mt-4 w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Сохранить пароль
                </Button>
            </div>
        </form>
    </AuthLayout>
</template>
