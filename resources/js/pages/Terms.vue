<script setup lang="ts">
import PublicHeader from '@/components/PublicHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="public-frame">
        <PublicHeader workspace-link />
        <main class="legal-copy pt-[13px] min-[700px]:pt-[18px]" lang="en">
            <Head title="Terms of Service" />
            <h1>Terms of Service</h1>
            <p class="mt-[17px] text-sm min-[700px]:mt-[21px]">Updated November 22, 2026</p>
            <p class="mt-6 leading-relaxed">
                These terms apply when you use Poster to prepare, manage or publish short videos. Poster is operated by Aleksandr Olegovich Spitsyn.
            </p>
            <section class="mt-8">
                <h2 class="text-xl font-semibold">Who can use Poster</h2>
                <p class="mt-3 leading-relaxed">
                    Poster is designed for creators working on their own videos and for people authorized to manage a project or channel for someone
                    else. You may use features made available to you for personal or professional creative work. You must have authority to act for
                    any account or organization you represent.
                </p>
            </section>
            <section class="mt-8">
                <h2 class="text-xl font-semibold">Your content and permissions</h2>
                <p class="mt-3 leading-relaxed">
                    You retain the rights you hold in your content. By submitting material, you give Poster permission to process it and deliver it to
                    the services needed for the actions you request. Only upload material you have the rights to use, including music, images and
                    footage. Obtain any permissions required from people featured in the video.
                </p>
            </section>
            <section class="mt-8">
                <h2 class="text-xl font-semibold">Publishing to a connected channel</h2>
                <p class="mt-3 leading-relaxed">
                    Before confirming a publication, check the recipient, preview the file, review the caption and choose an audience. Select any
                    required commercial or AI-content disclosures and review the music terms shown in the form.
                </p>
                <p class="mt-3 leading-relaxed">
                    Delivery and processing are separate steps. Check the publication status before submitting the same file again. You are
                    responsible for the content you approve and for following the connected platform’s terms and community rules.
                </p>
            </section>
            <section class="mt-8">
                <h2 class="text-xl font-semibold">Account access and support</h2>
                <p class="mt-3 leading-relaxed">
                    Keep your workspace access secure. Use only accounts and projects you are authorized to manage. Stop using a connection when your
                    authorization ends. For questions about these terms or to report unauthorized use, contact the operator below.
                </p>
            </section>
            <footer class="mt-10 flex flex-wrap gap-5 border-t pt-6 text-sm">
                <a href="mailto:fenixxxar85@gmail.com" class="underline">fenixxxar85@gmail.com</a>
                <Link :href="route('policy')" class="underline">Privacy Policy</Link>
            </footer>
        </main>
    </div>
</template>
