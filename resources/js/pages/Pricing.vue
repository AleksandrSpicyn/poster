<script setup lang="ts">
import LandingFooter from '@/components/LandingFooter.vue';
import LandingHomeLink from '@/components/LandingHomeLink.vue';
import LandingHeader from '@/components/LandingHeader.vue';
import { useRoute } from '@/composables/useRoute';
import { money } from '@/lib/billing';
import { formatCount } from '@/lib/formatCount';
import type { Offer } from '@/types/billing';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ catalog: Offer[] }>();
const route = useRoute();
const takeLength = 15;
const capacity = computed(() => Math.ceil(Math.max(0, ...props.catalog.map((offer) => offer.seconds)) / takeLength));
</script>
<template>
    <div class="pricing-view">
        <Head title="Тарифы студии" />
        <a class="skip" href="#pricing-title">К тарифам</a>
        <LandingHeader section="pricing" />
        <main class="catalog">
            <div class="price-hero">
                <LandingHomeLink />
                <h1 id="pricing-title" tabindex="-1">Тарифы студии</h1>
                <p>Выберите, сколько секунд видео хотите создать. Один пакет действует во всех ваших проектах.</p>
            </div>
            <section class="plan-grid" aria-label="Пакеты генерации">
                <article v-for="offer in catalog" :key="offer.code" class="offer" :class="offer.code">
                    <h2>{{ offer.label }}</h2>
                    <p class="mode">{{ offer.monthly ? 'Подписка' : 'Первая покупка' }}</p>
                    <p class="amount">
                        {{ money(offer.amount) }} <small>{{ offer.monthly ? 'в месяц' : 'однократно' }}</small>
                    </p>
                    <div class="capacity" aria-hidden="true" :style="{ gridTemplateColumns: `repeat(${capacity}, minmax(0, 1fr))` }">
                        <i v-for="slot in capacity" :key="slot" :class="{ on: slot <= offer.seconds / takeLength }" />
                    </div>
                    <p class="quota">{{ offer.seconds }} с генерации</p>
                    <p class="equiv">{{ formatCount(offer.seconds / takeLength, 'дубль', 'дубля', 'дублей') }} по {{ takeLength }} с</p>
                    <p class="terms">
                        {{
                            offer.monthly
                                ? 'Секунды действуют один оплаченный месяц. Автопродление требует отдельного согласия.'
                                : 'Можно купить один раз, при первой покупке. Секунды действуют до использования.'
                        }}
                    </p>
                    <a class="select-offer" :href="route('billing.checkout', { product: offer.code })" :aria-label="`Выбрать пакет «${offer.label}»`"
                        >Выбрать пакет <span aria-hidden="true">→</span></a
                    >
                </article>
            </section>
            <section class="pricing-info">
                <h2>Как расходуются секунды</h2>
                <dl>
                    <div>
                        <dt>Новый дубль</dt>
                        <dd>Секунды новых видеодублей Seedance Mini в 480p со звуком. Дубль на 8 секунд резервирует 8 секунд при запуске.</dd>
                    </div>
                    <div>
                        <dt>Во время генерации</dt>
                        <dd>При готовности дубля зарезервированные секунды списываются. Окончательный технический сбой освобождает резерв.</dd>
                    </div>
                    <div>
                        <dt>Готовое видео</dt>
                        <dd>Скачивание, монтаж и публикация готового файла — 0 секунд.</dd>
                    </div>
                </dl>
            </section>
        </main>
        <LandingFooter />
    </div>
