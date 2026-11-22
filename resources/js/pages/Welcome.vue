<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import type { HomepageProps } from '@/types/homepage';

const page = usePage<SharedData>();

defineProps<{ homepage: HomepageProps; channelsUrl: string }>();
</script>

<template>
    <div class="min-h-screen bg-stone-50 text-slate-950">
        <Head title="Poster · Short video workspace">
            <meta name="description" :content="homepage.hero.summary" />
        </Head>
        <header class="border-b border-slate-200">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-5">
                <span class="text-2xl font-semibold tracking-tight">{{ homepage.hero.kicker }}</span>
                <nav aria-label="Main navigation" class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    <a v-for="item in homepage.navigation" :key="item.href" :href="item.href" class="hover:underline">{{ item.label }}</a>
                    <Link :href="page.props.auth.user ? route('dashboard') : route('login')" class="font-semibold">
                        {{ page.props.auth.user ? 'Dashboard' : 'Log in' }}
                    </Link>
                </nav>
            </div>
        </header>
        <main>
            <section class="bg-slate-950 text-white">
                <div class="mx-auto max-w-6xl px-6 py-16 md:py-24">
                    <h1 class="max-w-3xl text-5xl leading-tight font-semibold tracking-tight md:text-7xl">{{ homepage.hero.title }}</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-200">{{ homepage.hero.summary }}</p>
                    <div class="mt-8 flex flex-wrap items-center gap-6">
                        <a
                            :href="homepage.hero.primary_cta.href"
                            class="rounded-lg bg-teal-200 px-5 py-3 font-semibold text-slate-950 hover:bg-teal-100"
                            >{{ homepage.hero.primary_cta.label }}</a
                        >
                        <a :href="homepage.hero.secondary_cta.href" class="underline underline-offset-4">{{ homepage.hero.secondary_cta.label }}</a>
                    </div>
                </div>
            </section>
            <div class="mx-auto grid max-w-6xl gap-10 px-6 py-12 md:grid-cols-2 md:py-16">
                <section id="workspace">
                    <h2 class="text-3xl font-semibold tracking-tight">{{ homepage.services.title }}</h2>
                    <ol class="mt-6 list-decimal space-y-4 pl-5 leading-relaxed marker:text-teal-800">
                        <li v-for="item in homepage.services.items" :key="item" class="pl-2">{{ item }}</li>
                    </ol>
                </section>
                <section id="tiktok" class="border-t border-slate-300 pt-8 md:border-t-0 md:border-l md:pt-0 md:pl-10">
                    <h2 class="text-3xl font-semibold tracking-tight">{{ homepage.process.title }}</h2>
                    <ol class="mt-6 list-decimal space-y-4 pl-5 leading-relaxed marker:text-teal-800">
                        <li v-for="item in homepage.process.items" :key="item" class="pl-2">{{ item }}</li>
                    </ol>
                    <p class="mt-6 text-sm text-slate-600">
                        During Sandbox testing, participating creators use a private TikTok account and select “Only me”.
                    </p>
                    <a :href="channelsUrl" class="mt-4 inline-block font-semibold text-teal-800 underline underline-offset-4"
                        >Connect TikTok in your workspace</a
                    >
                </section>
            </div>
        </main>
        <footer id="contact" class="border-t border-slate-200">
            <div class="mx-auto flex max-w-6xl flex-wrap items-start justify-between gap-6 px-6 py-8 text-sm">
                <div>
                    <p class="font-semibold">Poster · {{ homepage.footer.legal_name }}</p>
                    <a :href="homepage.contact.cta_href" class="underline">{{ homepage.contact.email }}</a>
                </div>
                <nav aria-label="Legal" class="flex flex-wrap gap-5 underline">
                    <Link :href="route('policy')">Privacy Policy</Link>
                    <Link :href="route('terms')">Terms of Service</Link>
                </nav>
            </div>
        </footer>
    </div>
</template>
