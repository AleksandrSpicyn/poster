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
const form = useForm({ request_key: props.checkout.request_key, catalog_version: props.checkout.catalog_version });
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
            <p v-else class="text-sm">Оплата в тестовом магазине. Деньги с карты не списываются. После подтверждения секунды появятся на балансе.</p>
            <p v-if="offer.monthly" class="text-muted-foreground text-sm">Продление вручную. Следующий месяц оплачивается отдельно.</p>
            <InputError :message="Object.values(form.errors).join(' ')" />
            <Button v-if="pendingOrder" as-child
                ><Link :href="route('billing.orders.show', pendingOrder)">Открыть заказ №{{ pendingOrder }}</Link></Button
            >
            <Button v-else-if="!checkout.blocked" :disabled="form.processing" type="submit">{{
                form.processing ? 'Оформляем заказ…' : 'Оформить тестовый заказ · ' + money(offer.amount)
            }}</Button>
            <Button variant="outline" as-child><Link :href="route('billing.show', { section: 'plans' })">Назад к тарифам</Link></Button>
        </form>
    </div>
</template>
