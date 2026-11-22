<script setup lang="ts">
import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'ziggy-js';

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
onMounted(() => {
    readSection();
    window.addEventListener('hashchange', readSection);
});
onUnmounted(() => window.removeEventListener('hashchange', readSection));
const emailNotice = computed(
    () => !user.value.email_verified_at && (section.value === 'change-email' || (!section.value && props.status === 'verification-link-sent')),
);
const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Профиль" />

        <SettingsLayout title="Профиль" description="Личные данные / Учётная запись">
            <template #action>
                <Button v-if="section !== 'delete' && !emailNotice" form="profile-form" type="submit" :disabled="form.processing">{{
                    form.processing ? 'Сохранение…' : 'Сохранить'
                }}</Button>
            </template>
            <template #navigation>
                <a href="#profile" :aria-current="section !== 'delete' && section !== 'change-email' ? 'page' : undefined">Профиль</a>
                <a href="#change-email" :aria-current="section === 'change-email' ? 'page' : undefined">Изменение почты</a>
                <a href="#delete" :aria-current="section === 'delete' ? 'page' : undefined">Удаление учётной записи</a>
            </template>
            <DeleteUser v-if="section === 'delete'" />
            <section v-else-if="emailNotice" class="space-y-4">
                <HeadingSmall title="Изменение почты" />
                <p v-if="status === 'verification-link-sent'" role="status">Адрес сохранён. Письмо отправлено на новый адрес.</p>
                <Label for="new-email">Новая почта</Label><Input id="new-email" :model-value="user.email" readonly />
                <p>Требуется подтверждение. Откройте ссылку из письма, чтобы подтвердить адрес.</p>
                <Link :href="route('verification.send')" method="post" as="button" class="underline">Отправить письмо повторно</Link>
            </section>
            <form v-else id="profile-form" @submit.prevent="submit">
                <HeadingSmall title="Параметры" />
                <Label for="name">Имя</Label><Input id="name" v-model="form.name" required autocomplete="name" />
                <InputError :message="form.errors.name" />
                <Label for="email">Email</Label><Input id="email" v-model="form.email" type="email" required autocomplete="email" />
                <InputError :message="form.errors.email" />
                <Label for="verified">Подтверждение</Label
                ><Input id="verified" :model-value="user.email_verified_at ? 'Адрес подтверждён' : 'Адрес ещё не подтверждён'" readonly />
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
        </SettingsLayout>
    </AppLayout>
</template>
