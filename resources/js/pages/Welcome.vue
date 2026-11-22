<script setup lang="ts">
import type { SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, useTemplateRef } from 'vue';
import { useRoute } from 'ziggy-js';
import projectTray from '../../assets/studio/project-tray.webp';

const route = useRoute();
const page = usePage<SharedData>();
const workflow = useTemplateRef<HTMLDetailsElement>('workflow');

function openWorkflow() {
    if (workflow.value) workflow.value.open = true;
}

function revealSection() {
    if (window.location.hash === '#workflow' || window.location.hash === '#workspace') openWorkflow();
}

onMounted(() => {
    revealSection();
    window.addEventListener('hashchange', revealSection);
});
onUnmounted(() => window.removeEventListener('hashchange', revealSection));
</script>

<template>
    <div class="landing">
        <Head title="Poster — студия коротких форм" /> <a class="skip" href="#main">К содержанию</a>
        <div class="top">
            <header class="head">
                <div class="outer nav">
                    <a class="logo" href="#main" aria-label="Poster, главная"
                        ><i aria-hidden="true"><b></b><b></b><b></b></i>Poster</a
                    >
                    <nav aria-label="Навигация">
                        <a href="#features">Возможности</a><a href="#workflow" class="flow-link" @click="openWorkflow">Рабочий процесс</a>
                    </nav>
                    <a class="login" :href="route(page.props.auth.user ? 'dashboard' : 'login')">Войти в студию</a>
                </div>
            </header>
            <main id="main" tabindex="-1">
                <div class="outer">
                    <section class="hero" aria-labelledby="title">
                        <div class="copy">
                            <h1 id="title"><span>Ваша студия</span> <span>коротких</span> <span>форм.</span></h1>
                            <p class="lede">Создавайте сериалы, собирайте клипы и планируйте публикации в одном месте.</p>
                            <a class="cta" href="#features">Изучить возможности <span aria-hidden="true">↓</span></a>
                        </div>
                        <figure class="art">
                            <svg viewBox="602 82 900 680" role="img" aria-labelledby="art-title">
                                <title id="art-title">В синем лотке лежат карточки проектов «Лунный атлас», «Ночной маршрут» и «Лесная почта».</title>
                                <defs>
                                    <image id="texture" x="607" y="88" width="885" height="660" :href="projectTray" />
                                    <path
                                        id="body"
                                        d="M626 654 Q627 624 637 593 L675 557 Q639 551 640 530 Q646 478 662 445 Q666 434 678 425 L827 299 L880 134 L950 141 L965 108 L1047 116 L1059 99 L1450 138 L1408 306 L1451 311 Q1460 314 1462 325 L1460 444 L1440 463 L1439 481 Q1451 480 1451 493 L1449 534 Q1448 551 1435 564 L1229 717 Q1217 732 1194 733 L642 678 Q626 675 626 654Z"
                                    />
                                    <path id="card" d="M880 134 L1358 175 L1249 580 Q1244 596 1229 596 L745 544Z" />
                                    <clipPath id="tray-cut"><use href="#body" /></clipPath>
                                    <clipPath id="sheet-cut"><use href="#card" /></clipPath>
                                    <clipPath id="lip">
                                        <path d="M624 539 L742 543 L1228 594 Q1243 595 1249 578 L1258 544 Q1261 494 1300 466 L1465 320 V747 H606Z" />
                                    </clipPath>
                                </defs>
                                <g clip-path="url(#tray-cut)">
                                    <use href="#texture" />
                                    <use href="#card" fill="#393248" />
                                </g>
                                <g class="sheet"><use href="#texture" clip-path="url(#sheet-cut)" /></g>
                                <g clip-path="url(#tray-cut)"><use href="#texture" clip-path="url(#lip)" /></g>
                            </svg>
                        </figure>
                    </section>
                    <section class="features" id="features" aria-labelledby="cap" tabindex="-1">
                        <h2 id="cap">Возможности студии</h2>
                        <div class="items">
                            <article class="feature" style="--i: 0">
                                <svg viewBox="0 0 60 52" aria-hidden="true" fill="none" stroke="#c5a1ee" stroke-width="2.5" stroke-linejoin="round">
                                    <path d="M5 13a3 3 0 0 1 3-3h13l5 5h21a3 3 0 0 1 3 3v24a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3Z" />
                                </svg>
                                <h3>Проекты</h3>
                                <p>Собирайте материалы и версии каждого проекта в одном месте.</p>
                            </article>
                            <article class="feature" style="--i: 1">
                                <svg viewBox="0 0 60 52" aria-hidden="true" fill="none" stroke="#a5b4ff" stroke-width="2.5" stroke-linejoin="round">
                                    <path d="M6 21h35v23H6z M19 21V10h35v23H41" />
                                </svg>
                                <h3>Сериалы и клипы</h3>
                                <p>Планируйте эпизоды и собирайте клипы из исходных материалов.</p>
                            </article>
                            <article class="feature" style="--i: 2">
                                <svg viewBox="0 0 60 52" aria-hidden="true" fill="none" stroke="#a9e4b8" stroke-width="2.5" stroke-linejoin="round">
                                    <path d="M24 7h12v12H24z M6 34h12v12H6z M24 34h12v12H24z M42 34h12v12H42z M30 19v15 M12 34v-8h36v8" />
                                </svg>
                                <h3>Каналы</h3>
                                <p>Подключайте аккаунты и выбирайте площадки для выпуска.</p>
                            </article>
                            <article class="feature" style="--i: 3">
                                <svg viewBox="0 0 60 52" aria-hidden="true" fill="none" stroke="#f3b4a5" stroke-width="2.5" stroke-linejoin="round">
                                    <path d="M6 30h10v15H6z M24 20h10v25H24z M42 8h10v37H42z" />
                                </svg>
                                <h3>Аналитика</h3>
                                <p>Отслеживайте просмотры и реакции по каналам и публикациям.</p>
                            </article>
                        </div>
                    </section>
                </div>
            </main>
        </div>
        <div class="outer">
            <details ref="workflow" id="workflow" class="flow">
                <summary>Рабочий процесс</summary>
                <ol>
                    <li>Создайте проект, задайте формат и добавьте материалы.</li>
                    <li>Подготовьте эпизод или выберите клипы для выпуска.</li>
                    <li>Выберите канал, подтвердите публикацию и следите за результатом.</li>
                </ol>
            </details>
            <footer class="foot"><a :href="route('policy')">Privacy Policy</a><a :href="route('terms')">Terms of Service</a></footer>
        </div>
    </div>
