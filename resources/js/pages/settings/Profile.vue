<script setup lang="ts">
import { useHashNavigation } from '@/composables/useHashNavigation';
import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem, SharedData, User } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useFormApi } from '@/lib/api/useFormApi';
import { useRoute } from '@/composables/useRoute';

const submitForm = useFormApi();

const route = useRoute();

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Профиль',
        href: route('profile.edit', undefined, false),
    },
];

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);

const form = useForm({
    name: user.value.name,
    email: user.value.email,
});

const section = ref('');
function readSection() {
    section.value = window.location.hash.slice(1);
    if (section.value === 'change-email' && user.value.email_verified_at) {
        void nextTick(() => document.getElementById('email')?.focus());
    }
}
useHashNavigation(readSection);
const emailNotice = computed(
    () => !user.value.email_verified_at && (section.value === 'change-email' || (!section.value && props.status === 'verification-link-sent')),
);
const submit = () => {
    submitForm(form, 'profile.update', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout
        :breadcrumbs="
            emailNotice || section === 'delete'
                ? [...breadcrumbs, { title: emailNotice ? 'Изменение почты' : 'Удаление аккаунта', href: page.url }]
                : breadcrumbs
        "
    >
        <Head title="Профиль" />

        <SettingsLayout title="Профиль" :description="emailNotice ? 'Изменение почты' : 'Личные данные / Учётная запись'">
            <template #action>
                <Button v-if="section !== 'delete' && !emailNotice" form="profile-form" type="submit" :disabled="form.processing">{{
                    form.processing ? 'Сохранение…' : 'Сохранить'
                }}</Button>
            </template>
            <section v-if="emailNotice" class="space-y-4">
                <HeadingSmall title="Изменение почты" />
                <div v-if="status === 'verification-link-sent'" class="profile-notice" role="status">
                    <strong>Адрес сохранён</strong>
                    <p>Письмо отправлено на новый адрес.</p>
                </div>
                <Label for="new-email">Новая почта</Label><Input id="new-email" :model-value="user.email" readonly />
                <div class="profile-notice warning">
                    <strong>Требуется подтверждение</strong>
                    <p>Откройте ссылку из письма, чтобы подтвердить адрес.</p>
                </div>
                <Link :href="route('verification.send')" method="post" as="button" class="underline">Отправить письмо повторно</Link>
            </section>
            <form v-else id="profile-form" class="account-form" @submit.prevent="submit">
                <h2>Параметры</h2>
                <div>
                    <Label for="name">Имя</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        required
                        autocomplete="name"
                        :aria-invalid="!!form.errors.name"
                        :aria-describedby="form.errors.name ? 'name-error' : undefined"
                    />
                    <InputError id="name-error" :message="form.errors.name" />
                </div>
                <div>
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                        :aria-invalid="!!form.errors.email"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                    />
                    <InputError id="email-error" :message="form.errors.email" />
                </div>
                <div>
                    <Label for="verified">Подтверждение</Label>
                    <Input id="verified" :model-value="user.email_verified_at ? 'Адрес подтверждён' : 'Адрес ещё не подтверждён'" readonly />
                </div>
                <Link
                    v-if="mustVerifyEmail && !user.email_verified_at"
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="mt-3 underline"
                    >Отправить письмо повторно</Link
                >
                <p v-if="form.recentlySuccessful" role="status" class="text-primary mt-3 text-sm">Изменения сохранены.</p>
            </form>
            <div class="mt-4 flex flex-wrap items-center gap-4">
                <Button v-if="!emailNotice" as-child variant="outline"><a href="#change-email">Изменить почту</a></Button>
                <a v-else href="#profile" class="underline">К личным данным</a>
                <DeleteUser />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

<style scoped>
.profile-notice {
    padding: 10px 14px;
    border-left: 3px solid var(--primary);
    border-radius: 5px;
    background: var(--muted);
}
.profile-notice.warning {
    border-color: var(--warning);
}
.profile-notice p {
    color: var(--muted-foreground);
    margin-top: 4px;
}
</style>
