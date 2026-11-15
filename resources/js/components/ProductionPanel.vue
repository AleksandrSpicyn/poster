<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { SharedData } from '@/types';
import type { ProductionRun } from '@/types/production';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Check, Circle, LoaderCircle, Play, Square } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = withDefaults(
    defineProps<{
        projectId: number;
        seriesId?: number;
        run?: ProductionRun | null;
        enabled?: boolean;
        archived?: boolean;
        projectEnabled?: boolean;
    }>(),
    { enabled: true, archived: false, projectEnabled: true },
);
const route = useRoute();
const page = usePage<SharedData>();
const form = useForm({ request_key: crypto.randomUUID(), series_id: props.seriesId ?? null });
const stopForm = useForm({});
const stages = computed(() => Object.entries(props.run?.stages ?? {}));
const currentStage = computed(() => stages.value.findIndex(([stage]) => stage === props.run?.stage));

function start() {
    form.post(route('production_runs.store', { project: props.projectId }), {
        preserveScroll: true,
        onSuccess: () => {
            form.request_key = crypto.randomUUID();
        },
    });
}
function stop() {
    if (props.run) stopForm.post(route('production_runs.stop', { production_run: props.run.id }), { preserveScroll: true });
}
</script>

<template>
    <section class="bg-card space-y-5 rounded-xl border p-5 sm:p-7" aria-labelledby="production-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="production-heading" class="text-lg font-semibold">Automatic production</h2>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{
                        seriesId
                            ? 'Characters → screenplay → images and video → montage → story memory → publication'
                            : 'Idea → first frame → video → publication'
                    }}
                </p>
            </div>
            <span class="bg-muted text-muted-foreground rounded-full px-3 py-1.5 text-xs">{{
                page.props.contentScheduleEnabled ? 'Schedule enabled' : 'Schedule off'
            }}</span>
        </div>
        <template v-if="run">
            <p role="status" aria-atomic="true" class="flex items-center gap-2 text-sm font-medium">
                <LoaderCircle v-if="run.is_active" class="size-4 shrink-0 motion-safe:animate-spin" aria-hidden="true" />
                <Check v-else-if="run.status === 'completed'" class="size-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                {{ run.status_label }} · {{ run.stage_label }}
            </p>
            <ol class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="([stage, label], index) in stages"
                    :key="stage"
                    class="flex items-center gap-2 rounded-lg border px-3 py-2.5 text-sm"
                    :class="index === currentStage && run.is_active ? 'border-primary/40 bg-primary/5' : 'text-muted-foreground'"
                    :aria-current="index === currentStage ? 'step' : undefined"
                >
                    <Check
                        v-if="index < currentStage || run.status === 'completed'"
                        class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                        aria-hidden="true"
                    />
                    <Circle v-else class="size-4 shrink-0" aria-hidden="true" />
                    <span>{{ index + 1 }}. {{ label }}</span>
                </li>
            </ol>
            <p
                v-if="run.message"
                class="bg-muted rounded-lg p-3 text-sm leading-relaxed"
                :class="run.status === 'needs_attention' ? 'text-destructive' : 'text-muted-foreground'"
            >
                {{ run.message }}
            </p>
        </template>
        <p v-else class="text-muted-foreground text-sm">
            {{
                !projectEnabled
                    ? 'Enable this project in its settings to produce new content.'
                    : seriesId && !enabled
                      ? 'Enable automation in series settings to let new episodes run through every stage.'
                      : 'Start one release now. Later, each scheduled trigger will create the next release.'
            }}
        </p>
        <div class="flex flex-wrap items-center gap-3 border-t pt-4">
            <Button
                v-if="!run?.blocks_next_run && projectEnabled && enabled && !archived"
                type="button"
                class="h-11"
                :disabled="form.processing"
                @click="start"
            >
                <LoaderCircle v-if="form.processing" class="size-4 motion-safe:animate-spin" aria-hidden="true" /><Play
                    v-else
                    class="size-4"
                    aria-hidden="true"
                />
                {{ seriesId ? 'Produce next episode' : 'Produce one video' }}
            </Button>
            <Button v-if="run?.blocks_next_run" type="button" variant="outline" class="h-11" :disabled="stopForm.processing" @click="stop"
                ><Square class="size-4" aria-hidden="true" />Stop next stages</Button
            >
            <Button v-if="run?.episode_id && run.series_id" as-child variant="outline" class="h-11"
                ><Link :href="route('episodes.show', { series: run.series_id, episode: run.episode_id })">Open episode</Link></Button
            >
            <p v-if="run?.blocks_next_run" class="text-muted-foreground text-xs">Submitted work may still finish after stopping.</p>
        </div>
        <InputError :message="Object.values(form.errors).join(' ')" /><InputError :message="Object.values(stopForm.errors).join(' ')" />
    </section>
</template>
