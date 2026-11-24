<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import BillingCheckout from '@/components/billing/BillingCheckout.vue';
import BillingHistory from '@/components/billing/BillingHistory.vue';
import BillingOrderDetails from '@/components/billing/BillingOrderDetails.vue';
import BillingOverview from '@/components/billing/BillingOverview.vue';
import BillingPlans from '@/components/billing/BillingPlans.vue';
import { useRoute } from '@/composables/useRoute';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BillingWorkspace } from '@/types/billing';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps<BillingWorkspace>();
const route = useRoute();
const offer = computed(() => props.catalog.find((o) => o.code === props.selected));
const tabs = [
    { section: 'overview', title: 'Обзор' },
    { section: 'plans', title: 'Тарифы' },
    { section: 'payments', title: 'История' },
];
const currentTab = computed(() => (props.section === 'seconds' ? 'payments' : props.section));
const pageTitle = computed(() => (props.order ? 'Заказ №' + props.order.id : offer.value ? 'Покупка · ' + offer.value.label : 'Подписка'));
</script>
<template>
    <AppLayout :breadcrumbs="[{ title: 'Подписка', href: route('billing.show') }]"
        ><Head :title="pageTitle" />
        <div class="workspace-page">
            <Heading :title="pageTitle" description="Тариф и секунды генерации для всех ваших проектов." />
            <nav class="flex flex-wrap gap-5 border-b" aria-label="Разделы подписки">
                <Link
                    v-for="tab in tabs"
                    :key="tab.section"
                    class="text-muted-foreground aria-[current]:border-ring aria-[current]:text-ring inline-flex min-h-11 items-center border-b-2 border-transparent px-1 text-sm font-medium"
                    :aria-current="currentTab === tab.section ? 'page' : undefined"
                    :href="
                        tab.section === 'payments'
                            ? route('billing.history')
                            : tab.section === 'overview'
                              ? route('billing.show')
                              : route('billing.show', { section: tab.section })
                    "
                    >{{ tab.title }}</Link
                >
            </nav>
            <p v-if="pendingOrder && !order && !checkout" class="bg-sidebar-accent text-sidebar-accent-foreground rounded-xl px-4 py-3 text-sm">
                Ожидается подтверждение платежа.
                <Link class="font-semibold underline" :href="route('billing.orders.show', pendingOrder)">Открыть заказ №{{ pendingOrder }}</Link>
            </p>
            <BillingOrderDetails
                v-if="order"
                :key="order.id"
                :order="order"
                :label="catalog.find((p) => p.code === order?.product_code)?.label ?? order.product_code"
                :confirmation-url="confirmationUrl"
                :receipts="receipts"
                :refunds="refunds"
            />
            <BillingCheckout v-else-if="offer && checkout" :key="offer.code" :offer="offer" :checkout="checkout" :pending-order="pendingOrder" />
            <BillingOverview
                v-else-if="section === 'overview' && balance"
                :catalog-version="catalogVersion"
                :account="account"
                :balance="balance"
                :sources="sources"
                :catalog="catalog"
            />
            <BillingPlans v-else-if="section === 'plans'" :catalog="catalog" />
            <BillingHistory
                v-else-if="(section === 'seconds' || section === 'payments') && history"
                :section="section"
                :history="history"
                :project-filter="projectFilter"
                :history-projects="historyProjects"
                :catalog="catalog"
            /></div
    ></AppLayout>
</template>
