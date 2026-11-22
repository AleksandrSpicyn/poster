<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const route = useRoute();
</script>

<template>
    <AuthBase title="Registration is closed" description="Contact your Poster operator to request access.">
        <Head title="Registration is closed" />
        <TextLink :href="route('login')">Log in</TextLink>
    </AuthBase>
</template>