</template>
<style scoped>
.pricing-view {
    --ink: #fbf6ed;
    --bg: #12111c;
    --lime: #c9fa48;
    --pad: clamp(20px, 3.4vw, 66px);
    color-scheme: dark;
    color: var(--ink);
    background: var(--bg);
    font:
        16px/1.45 'Poster Sans',
        system-ui,
        sans-serif;
    min-height: 100svh;
    display: flex;
    flex-direction: column;
}
h1,
h2 {
    font-weight: 700;
}
a:focus-visible {
    outline: 3px solid var(--lime);
    outline-offset: 4px;
}
.skip {
    position: absolute;
    left: 18px;
    top: -8em;
}
.skip:focus {
    top: 10px;
    z-index: 5;
    background: var(--bg);
    padding: 12px;
}
.catalog {
    flex: 1;
    width: 100%;
    max-width: 1432px;
    padding-inline: clamp(20px, 4vw, 64px);
    margin: 0 auto;
}
.price-hero {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    min-height: clamp(220px, 18vw, 275px);
    display: flex;
    flex-flow: column;
    justify-content: center;
    padding: 26px 0 34px;
}
.price-hero::before {
    content: '';
    position: absolute;
    inset: 0 -1px 0 24%;
    z-index: -1;
    background:
        linear-gradient(90deg, #12111c 0%, rgba(18, 17, 28, 0.72) 22%, transparent 51%),
        linear-gradient(0deg, #12111c, transparent 25%, transparent 83%, #12111c),
        url('../../assets/studio/lunar-landscape.webp') 50% 57% / cover no-repeat;
    opacity: 0.85;
    mask-image: linear-gradient(90deg, transparent, #000 13%, #000 86%, transparent);
}
.price-hero h1 {
    font-size: clamp(54px, 5.4vw, 88px);
    line-height: 0.98;
    color: #fbf6ed;
    letter-spacing: -0.066em;
}
.price-hero p {
    max-width: 525px;
    color: #e0dce6;
    font-size: clamp(16px, 1.38vw, 21px);
    margin-top: 16px;
    line-height: 1.4;
    text-wrap: pretty;
}
.plan-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.offer {
    --tone: #d6beff;
    --wash: #30273e;
    display: flex;
    flex-direction: column;
    padding: clamp(18px, 1.8vw, 28px);
    border-radius: 18px;
    background: linear-gradient(150deg, var(--wash), #1c1927 68%);
    border: 1px solid #51465e;
    position: relative;
    overflow: hidden;
}
.offer.author {
    --tone: #cff568;
    --wash: #2d3424;
    border-color: #5b6540;
}
.offer.studio {
    --tone: #a8c6ff;
    --wash: #25314a;
    border-color: #455572;
}
.offer h2 {
    font-size: clamp(21px, 1.85vw, 30px);
    line-height: 1.16;
    letter-spacing: -0.045em;
    margin: 0;
}
.offer .mode {
    font-size: 14px;
    color: #d4cfdb;
    margin-top: 5px;
}
.amount {
    font-size: clamp(32px, 3.3vw, 55px);
    line-height: 1.08;
    letter-spacing: -0.074em;
    margin: 24px 0 17px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
.amount small {
    font-weight: 400;
    font-size: 15px;
    color: #d5d1db;
    letter-spacing: 0;
}
.capacity {
    display: grid;
    gap: 3px;
    margin: 0 0 13px;
}
.capacity i {
    height: 10px;
    background: #4d4758;
    border-radius: 2px;
}
.capacity i.on {
    background: var(--tone);
}
.quota {
    font-size: 19px;
    font-weight: 700;
    letter-spacing: -0.035em;
}
.equiv {
    color: #ded9e5;
    font-size: 15px;
    margin-top: 1px;
}
.terms {
    border-top: 1px solid #4b4454;
    margin: 19px 0 18px;
    padding-top: 13px;
    color: #d2cedb;
    line-height: 1.5;
    font-size: 14px;
    flex: 1;
}
.select-offer {
    font-weight: 700;
    text-align: left;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: transparent;
    border: 1px solid #766e83;
    padding: 9px 15px;
    border-radius: 9px;
    min-height: 44px;
    cursor: pointer;
}
.select-offer:hover {
    background: #383344;
}
.author .select-offer {
    background: #cff568;
    border-color: #cff568;
    color: #182207;
}
.author .select-offer:hover {
    background: #dcff84;
}
.pricing-info {
    display: grid;
    grid-template-columns: 0.8fr 2fr;
    gap: 32px;
    border-top: 1px solid #494252;
    margin: 34px 0 22px;
    padding: 22px 0;
}
.pricing-info h2 {
    font-size: clamp(22px, 2vw, 32px);
    letter-spacing: -0.045em;
}
.pricing-info dl {
    margin: 0;
}
.pricing-info dl > div {
    display: grid;
    grid-template-columns: minmax(130px, 0.65fr) 1.6fr;
    gap: 18px;
    padding: 12px 0;
    border-bottom: 1px solid #35303f;
}
.pricing-info dl > div:first-child {
    padding-top: 3px;
}
.pricing-info dt {
    font-weight: 700;
}
.pricing-info dd {
    margin: 0;
    color: #d3cddc;
}
@media (width > 1440px) {
    .catalog {
        max-width: 1780px;
        padding-inline: var(--pad);
    }
}
@media (max-width: 900px) {
    .catalog {
        padding-inline: 22px;
    }
    .offer {
        padding: 17px;
    }
    .capacity {
        gap: 2px;
    }
    .terms {
        min-height: 72px;
    }
}
@media (max-width: 700px) {
    .catalog {
        padding-inline: 18px;
    }
    .price-hero {
        min-height: 192px;
        padding: 16px 0 24px;
    }
    .price-hero h1 {
        font-size: clamp(42px, 10vw, 65px);
    }
    .price-hero p {
        font-size: 15px;
        max-width: 85%;
    }
    .price-hero::before {
        inset: 0 -16% 0 32%;
        opacity: 0.5;
    }
    .plan-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .offer {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 0 9px;
        padding: 18px;
        border-radius: 14px;
    }
    .offer h2 {
        grid-area: 1/1;
        font-size: 22px;
    }
    .offer .mode {
        grid-area: 2/1;
        font-size: 13px;
    }
    .amount {
        grid-area: 1/2/3/3;
        align-self: center;
        margin: 0;
        text-align: right;
        font-size: 32px;
    }
    .amount small {
        display: block;
        font-size: 12px;
        margin-top: 3px;
    }
    .capacity,
    .terms,
    .select-offer,
    .quota,
    .equiv {
        grid-column: 1/-1;
    }
    .capacity {
        margin: 17px 0 9px;
    }
    .capacity i {
        height: 6px;
    }
    .quota {
        font-size: 17px;
    }
    .equiv,
    .terms {
        font-size: 14px;
    }
    .terms {
        min-height: 0;
        margin: 10px 0 12px;
        padding-top: 8px;
    }
    .select-offer {
        min-height: 44px;
    }
    .pricing-info {
        display: block;
        margin: 25px 0;
        padding-top: 18px;
    }
    .pricing-info dl {
        margin-top: 10px;
    }
    .pricing-info dl > div {
        grid-template-columns: 1fr;
        gap: 3px;
    }
}
</style>
