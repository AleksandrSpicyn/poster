<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { CircleX, LoaderCircle } from '@lucide/vue';
import { nextTick, ref, useTemplateRef } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();

interface Props {
    token: string;
    email: string | null;
}

const props = defineProps<Props>();

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
});

const mismatch = ref(false);
const confirmation = useTemplateRef<InstanceType<typeof Input>>('confirmation');
const submit = () => {
    mismatch.value = form.password !== form.password_confirmation;
    if (mismatch.value) {
        void nextTick(() => confirmation.value?.focus());
        return;
    }
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
            <div class="grid gap-[19px]">
                <div class="grid gap-2">
                    <Label for="email">{{ mismatch ? 'Почта' : 'Email' }}</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        class="mt-1 block w-full"
                        :readonly="!!props.email"
                        :autofocus="!props.email"
                        :aria-invalid="!!form.errors.email"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                        required
                    />
                    <InputError id="email-error" :message="form.errors.email" class="mt-2" />
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
                        :autofocus="!!props.email"
                        :aria-invalid="!!form.errors.password"
                        :aria-describedby="form.errors.password ? 'password-error' : undefined"
                        required
                    />
                    <InputError id="password-error" :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Повторите пароль</Label>
                    <Input
                        ref="confirmation"
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        @input="mismatch = false"
                        :aria-invalid="mismatch || !!form.errors.password_confirmation"
                        :aria-describedby="mismatch ? 'password-mismatch' : form.errors.password_confirmation ? 'confirmation-error' : undefined"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError id="confirmation-error" :message="form.errors.password_confirmation" />
                </div>

                <div v-if="mismatch" id="password-mismatch" role="alert" class="account-message">
                    <CircleX aria-hidden="true" /><strong>Пароли не совпадают</strong>
                    <p>Введите одинаковое значение в обоих полях.</p>
                </div>
                <Button type="submit" class="-mt-px w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Сохранить пароль
                </Button>
            </div>
        </form>
        <TextLink v-if="form.errors.email" :href="route('password.request')" class="mt-4 inline-flex">Запросить новую ссылку</TextLink>
        <p v-else-if="!mismatch" class="text-muted-foreground mt-4 text-sm">Ссылка из письма</p>
    </AuthLayout>
</template>
