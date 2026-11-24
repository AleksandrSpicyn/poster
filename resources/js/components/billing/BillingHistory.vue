<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { useRoute } from '@/composables/useRoute';
import { billingDate, money, operationLabel, orderStatus } from '@/lib/billing';
import type { BillingOrder, CreditEntry, History, Offer } from '@/types/billing';
import { Link, router } from '@inertiajs/vue3';
const props = defineProps<{
    section: 'payments' | 'seconds';
    history: History<BillingOrder | CreditEntry>;
    catalog: Offer[];
    projectFilter: number | null;
    historyProjects: { id: number; name: string }[];
}>();
const route = useRoute();
function productLabel(code: string) {
    return props.catalog.find((p) => p.code === code)?.label ?? code;
}
function changes(entry: CreditEntry) {
    return [
        ['Доступно', entry.available_delta],
        ['Резерв', entry.held_delta],
        ['Расход', entry.consumed_delta],
        ['Истекло', entry.expired_delta],
        ['Отозвано', entry.revoked_delta],
    ]
        .filter(([, value]) => Number(value) !== 0)
        .map(([label, value]) => label + ' ' + (Number(value) > 0 ? '+' : '') + value + ' с')
        .join(' · ');
}
function filterProject(event: Event) {
    const project = (event.target as HTMLSelectElement).value;
    router.get(route('billing.history'), { tab: 'seconds', ...(project ? { project } : {}) }, { preserveScroll: true });
}
</script>
<template>
    <div class="studio-panel">
        <nav class="mb-5 flex flex-wrap gap-2" aria-label="Вид истории">
            <Button
                v-for="item in [
                    { key: 'payments', title: 'Платежи' },
                    { key: 'seconds', title: 'Секунды' },
                ]"
                :key="item.key"
                as-child
                :variant="section === item.key ? 'secondary' : 'ghost'"
                ><Link :href="route('billing.history', { tab: item.key })" :aria-current="section === item.key ? 'page' : undefined">{{
                    item.title
                }}</Link></Button
            >
        </nav>
        <label v-if="section === 'seconds' && (historyProjects.length || projectFilter)" class="mb-4 grid w-fit gap-1 text-sm"
            >Проект<select class="list-select" :value="projectFilter ?? ''" @change="filterProject">
                <option value="">Все проекты</option>
                <option v-for="p in historyProjects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select></label
        >
        <EmptyState
            v-if="!history.data.length"
            :title="section === 'payments' ? 'Платежей пока нет' : 'Операций с секундами пока нет'"
            :description="
                section === 'payments' ? 'Здесь появятся ваши заказы и результаты оплаты.' : 'Здесь появятся начисления, резервы и расход секунд.'
            "
        />
        <ul v-else>
            <li v-for="row in history.data" :key="row.id" class="studio-row text-sm">
                <template v-if="'amount_kopecks' in row"
                    ><div>
                        <Link class="text-ring font-semibold hover:underline" :href="route('billing.orders.show', row.id)"
                            >Заказ №{{ row.id }} · {{ productLabel(row.product_code) }}</Link
                        >
                        <p>{{ billingDate(row.created_at) }}</p>
                    </div>
                    <div>
                        <strong>{{ money(row.amount_kopecks) }}</strong>
                        <p>{{ orderStatus(row) }}</p>
                    </div></template
                >
                <template v-else
                    ><div>
                        <strong>{{ operationLabel(row.kind) }} · {{ productLabel(row.product_code) }}</strong>
                        <p>
                            {{ billingDate(row.created_at) }} ·
                            <Link class="text-ring underline underline-offset-2" :href="route('billing.orders.show', row.order_id)"
                                >Заказ №{{ row.order_id }}</Link
                            >
                        </p>
                        <p v-if="row.project_id">
                            <Link class="text-ring underline underline-offset-2" :href="route('projects.show', row.project_id)"
                                >Проект №{{ row.project_id }}</Link
                            ><span v-if="row.production_run_id"> · Запуск №{{ row.production_run_id }}</span
                            ><span v-if="row.shot_id"> · Кадр №{{ row.shot_id }}</span>
                        </p>
                        <p v-if="row.reason">{{ row.reason }}</p>
                    </div>
                    <strong>{{ changes(row) }}</strong></template
                >
            </li>
        </ul>
        <nav v-if="history.last > 1 || history.page > 1" class="mt-4 flex flex-wrap items-center gap-3" aria-label="Страницы истории">
            <Button v-if="history.page > 1" as-child variant="outline"
                ><Link :href="route('billing.history', { tab: section, project: projectFilter ?? undefined, page: history.page - 1 })"
                    >Назад</Link
                ></Button
            ><span class="text-sm">Страница {{ history.page }}</span
            ><Button v-if="history.page < history.last" as-child variant="outline"
                ><Link :href="route('billing.history', { tab: section, project: projectFilter ?? undefined, page: history.page + 1 })"
                    >Далее</Link
                ></Button
            >
        </nav>
    </div>
</template>
