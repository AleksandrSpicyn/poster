<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useRoute } from '@/composables/useRoute';
import { money } from '@/lib/billing';
import type { Offer } from '@/types/billing';
import { Link } from '@inertiajs/vue3';
defineProps<{ catalog: Offer[] }>();
const route = useRoute();
</script>
<template>
    <div class="space-y-5">
        <div class="grid gap-4 md:grid-cols-3">
            <section v-for="item in catalog" :key="item.code" class="studio-panel flex flex-col items-start gap-4">
                <h2 class="!mb-0">{{ item.label }}</h2>
                <p>
                    <strong class="text-3xl font-bold tracking-tight">{{ money(item.amount) }}</strong><span class="text-muted-foreground text-sm">
                        {{ item.monthly ? '/ месяц' : 'однократно' }}</span
                    >
                </p>
                <div>
                    <strong>{{ item.seconds }} с</strong>
                    <p class="text-muted-foreground mt-1 text-sm">{{ item.seconds / 15 }} × 15 с генерации</p>
                </div>
                <p class="text-muted-foreground text-sm">
                    {{ item.monthly ? 'Секунды действуют один оплаченный месяц.' : 'Для первой покупки. Секунды без срока действия.' }}
                </p>
                <Button as-child variant="outline" class="mt-auto w-full"
                    ><Link :href="route('billing.checkout', { product: item.code })" :aria-label="'Выбрать пакет «' + item.label + '»'"
                        >Выбрать пакет</Link
                    ></Button
                >
            </section>
        </div>
        <section class="studio-panel">
            <h2>Что входит в пакет</h2>
            <p class="text-muted-foreground text-sm">
                Секунды новых видеодублей Seedance Mini в 720p со звуком. Один пакет действует во всех проектах владельца. Монтаж и публикация
                готового видео не расходуют секунды.
            </p>
        </section>
    </div>
</template>
