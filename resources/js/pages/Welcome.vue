<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Fails" />

    <div class="flex min-h-screen flex-col items-center justify-between bg-white p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-white">
        <div class="flex w-full flex-1 items-center justify-center">
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
                    </template>
                </div>

                <div class="mt-10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Built with Laravel, Inertia, and Vue — fully automated for TikTok, Instagram, YouTube.
                    </p>
                </div>
            </div>
        </div>

        <footer class="mt-10 text-sm text-gray-500 dark:text-gray-400">
            <div class="flex flex-col items-center gap-2 sm:flex-row sm:gap-4">
                <Link :href="route('policy')" class="hover:underline">Privacy Policy</Link>
                <span class="hidden sm:inline">·</span>
                <Link :href="route('terms')" class="hover:underline">Terms of Service</Link>
            </div>
        </footer>
    </div>
</template>
