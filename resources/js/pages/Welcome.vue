<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="AI Fails" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-white p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-white">
        <div class="text-center">
            <h1 class="mb-4 text-4xl font-extrabold tracking-tight">Welcome to <span class="text-red-600">AI Fails</span></h1>
            <p class="mx-auto mb-8 max-w-xl text-lg">
                A creative automation system that generates, edits and publishes viral short-form videos using cutting-edge AI tools.
            </p>

            <div class="flex justify-center gap-4">
                <Link
                    :href="route('dashboard')"
                    v-if="$page.props.auth.user"
                    class="rounded-lg bg-red-600 px-6 py-2 font-semibold text-white transition hover:bg-red-700"
                >
                    Go to Dashboard
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="rounded-lg border border-red-600 px-6 py-2 font-semibold text-red-600 transition hover:bg-red-600 hover:text-white"
                    >
                        Login
                    </Link>
                    <!--                    <Link-->
                    <!--                        :href="route('register')"-->
                    <!--                        class="px-6 py-2 border border-red-600 text-red-600 font-semibold rounded-lg hover:bg-red-600 hover:text-white transition"-->
                    <!--                    >-->
                    <!--                        Register-->
                    <!--                    </Link>-->
                </template>
            </div>

            <div class="mt-10">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Built with Laravel, Inertia, and Vue — fully automated for TikTok, Instagram, YouTube.
                </p>
            </div>
        </div>
    </div>
</template>