</template>
<style scoped>
.landing {
    color-scheme: dark;
    --bg: #12111c;
    --ink: #fbf6ed;
    --muted: #d1cddc;
    --edge: #514c62;
    --lime: #c9fa48;
    --pad: clamp(20px, 3.4vw, 66px);
}
* {
    box-sizing: border-box;
}
:global(html:has(.landing)) {
    scroll-behavior: smooth;
    scroll-padding-top: 20px;
}
.landing {
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font:
        16px/1.45 'Poster Sans',
        system-ui,
        sans-serif;
}
a {
    color: inherit;
    text-decoration: none;
}
a:focus-visible,
summary:focus-visible {
    outline: 3px solid var(--lime);
    outline-offset: 5px;
}
h1,
h2,
h3,
p,
figure {
    margin: 0;
}
.top {
    overflow: hidden;
}
.landing {
    background: var(--bg) url('../../assets/studio/landing-glow-desktop.webp') center top / 100% 100svh no-repeat;
}
.outer {
    max-width: 1780px;
    margin-inline: auto;
    padding-inline: var(--pad);
}
.head {
    border-bottom: 1px solid #696276;
}
.nav {
    display: flex;
    align-items: center;
    gap: clamp(25px, 4.2vw, 80px);
    min-height: calc(clamp(70px, 5.2vw, 92px) - 1px);
}
.logo {
    display: inline-flex;
    gap: clamp(0px, calc(1vw - 8.4px), 9px);
    align-items: center;
    line-height: 1;
    flex-basis: calc(3.66em + 52px);
    font-size: clamp(29px, 3vw, 48px);
    letter-spacing: -0.072em;
    font-weight: 700;
}
.logo i {
    display: inline-flex;
    gap: 4px;
    transform: skew(-24deg) scaleX(1.08);
    margin-inline: 5px;
}
.logo b {
    height: 0.74em;
    width: 0.17em;
    background: currentColor;
}
.nav nav {
    display: flex;
    gap: clamp(18px, 3.2vw, 54px);
}
.nav nav a,
.login,
.cta {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    font-weight: 700;
    letter-spacing: normal;
}
.nav nav a {
    font-size: clamp(14px, 1.4vw, 22px);
}
.nav nav a:hover {
    color: #d8c5ff;
}
.login {
    margin-left: auto;
    border: 1.5px solid currentColor;
    border-radius: 11px;
    padding: 6px 20px;
}
.login:hover {
    background: var(--ink);
    color: var(--bg);
}
.hero {
    min-height: clamp(530px, 44vw, 765px);
    display: grid;
    align-items: center;
    position: relative;
    isolation: isolate;
}
.copy {
    z-index: 1;
    width: 54%;
    padding: clamp(32px, 4vw, 72px) 0;
    pointer-events: none;
}
.copy a {
    pointer-events: auto;
}
h1 {
    font-size: clamp(64px, 7.15vw, 128px);
    position: relative;
    top: -0.05em;
    letter-spacing: -0.075em;
    line-height: 0.96;
}
h1 span {
    display: block;
    white-space: nowrap;
}
h1 span:last-child {
    width: fit-content;
    color: #eadfff;
}
.lede {
    position: relative;
    top: -0.1em;
    max-width: min(24em, 91%);
    margin: clamp(19px, 1.8vw, 36px) 0 clamp(22px, 2.6vw, 42px);
    font-size: clamp(17px, 1.68vw, 29px);
    line-height: 1.28;
    letter-spacing: normal;
}
.cta {
    gap: 12px;
    padding: 11px clamp(16px, calc(13px + 0.35vw), 18.5px);
    border-radius: 11px;
    background: var(--lime);
    color: #19200d;
    font-size: clamp(16px, 1.45vw, 24px);
}
.cta span {
    transition: transform 0.2s ease;
}
.cta:hover {
    background: #dafb78;
}
.cta:hover span,
.cta:focus-visible span {
    transform: translateY(3px);
}
.art {
    z-index: 0;
    position: absolute;
    right: 0.7%;
    width: 60%;
    top: calc(50% - 1px);
    transform: translateY(-50%);
    pointer-events: none;
}
.art svg {
    position: relative;
    display: block;
    width: 100%;
    height: auto;
    overflow: visible;
}
.sheet {
    transform-box: fill-box;
    transform-origin: 50% 90%;
}
.features {
    padding-bottom: 25px;
}
.features h2 {
    border-top: 1px solid #797286;
    padding: 7px 0 0;
    min-height: calc(44px + clamp(23px, 2vw, 34px));
    font-size: clamp(23px, 2vw, 34px);
    letter-spacing: -0.04em;
    line-height: 1.2;
}
.items {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    grid-auto-rows: minmax(140px, auto);
}
.feature {
    position: relative;
    padding: 0 9px 0 15px;
}
.feature + .feature::before {
    position: absolute;
    inset: 0 auto auto 0;
    height: min(105px, 100%);
    border-left: 1px solid #655e73;
    content: '';
}
.feature:first-child {
    padding-left: 0;
    border-left: 0;
}
.feature:last-child {
    padding-right: 0;
}
.feature svg {
    height: clamp(32px, 3.8vw, 62px);
    width: auto;
    display: block;
    margin-bottom: clamp(3px, calc(7px - 0.3vw), 6px);
}
.feature:first-child path {
    transform: translate(-2px, 1px) scale(1, 0.93);
}
.feature:nth-child(2) path {
    transform: translate(-3px, -1px);
}
.feature:nth-child(3) path {
    transform: translate(0, 1px) scale(0.92, 0.9);
}
.feature:nth-child(4) path {
    transform: translate(-2px, 1px) scale(0.9);
}
.feature h3 {
    font-size: clamp(20px, 1.85vw, 32px);
    line-height: 1.18;
    letter-spacing: -0.04em;
}
.feature p {
    margin-top: 5px;
    line-height: 1.32;
    color: var(--muted);
    letter-spacing: normal;
    font-size: clamp(14px, 1.16vw, 19px);
}
.flow {
    padding-block: 0;
    border-top: 1px solid var(--edge);
}
.flow summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    cursor: pointer;
    list-style: none;
    font-size: 25px;
    min-height: 75px;
    font-weight: 700;
    letter-spacing: -0.05em;
}
.flow summary::-webkit-details-marker {
    display: none;
}
.flow summary::after {
    content: '+';
    color: #cfb2ff;
}
.flow[open] summary::after {
    content: '−';
}
.flow ol {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: clamp(20px, 4vw, 64px);
    padding-left: 22px;
    margin: 25px 0 0;
    color: var(--muted);
}
.flow li {
    padding-left: 4px;
    max-width: 32ch;
}
.foot {
    border-top: 1px solid var(--edge);
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    padding-block: 17px 32px;
    color: var(--muted);
    font-size: 13px;
}
.foot a:hover {
    text-decoration: underline;
}
.skip {
    position: absolute;
    top: -8em;
    left: 18px;
}
.skip:focus {
    top: 10px;
    z-index: 5;
    background: var(--bg);
    padding: 12px;
}
@keyframes place-sheet {
    from {
        transform: translate(7px, -15px) rotate(0.9deg);
    }
    to {
        transform: none;
    }
}
@keyframes enter-card {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
@media (prefers-reduced-motion: no-preference) {
    .sheet {
        animation: place-sheet 0.82s cubic-bezier(0.2, 0.72, 0.2, 1) both;
    }
    .feature {
        animation: enter-card 0.4s ease-out both;
        animation-delay: calc(0.14s + var(--i) * 0.055s);
    }
    .login,
    .cta {
        transition:
            background-color 0.17s,
            color 0.17s;
    }
}
@media (min-width: 1800px) {
    .cta {
        padding-block: 12px;
    }
    h1 {
        top: -0.035em;
    }
    .features {
        padding-bottom: 7px;
    }
    .landing {
        background-image: url('../../assets/studio/landing-glow-wide.webp');
    }
}
@media (max-width: 900px) and (min-width: 701px) {
    .landing {
        background-image: url('../../assets/studio/landing-glow-tablet.webp');
    }
    .hero {
        min-height: 466px;
    }
    .copy {
        width: 54%;
    }
    h1 {
        font-size: 7.3vw;
    }
    .art {
        width: 57%;
    }
    .items {
        grid-template-columns: 1fr 1fr;
        row-gap: 0;
    }
    .feature:nth-child(3)::before {
        display: none;
    }
    .feature:nth-child(3) {
        border: 0;
        padding-left: 0;
    }
}
@media (max-width: 700px) {
    .nav {
        min-height: 61px;
        gap: 8px;
    }
    .nav nav {
        display: none;
    }
    .login {
        padding: 5px 12px;
        font-size: 14px;
        border-radius: 9px;
    }
    .hero {
        min-height: 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }
    .copy {
        width: 100%;
        padding: 32px 0 0;
    }
    .copy h1 {
        font-size: clamp(48px, 12.5vw, 78px);
        line-height: 0.96;
    }
    .copy h1 span {
        display: inline;
    }
    .copy h1 span:first-child {
        display: block;
    }
    .lede {
        max-width: min(100%, 19.8em);
        margin: 16px 0;
        font-size: 17px;
    }
    .cta {
        min-height: 47px;
        padding: 9px 15px 9px 16px;
    }
    .art {
        position: relative;
        right: 2%;
        top: auto;
        transform: none;
        width: min(104%, 540px);
        margin: 7px auto 9px;
        align-self: center;
    }
    .features h2 {
        padding: 10px 0 0;
    }
    .items {
        grid-template-columns: 1fr 1fr;
        row-gap: 0;
        grid-auto-rows: minmax(132px, auto);
    }
    .feature {
        padding-inline: 15px 9px;
    }
    .feature:nth-child(3)::before {
        display: none;
    }
    .feature:nth-child(3) {
        border: 0;
        padding-left: 0;
    }
    .feature p {
        font-size: 14px;
    }
    .feature h3 {
        font-size: 20px;
    }
    .flow ol {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .landing {
        background-image: url('../../assets/studio/landing-glow-phone.webp');
    }
}
@media (prefers-reduced-motion: reduce) {
    :global(html:has(.landing)) {
        scroll-behavior: auto;
    }
    .cta span {
        transition: none;
    }
    .cta:hover span,
    .cta:focus-visible span {
        transform: none;
    }
}
.landing {
    min-height: 100vh;
}
h1,
h2,
h3 {
    font-weight: 700;
}
.flow ol {
    list-style: decimal;
}
.skip:focus {
    position: absolute;
}
</style>
