<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { operationLabel } from '@/lib/billing';
import { useRoute } from '@/composables/useRoute';
import { useGenerationUsage, type GenerationUsageTarget } from '@/composables/useGenerationUsage';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
const props = defineProps<GenerationUsageTarget & { summary?: boolean; updatedAt?: string }>();

const route = useRoute();
const { data, error, refresh, setOpen } = useGenerationUsage(props);
const totals = computed(() =>
    data.value?.sources.reduce(
        (sum, source) => ({
            held: sum.held + Number(source.held),
            consumed: sum.consumed + Number(source.consumed),
        }),
        { held: 0, consumed: 0 },
    ),
);
onMounted(() => {
    if (props.summary) setOpen(true);
});
watch(
    () => props.updatedAt,
    () => {
        if (props.summary) void refresh();
    },
);
function toggle(event: Event) {
    if (props.summary) return;
    if (event.currentTarget instanceof HTMLDetailsElement) setOpen(event.currentTarget.open);
}
</script>
<template>
    <div class="min-w-0 text-sm">
        <div v-if="summary" class="mt-3" aria-live="polite">
            <template v-if="data && totals">
                <template v-if="kind === 'run'">
                    <p class="font-mono text-2xl">{{ totals.consumed }} с</p>
                    <p class="text-muted-foreground text-sm">Списано в этом запуске</p>
                    <div class="mt-3 flex items-center justify-between gap-2 border-t pt-3">
                        <span class="text-muted-foreground">В резерве</span><span class="font-mono">{{ totals.held }} с</span>
                    </div>
                </template>
                <template v-else
                    ><p class="font-mono text-2xl">{{ data.estimate === null ? '—' : data.estimate + ' с' }}</p>
                    <p class="text-muted-foreground">Расчётный резерв перед запуском</p></template
                >
            </template>
            <template v-else-if="error"
                ><p>{{ error }}</p>
                <Button class="mt-2" variant="outline" @click="refresh">Повторить</Button></template
            >
            <p v-else class="text-muted-foreground">Загружаем данные…</p>
        </div>
        <details v-if="!summary || data" class="text-sm" @toggle="toggle">
            <summary class="text-ring w-fit cursor-pointer py-2 font-medium">
                {{
                    summary
                        ? 'Подробнее'
                        : kind === 'shot'
                          ? 'Расчёт секунд дубля'
                          : kind === 'production'
                            ? 'Рассчитать резерв перед запуском'
                            : 'Секунды этого запуска'
                }}
            </summary>
            <div class="bg-muted grid gap-2 rounded-lg p-3" aria-live="polite">
                <template v-if="data">
                    <p v-if="data.estimate !== null">
                        {{ kind === 'production' ? 'Для запуска потребуется' : kind === 'run' ? 'Котировка запуска' : 'Оценка нового дубля' }} ·
                        <strong>{{ data.estimate }} с</strong>
                    </p>
                    <p v-else-if="kind === 'shot'">Для этих настроек оценка по тарифу недоступна.</p>
                    <p v-if="!data.enforced" class="text-muted-foreground">Для этого аккаунта генерация доступна без списания секунд.</p>
                    <p>Доступно на балансе · {{ data.available }} с</p>
                    <p v-if="data.enforced && kind !== 'run' && data.estimate !== null && data.estimate > data.available">
                        До расчётной длительности не хватает {{ data.estimate - data.available }} с.
                    </p>
                    <ul v-if="data.sources.length">
                        <li v-for="source in data.sources" :key="source.order_id">
                            <Link class="text-ring hover:underline" :href="route('billing.orders.show', source.order_id)"
                                >{{ source.product_label }} · №{{ source.order_id }}</Link
                            >
                            · В резерве {{ source.held }} с · Расход {{ source.consumed }} с
                        </li>
                    </ul>
                    <p v-else-if="kind !== 'production'" class="text-muted-foreground">Резерв и списание не зарегистрированы.</p>
                    <ul v-if="data.shots?.length" aria-label="Учёт по кадрам">
                        <li v-for="part in data.shots" :key="part.prompt_id ?? 'shot-' + part.shot_id">
                            Кадр №{{ part.shot_id }}<template v-if="part.prompt_id"> · дубль №{{ part.prompt_id }}</template> · {{ part.seconds }} с ·
                            {{ operationLabel(part.status) }}
                        </li>
                    </ul>
                    <Link class="text-ring font-medium hover:underline" :href="route('billing.show')">Открыть баланс →</Link></template
                >
                <template v-else-if="error"
                    ><p>{{ error }}</p>
                    <Button class="w-fit" variant="outline" type="button" @click="refresh">Повторить</Button></template
                >
                <p v-else>Загружаем данные…</p>
            </div>
        </details>
    </div>
</template>
