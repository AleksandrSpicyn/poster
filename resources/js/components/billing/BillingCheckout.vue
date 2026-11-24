<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useRoute } from '@/composables/useRoute';
import { useFormApi } from '@/lib/api/useFormApi';
import { money } from '@/lib/billing';
import type { Checkout, Offer } from '@/types/billing';
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps<{ offer: Offer; checkout: Checkout; pendingOrder: number | null }>();
const route = useRoute();
const submit = useFormApi();
const form = useForm({
    request_key: props.checkout.request_key,
    catalog_version: props.checkout.catalog_version,
    accept_offer: false,
    auto_renew: false,
});
</script>
<template>
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="studio-panel">
            <h2>Состав платежа</h2>
            <dl>
                <div class="studio-row">
                    <dt>Пакет</dt>
                    <dd class="font-bold">{{ offer.label }}</dd>
                </div>
                <div class="studio-row">
                    <dt>Генерация</dt>
                    <dd>{{ offer.seconds }} с</dd>
                </div>
                <div class="studio-row">
                    <dt>Срок действия</dt>
                    <dd>{{ offer.monthly ? 'Месяц с момента оплаты' : 'Без срока' }}</dd>
                </div>
                <div class="studio-row font-bold">
                    <dt>К оплате</dt>
                    <dd>{{ money(offer.amount) }}</dd>
                </div>
            </dl>
        </section>
        <form class="studio-panel flex flex-col items-start gap-4" @submit.prevent="submit(form, ['billing.orders.store', { product: offer.code }])">
            <h2 class="!mb-0">Оформление покупки</h2>
            <p v-if="pendingOrder">У вас есть незавершённый платёж.</p>
            <p v-else-if="checkout.blocked">{{ checkout.blocked }}</p>
            <p v-else class="text-sm">
                <template v-if="checkout.test_mode">Оплата в тестовом магазине. Деньги с карты не списываются. </template>После подтверждения секунды
                появятся на балансе.
            </p>
            <label v-if="checkout.can_auto_renew && !checkout.blocked && !pendingOrder" class="flex items-start gap-2 text-sm">
                <input v-model="form.auto_renew" type="checkbox" class="mt-1" />
                Сохранить способ оплаты и автоматически продлевать подписку за {{ money(offer.amount) }} в месяц. Отключить продление можно в
                кабинете.
            </label>
            <p v-else-if="offer.monthly" class="text-muted-foreground text-sm">Продление вручную. Следующий месяц оплачивается отдельно.</p>
            <label v-if="!checkout.test_mode && checkout.offer_url && !checkout.blocked && !pendingOrder" class="flex items-start gap-2 text-sm">
                <input v-model="form.accept_offer" type="checkbox" class="mt-1" required />
                <span>Принимаю <a class="underline" :href="checkout.offer_url" target="_blank" rel="noopener">условия оферты</a>.</span>
            </label>
            <InputError :message="Object.values(form.errors).join(' ')" />
            <Button v-if="pendingOrder" as-child
                ><Link :href="route('billing.orders.show', pendingOrder)">Открыть заказ №{{ pendingOrder }}</Link></Button
            >
            <Button v-else-if="!checkout.blocked" :disabled="form.processing" type="submit">{{
                form.processing
                    ? 'Оформляем заказ…'
                    : (checkout.test_mode ? 'Оформить тестовый заказ · ' : 'Перейти к оплате · ') + money(offer.amount)
            }}</Button>
            <Button variant="outline" as-child><Link :href="route('billing.show', { section: 'plans' })">Назад к тарифам</Link></Button>
        </form>
    </div>
</template>
