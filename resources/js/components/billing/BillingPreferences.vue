<script setup lang="ts">
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useRoute } from '@/composables/useRoute';
import { useFormApi } from '@/lib/api/useFormApi';
import { billingDate, money } from '@/lib/billing';
import type { BillingAccount, CreditSource, Offer } from '@/types/billing';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
const props = defineProps<{ account: BillingAccount | null; active?: CreditSource; catalog: Offer[] }>();
const route = useRoute();
const submit = useFormApi();
const editing = ref(false);
const form = useForm({ product: props.account?.next_product ?? props.active?.product_code ?? null });
const card = useForm({});
const next = computed(() => props.catalog.find((o) => o.code === props.account?.next_product));
const selected = computed(() => props.catalog.find((o) => o.code === form.product));
watch(
    () => [props.account?.next_product, props.active?.product_code],
    () => {
        form.product = props.account?.next_product ?? props.active?.product_code ?? null;
    },
);
function save(product: string | null) {
    form.product = product;
    submit(form, 'billing.subscription.next_plan', {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
    });
}
</script>
<template>
    <div class="grid gap-4 lg:col-span-2 lg:grid-cols-[1.18fr_1fr]">
        <section class="studio-panel">
            <h2>Продление</h2>
            <p v-if="!active?.ends_at" class="text-sm">
                {{ account?.renews_at ? 'Оплаченный период завершён.' : 'Нет активной месячной подписки.' }}
            </p>
            <dl v-else class="text-sm">
                <div class="flex flex-wrap justify-between gap-2">
                    <dt>Способ продления</dt>
                    <dd>Вручную</dd>
                </div>
                <div class="studio-row mt-3">
                    <dt>Период до</dt>
                    <dd>{{ billingDate(active.ends_at) }}</dd>
                </div>
                <div class="studio-row">
                    <dt>Следующий тариф</dt>
                    <dd>{{ next?.label ?? catalog.find((p) => p.code === active?.product_code)?.label }}</dd>
                </div>
            </dl>
            <template v-if="active?.ends_at">
                <form v-if="editing" class="flex flex-col items-start gap-3" @submit.prevent="save(form.product)">
                    <label class="grid gap-1 text-sm"
                        >Тариф следующего месяца<select v-model="form.product" class="list-select">
                            <option v-for="plan in catalog.filter((p) => p.monthly)" :key="plan.code" :value="plan.code">
                                {{ plan.label }} · {{ money(plan.amount) }}
                            </option>
                        </select></label
                    >
                    <p v-if="selected" class="text-muted-foreground text-sm">
                        После {{ billingDate(active.ends_at) }} можно будет вручную оплатить {{ money(selected.amount) }} за {{ selected.seconds }} с.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Button type="submit" :disabled="form.processing">Сохранить выбор</Button
                        ><Button type="button" variant="outline" @click="editing = false">Назад</Button>
                    </div>
                </form>
                <div v-else class="flex flex-wrap gap-2">
                    <Button variant="outline" @click="editing = true">Сменить тариф</Button
                    ><Button v-if="next" variant="ghost" :disabled="form.processing" @click="save(null)">Отменить смену</Button>
                </div>
                <InputError :message="Object.values(form.errors).join(' ')"
            /></template>
            <Button v-else-if="account?.renews_at" as-child variant="outline" class="mt-3"
                ><Link :href="route('billing.checkout', { product: next?.code ?? account.current_product ?? 'author' })"
                    >Продлить вручную</Link
                ></Button
            >
        </section>
        <section class="studio-panel">
            <h2>Способ оплаты</h2>
            <p class="text-sm">
                {{
                    account?.has_card
                        ? 'Банковская карта' + (account.payment_method_last_four ? ' ·· ' + account.payment_method_last_four : '')
                        : 'Сохранённых карт нет'
                }}
            </p>
            <ConfirmationDialog
                v-if="account?.has_card"
                heading="Отвязать карту?"
                title="Карта будет удалена из способов оплаты"
                description="Оплаченные секунды останутся на балансе."
                action="Отвязать карту"
                :processing="card.processing"
                :error="Object.values(card.errors).join(' ')"
                @confirm="submit(card, 'billing.payment_method.destroy', { preserveScroll: true })"
                ><template #trigger><Button variant="outline" class="mt-4">Отвязать карту</Button></template></ConfirmationDialog
            >
        </section>
    </div>
</template>
