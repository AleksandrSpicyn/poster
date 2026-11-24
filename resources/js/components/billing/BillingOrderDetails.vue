<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useRoute } from '@/composables/useRoute';
import { billingDate, money, operationLabel, orderStatus } from '@/lib/billing';
import type { BillingOrder, BillingWorkspace } from '@/types/billing';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';
const props = defineProps<{
    order: BillingOrder;
    label: string;
    confirmationUrl: string | null;
    refunds: BillingWorkspace['refunds'];
    receipts: BillingWorkspace['receipts'];
}>();
const route = useRoute();
const waiting = computed(() => props.order.status === 'open' && ['created', 'pending', 'unknown'].includes(props.order.payment_status ?? ''));
const cancelled = computed(() => props.order.status === 'cancelled' || props.order.payment_status === 'cancelled');
let timer: ReturnType<typeof setInterval>;
onMounted(() => {
    timer = setInterval(() => {
        if (waiting.value && document.visibilityState === 'visible')
            router.reload({ only: ['order', 'confirmationUrl', 'receipts', 'refunds', 'pendingOrder'] });
    }, 15000);
});
onUnmounted(() => clearInterval(timer));
</script>
<template>
    <div class="grid gap-4 lg:grid-cols-2">
        <section class="studio-panel flex flex-col items-start gap-4" aria-live="polite">
            <h2 class="!mb-0">Заказ №{{ order.id }}</h2>
            <strong class="text-2xl">{{ orderStatus(order) }}</strong>
            <p v-if="waiting" class="text-muted-foreground text-sm">
                {{
                    confirmationUrl
                        ? 'Перейдите на страницу платёжного сервиса для подтверждения.'
                        : order.payment_status === 'unknown'
                          ? 'Ответ на оплату не получен. Сохранили заказ для сверки. Повторно оплачивать его не нужно.'
                          : 'Проверяем ответ платёжного сервиса. Статус обновится автоматически.'
                }}
            </p>
            <p v-if="cancelled" class="text-muted-foreground text-sm">Секунды по этому заказу не начислены. Можно оформить новый заказ.</p>
            <Button v-if="waiting && confirmationUrl" as-child><a :href="confirmationUrl" rel="noreferrer">Продолжить оплату</a></Button
            ><Button v-if="cancelled" as-child
                ><Link :href="route('billing.checkout', { product: order.product_code })">Попробовать снова</Link></Button
            >
            <Button as-child variant="outline"><Link :href="route('billing.show')">К балансу</Link></Button>
        </section>
        <section class="studio-panel">
            <h2>Состав платежа</h2>
            <dl>
                <div class="studio-row">
                    <dt>Пакет</dt>
                    <dd>{{ label }}</dd>
                </div>
                <div class="studio-row">
                    <dt>Генерация</dt>
                    <dd>{{ order.seconds }} с</dd>
                </div>
                <div class="studio-row">
                    <dt>Дата заказа</dt>
                    <dd>{{ billingDate(order.created_at) }}</dd>
                </div>
                <div v-if="order.period_ends_at" class="studio-row">
                    <dt>Срок пакета</dt>
                    <dd>До {{ billingDate(order.period_ends_at) }}</dd>
                </div>
                <div class="studio-row font-bold">
                    <dt>Сумма</dt>
                    <dd>{{ money(order.amount_kopecks) }}</dd>
                </div>
            </dl>
        </section>
        <section v-if="refunds.length" class="studio-panel">
            <h2>Возвраты</h2>
            <div v-for="refund in refunds" :key="refund.id" class="studio-row">
                <span>{{ billingDate(refund.created_at) }} · {{ operationLabel(refund.status) }}</span
                ><strong>{{ money(refund.amount_kopecks) }}</strong>
            </div>
        </section>
        <section v-if="receipts.length" class="studio-panel">
            <h2>Чеки</h2>
            <div v-for="receipt in receipts" :key="receipt.id" class="studio-row">
                <span>{{ operationLabel(receipt.kind) }} №{{ receipt.id }}</span
                ><strong>{{ operationLabel(receipt.status) }}</strong>
            </div>
        </section>
    </div>
</template>
