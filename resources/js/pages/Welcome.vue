<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

import type { HomepageProps } from '@/types/homepage';
import type { SharedData } from '@/types';

type WelcomePageProps = Omit<SharedData, 'auth'> & {
    auth: {
        user: SharedData['auth']['user'] | null;
    };
};

defineProps<{ homepage: HomepageProps }>();

const page = usePage<WelcomePageProps>();
</script>

<template>
    <Head :title="homepage.identity.legal_name" />

    <div class="public-home relative min-h-screen overflow-hidden bg-[linear-gradient(180deg,#eef4f7_0%,#f8fbfc_38%,#fdfefe_100%)] text-slate-950">
        <div class="public-home-glow public-home-glow--teal" />
        <div class="public-home-glow public-home-glow--slate" />

        <header class="sticky top-0 z-30 border-b border-white/70 bg-white/80 backdrop-blur-xl">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
                <div class="min-w-0">
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-teal-700">
                        {{ homepage.hero.kicker }}
                    </p>
                    <p class="truncate text-sm text-slate-600">{{ homepage.identity.legal_name }}</p>
                </div>

                <nav class="hidden items-center gap-6 text-sm text-slate-600 lg:flex">
                    <a
                        v-for="item in homepage.navigation"
                        :key="item.href"
                        :href="item.href"
                        class="transition hover:text-slate-950"
                    >
                        {{ item.label }}
                    </a>
                </nav>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="route('login')"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                    >
                        Login
                    </Link>

                    <a
                        :href="homepage.hero.primary_cta.href"
                        class="rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        {{ homepage.hero.primary_cta.label }}
                    </a>
                </div>
            </div>
        </header>

        <main class="relative">
            <section class="mx-auto max-w-7xl px-6 pb-18 pt-16 lg:pb-24 lg:pt-24">
                <div class="grid gap-10 xl:grid-cols-[1.2fr_0.8fr] xl:items-start">
                    <div class="space-y-8">
                        <div class="space-y-5">
                            <p class="public-home-eyebrow">{{ homepage.hero.kicker }}</p>
                            <h1 class="max-w-4xl text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">
                                {{ homepage.hero.title }}
                            </h1>
                            <p class="max-w-2xl text-lg leading-8 text-slate-600">
                                {{ homepage.hero.summary }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-4">
                            <a
                                :href="homepage.hero.primary_cta.href"
                                class="rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                            >
                                {{ homepage.hero.primary_cta.label }}
                            </a>
                            <a
                                :href="homepage.hero.secondary_cta.href"
                                target="_blank"
                                rel="noreferrer"
                                class="rounded-full border border-slate-300 bg-white/75 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                            >
                                {{ homepage.hero.secondary_cta.label }}
                            </a>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <span
                                v-for="highlight in homepage.hero.highlights"
                                :key="highlight"
                                class="public-home-chip"
                            >
                                {{ highlight }}
                            </span>
                        </div>
                    </div>

                    <aside class="public-home-card">
                        <p class="public-home-eyebrow">Official business identity</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.identity.legal_name }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ homepage.identity.status }}</p>

                        <dl class="mt-6 space-y-4 text-sm text-slate-700">
                            <div class="public-home-identity-row">
                                <dt>OGRNIP</dt>
                                <dd>{{ homepage.identity.ogrnip }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>INN</dt>
                                <dd>{{ homepage.identity.inn }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>Registered</dt>
                                <dd>{{ homepage.identity.registered_at }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>Primary activity</dt>
                                <dd>{{ homepage.identity.primary_activity }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>Tax regime</dt>
                                <dd>{{ homepage.identity.tax_regime }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>Work model</dt>
                                <dd>{{ homepage.identity.work_model }}</dd>
                            </div>
                        </dl>

                        <p class="mt-6 text-sm leading-6 text-slate-500">
                            {{ homepage.identity.registration_authority }}
                        </p>
                    </aside>
                </div>
            </section>

            <section id="services" class="mx-auto max-w-7xl px-6 py-8 lg:py-12">
                <div class="grid gap-6 lg:grid-cols-2">
                    <article class="public-home-panel">
                        <p class="public-home-eyebrow">Services</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.services.title }}</h2>
                        <ul class="mt-6 grid gap-3 text-sm leading-6 text-slate-600">
                            <li v-for="item in homepage.services.items" :key="item" class="public-home-list-item">
                                {{ item }}
                            </li>
                        </ul>
                    </article>

                    <article id="work-process" class="public-home-panel">
                        <p class="public-home-eyebrow">Delivery model</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.process.title }}</h2>
                        <ol class="mt-6 grid gap-3 text-sm leading-6 text-slate-600">
                            <li
                                v-for="(item, index) in homepage.process.items"
                                :key="item"
                                class="flex items-start gap-3"
                            >
                                <span class="public-home-step">{{ index + 1 }}</span>
                                <span>{{ item }}</span>
                            </li>
                        </ol>
                    </article>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-8 lg:py-12">
                <div class="grid gap-6 lg:grid-cols-2">
                    <article class="public-home-panel">
                        <p class="public-home-eyebrow">Engineering evidence</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.stack.title }}</h2>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <span v-for="item in homepage.stack.items" :key="item" class="public-home-chip">
                                {{ item }}
                            </span>
                        </div>
                    </article>

                    <article class="public-home-panel">
                        <p class="public-home-eyebrow">Track record</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.achievements.title }}</h2>
                        <ul class="mt-6 grid gap-3 text-sm leading-6 text-slate-600">
                            <li v-for="item in homepage.achievements.items" :key="item" class="public-home-list-item">
                                {{ item }}
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <section id="selected-clients" class="mx-auto max-w-7xl px-6 py-8 lg:py-12">
                <article class="public-home-panel">
                    <p class="public-home-eyebrow">Selected clients</p>
                    <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.selected_clients.title }}</h2>
                    <div class="mt-6 grid gap-3 xl:grid-cols-2">
                        <article
                            v-for="client in homepage.selected_clients.items"
                            :key="`${client.name}-${client.period}`"
                            class="public-home-list-item h-full"
                        >
                            <div class="flex flex-col gap-3">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-baseline sm:justify-between">
                                    <a
                                        :href="client.href"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="text-base font-semibold text-slate-950 transition hover:text-teal-700"
                                    >
                                        {{ client.name }}
                                    </a>
                                    <p class="text-sm text-slate-500">{{ client.period }}</p>
                                </div>

                                <p class="text-sm leading-6 text-slate-600">
                                    {{ client.summary }}
                                </p>
                            </div>
                        </article>
                    </div>
                </article>
            </section>

            <section id="official-status" class="mx-auto max-w-7xl px-6 py-8 lg:py-12">
                <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                    <article class="public-home-panel">
                        <p class="public-home-eyebrow">Official status</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.official_status.title }}</h2>
                        <ul class="mt-6 grid gap-3 text-sm leading-6 text-slate-600">
                            <li v-for="item in homepage.official_status.items" :key="item" class="public-home-list-item">
                                {{ item }}
                            </li>
                        </ul>
                    </article>

                    <article id="contact" class="public-home-panel">
                        <p class="public-home-eyebrow">Contact</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.contact.title }}</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-600">
                            {{ homepage.contact.note }}
                        </p>
                        <a
                            :href="homepage.contact.cta_href"
                            class="mt-6 inline-flex rounded-full bg-teal-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-teal-800"
                        >
                            {{ homepage.contact.cta_label }}
                        </a>
                        <p class="mt-5 text-sm text-slate-500">
                            {{ homepage.contact.email }}
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a
                                v-for="link in homepage.contact.links"
                                :key="link.href"
                                :href="link.href"
                                target="_blank"
                                rel="noreferrer"
                                class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                            >
                                {{ link.label }}
                            </a>
                        </div>
                    </article>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/70 bg-white/70">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-6 text-sm text-slate-500 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="font-medium text-slate-700">{{ homepage.footer.legal_name }}</p>
                    <p>{{ homepage.footer.label }} · {{ homepage.footer.note }}</p>
                </div>

                <div class="flex items-center gap-4">
                    <Link :href="route('policy')" class="transition hover:text-slate-950">Privacy Policy</Link>
                    <Link :href="route('terms')" class="transition hover:text-slate-950">Terms of Service</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
