<script setup lang="ts">
import BillingPreferences from '@/components/billing/BillingPreferences.vue';
import { Button } from '@/components/ui/button';
import { useRoute } from '@/composables/useRoute';
import { billingDate, money } from '@/lib/billing';
import type { Balance, BillingAccount, CreditSource, Offer } from '@/types/billing';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps<{ catalogVersion: string; catalog: Offer[]; balance: Balance; account: BillingAccount | null; sources: CreditSource[] }>();
const route = useRoute();
const active = computed(() => props.sources.find((s) => s.active && s.ends_at) ?? props.sources.find((s) => s.active && s.available_seconds > 0));
const current = computed(() => props.catalog.find((o) => o.code === active.value?.product_code));
const metrics = computed(() => [
    { label: 'Доступно', value: Number(props.balance.available), color: 'var(--primary)' },
    { label: 'В резерве', value: Number(props.balance.held), color: '#a592c7' },
    { label: 'Израсходовано', value: Number(props.balance.consumed), color: 'var(--muted-foreground)' },
    { label: 'Истекло', value: Number(props.balance.expired), color: 'var(--border)' },
]);
</script>
<template>
    <div class="grid gap-4 lg:grid-cols-[1.18fr_1fr]">
        <section class="studio-panel flex flex-col items-start gap-3">
            <div class="flex w-full items-center justify-between gap-2">
                <h2 class="!mb-0 !text-base">Текущий тариф</h2>
                <span v-if="current" class="text-sidebar-accent-foreground bg-sidebar-accent rounded-full px-2.5 py-1 text-xs">Активен</span>
            </div>
            <strong class="text-3xl font-bold tracking-tight">{{ current?.label ?? 'Без подписки' }}</strong>
            <template v-if="current && active"
                ><p class="font-semibold">{{ money(active.amount_kopecks) }} {{ current.monthly ? '/ месяц' : 'однократно' }}</p>
                <p class="text-muted-foreground text-sm">
                    {{ active.ends_at ? billingDate(active.starts_at) + ' — ' + billingDate(active.ends_at) : 'Секунды действуют до использования.' }}
                </p></template
            >
            <p v-else class="text-muted-foreground max-w-md text-sm">
                Выберите пакет секунд для новых видеодублей. Баланс общий для всех ваших проектов.
            </p>
            <Button as-child class="mt-auto"
                ><Link :href="route('billing.show', { section: 'plans' })">{{ current ? 'Посмотреть тарифы' : 'Выбрать пакет' }}</Link></Button
            >
        </section>
        <section class="studio-panel flex flex-col items-start gap-4">
            <h2 class="!mb-0">Баланс секунд</h2>
            <div class="bg-muted flex h-2 w-full overflow-hidden rounded-full" aria-hidden="true">
                <span v-for="metric in metrics" :key="metric.label" :style="{ flex: metric.value, backgroundColor: metric.color }"></span>
            </div>
            <dl class="grid w-full grid-cols-2 gap-x-3 gap-y-4 sm:grid-cols-4">
                <div v-for="metric in metrics" :key="metric.label" class="flex flex-col-reverse">
                    <dt class="text-muted-foreground text-xs">{{ metric.label }}</dt>
                    <dd class="text-xl font-bold tabular-nums">{{ metric.value }} с</dd>
                </div>
            </dl>
            <Link
                class="text-ring mt-auto text-sm font-medium underline-offset-4 hover:underline"
                :href="route('billing.history', { tab: 'seconds' })"
                >История секунд →</Link
            >
        </section>
        <BillingPreferences :catalog-version="catalogVersion" :account="account" :active="active" :catalog="catalog" />
        <section v-if="sources.length" class="studio-panel lg:col-span-2">
            <h2>Пакеты секунд</h2>
            <div v-for="source in sources" :key="source.id" class="studio-row">
                <div>
                    <strong>{{ catalog.find((p) => p.code === source.product_code)?.label }}</strong>
                    <p>{{ source.ends_at ? 'До ' + billingDate(source.ends_at) : 'Без срока действия' }}</p>
                </div>
                <span>{{ source.active ? source.available_seconds : 0 }} с доступно · {{ source.held_seconds }} с в резерве</span>
            </div>
        </section>
    </div>
</template>
