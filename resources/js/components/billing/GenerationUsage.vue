<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { operationLabel } from '@/lib/billing';
import { useRoute } from '@/composables/useRoute';
import { useGenerationUsage, type GenerationUsageTarget } from '@/composables/useGenerationUsage';
import { Link } from '@inertiajs/vue3';
const props = defineProps<GenerationUsageTarget>();
const route = useRoute();
const { data, error, refresh, setOpen } = useGenerationUsage(props);
function toggle(event: Event) {
    if (event.currentTarget instanceof HTMLDetailsElement) setOpen(event.currentTarget.open);
}
</script>
<template>
    <details class="text-sm" @toggle="toggle">
        <summary class="text-ring w-fit cursor-pointer py-2 font-medium">
            {{ kind === 'shot' ? 'Расчёт секунд дубля' : kind === 'production' ? 'Рассчитать резерв перед запуском' : 'Секунды этого запуска' }}
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
</template>
