<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useRoute } from '@/composables/useRoute';
import { fetchBillingUsage } from '@/lib/api/billing';
import { Link } from '@inertiajs/vue3';
import { onUnmounted, ref } from 'vue';
const props = defineProps<{ kind: 'shot' | 'run'; id: number }>();
const route = useRoute();
const data = ref<Awaited<ReturnType<typeof fetchBillingUsage>>>();
const error = ref(false);
let controller: AbortController | undefined;
async function refresh() {
    controller?.abort();
    const request = new AbortController();
    controller = request;
    error.value = false;
    data.value = undefined;
    try {
        const result = await fetchBillingUsage(route(props.kind === 'shot' ? 'billing.usage.shot' : 'billing.usage.run', props.id), request.signal);
        if (!request.signal.aborted) data.value = result;
    } catch {
        if (!request.signal.aborted) error.value = true;
    }
}
function toggle(event: Event) {
    if ((event.currentTarget as HTMLDetailsElement).open) refresh();
    else controller?.abort();
}
onUnmounted(() => controller?.abort());
</script>
<template>
    <details class="text-sm" @toggle="toggle">
        <summary class="text-ring w-fit cursor-pointer py-2 font-medium">
            {{ kind === 'shot' ? 'Расчёт секунд дубля' : 'Секунды этого запуска' }}
        </summary>
        <div class="bg-muted grid gap-2 rounded-lg p-3" aria-live="polite">
            <template v-if="data">
                <p v-if="data.estimate !== null">
                    Оценка нового дубля · <strong>{{ data.estimate }} с</strong>
                </p>
                <p v-else-if="kind === 'shot'">Для этих настроек оценка по тарифу недоступна.</p>
                <p>Доступно на балансе · {{ data.available }} с</p>
                <p v-if="data.estimate !== null && data.estimate > data.available">
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
                <p v-else class="text-muted-foreground">Резерв и списание не зарегистрированы.</p>
                <Link class="text-ring font-medium hover:underline" :href="route('billing.show')">Открыть баланс →</Link></template
            >
            <template v-else-if="error"
                ><p>Не удалось загрузить баланс.</p>
                <Button class="w-fit" variant="outline" type="button" @click="refresh">Повторить</Button></template
            >
            <p v-else>Загружаем данные…</p>
        </div>
    </details>
</template>
