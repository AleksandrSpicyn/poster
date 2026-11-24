<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { WorkspacePageProps } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CircleCheck, LoaderCircle } from '@lucide/vue';
import { useFormApi } from '@/lib/api/useFormApi';
import { useRoute } from '@/composables/useRoute';

const submitForm = useFormApi();

const route = useRoute();
const page = usePage<WorkspacePageProps>();

defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    submitForm(form, 'verification.send');
};
</script>

<template>
    <AuthLayout
        :feedback="status === 'verification-link-sent'"
        title="Подтверждение почты"
        description="Откройте ссылку в письме, чтобы войти в студию"
    >
        <Head title="Подтверждение почты" />

        <div v-if="status === 'verification-link-sent'" role="status" class="account-message mb-4">
            <CircleCheck aria-hidden="true" /><strong>Письмо отправлено повторно</strong>
            <p>Проверьте входящие и папку «Спам».</p>
        </div>

        <div class="grid" :class="status === 'verification-link-sent' ? 'feedback-address mb-[18px] gap-1' : 'mb-[18px] gap-px'">
            <span class="text-muted-foreground text-sm">{{ status === 'verification-link-sent' ? 'Адрес' : 'Адрес для подтверждения' }}</span>
            <p class="border-input flex min-h-12 items-center rounded-lg border px-3.5 break-all">{{ page.props.auth.user.email }}</p>
        </div>

        <form :class="status === 'verification-link-sent' ? 'space-y-3 text-left' : 'space-y-6 text-center'" @submit.prevent="submit">
            <Button :variant="status === 'verification-link-sent' ? 'outline' : 'default'" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                {{ status === 'verification-link-sent' ? 'Отправить ещё раз' : 'Отправить повторно' }}
            </Button>

            <TextLink v-if="status !== 'verification-link-sent'" :href="route('logout')" method="post" as="button" class="block text-sm"
                >Выйти из аккаунта</TextLink
            >
        </form>
    </AuthLayout>
</template>
