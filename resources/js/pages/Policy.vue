<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="public-frame">
        <PublicHeader workspace-link />
        <main class="legal-copy pt-[13px] min-[700px]:pt-[18px]" lang="en">
            <Head title="Privacy Policy" />
            <h1>Privacy Policy</h1>
            <p class="relative -left-px mt-[17px] text-sm tracking-[0.06px] min-[700px]:mt-5">
                <time datetime="2026-11-22">Updated November 22, 2026</time>
            </p>
            <p class="mt-6 leading-relaxed">
                This policy explains how Aleksandr Olegovich Spitsyn, the operator of Poster, handles information when you visit the site, work on
                videos, or connect a social account to the creator workspace.
            </p>
            <section class="mt-8">
                <h2 class="text-xl font-semibold">Using the site and workspace</h2>
                <p class="mt-3 leading-relaxed">
                    Poster uses a session cookie to keep track of your visit, sign-in and account connection. If you have a workspace account, Poster
                    processes your name, email address and the project materials you provide, including scripts, reference images, video files and
                    captions. These records let you return to a project, assemble an episode and track releases.
                </p>
            </section>
            <section class="mt-8">
                <h2 class="text-xl font-semibold">Connecting a social account</h2>
                <p class="mt-3 leading-relaxed">
                    When you connect a channel, its platform authorizes Poster to use the permissions you grant. Poster stores the encrypted
                    credentials, expiry and display name with the channel. Your password stays with the platform.
                </p>
                <p class="mt-3 leading-relaxed">
                    Poster reads your creator settings to show your account, available audiences and interaction options. It can also retrieve your
                    public video list and the available view, like, comment and share counts for your workspace.
                </p>
            </section>
            <section class="mt-8">
                <h2 class="text-xl font-semibold">What Poster sends</h2>
                <p class="mt-3 leading-relaxed">
                    When you confirm a publication, Poster sends the selected video, caption, audience, interaction choices and applicable disclosures
                    to the connected platform. Poster uses the identifier it receives to check processing and show the result.
                </p>
                <p class="mt-3 leading-relaxed">
                    In the production workspace, the services involved depend on the project. Generation providers receive the prompts and reference
                    media needed for the selected generation. Connected publishing platforms receive the material selected for release.
                </p>
            </section>
            <section class="mt-8">
                <h2 class="text-xl font-semibold">Retention and your choices</h2>
                <p class="mt-3 leading-relaxed">
                    Poster stores publication receipts with your project history so you can check the result across sessions. Disconnecting a
                    workspace channel clears its saved credentials.
                </p>
                <p class="mt-3 leading-relaxed">
                    You can revoke Poster’s access in your social account settings. To remove a video already delivered to a connected platform, use
                    its video controls. To request access, correction or deletion of information held by Poster, email the operator below and identify
                    the account or project involved. Please omit passwords and access tokens.
                </p>
            </section>
            <footer class="mt-10 flex flex-wrap gap-5 border-t pt-6 text-sm">
                <a href="mailto:fenixxxar85@gmail.com" class="underline">fenixxxar85@gmail.com</a>
                <Link :href="route('terms')" class="underline">Terms of Service</Link>
            </footer>
        </main>
    </div>
</template>
