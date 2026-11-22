<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<SharedData>();

defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <AuthLayout title="Подтверждение почты" description="Откройте ссылку в письме, чтобы войти в студию">
        <Head title="Подтверждение почты" />

        <div v-if="status === 'verification-link-sent'" class="mb-4 text-center text-sm font-medium text-green-600">
            Новое письмо со ссылкой отправлено на вашу почту.
        </div>

        <div class="mb-[18px] grid gap-1">
            <span class="text-muted-foreground text-sm">Адрес для подтверждения</span>
            <p class="border-input flex min-h-12 items-center rounded-lg border px-3.5 break-all">{{ page.props.auth.user.email }}</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6 text-center">
            <Button :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Отправить повторно
            </Button>

            <TextLink :href="route('logout')" method="post" as="button" class="block text-sm">Выйти из аккаунта</TextLink>
        </form>
    </AuthLayout>
</template>
