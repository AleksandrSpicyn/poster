<script setup lang="ts">
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const route = useRoute();
</script>

<template>
    <AuthBase title="Регистрация закрыта" description="Доступ выдаёт оператор Poster">
        <Head title="Регистрация закрыта" />
        <Link :href="route('login')" class="access-cta -mt-px">Перейти ко входу</Link>
        <p class="text-muted-foreground mt-4 text-sm">Закрытый запуск</p>
    </AuthBase>
</template>
