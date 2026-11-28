<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogTitle, DialogDescription, DialogFooter, DialogTrigger } from '@/components/ui/dialog';
import { useFormApi } from '@/lib/api/useFormApi';
import { activeTake, keepShotLocation } from '@/lib/shotTakes';
import type { ShotSummary, EpisodeRouteParams, ContinuationSource } from '@/types/episodes';
const props = defineProps<{
    shot: ShotSummary;
    heading: string;
    params: EpisodeRouteParams & { revision: number; scene: number; shot: number };
    sourceOptions: ContinuationSource[];
    requestKey: string;
    archived: boolean;
    approved: boolean;
    projectEnabled: boolean;
    secondary: boolean;
}>();
const submit = useFormApi();
const open = ref(false);
const form = useForm({ source_prompt_id: null as number | null, new_take: false, request_key: props.requestKey + '-' + props.shot.id });
const reason = computed(() => {
    if (props.archived) return 'Сериал в архиве.';
    if (!props.projectEnabled) return 'Проект выключен.';
    if (!props.approved) return 'Для генерации утвердите сценарий.';
    if (props.shot.takes.some((t) => t.operations.some((op) => op.status === 'submission_unknown')))
        return 'Сначала проверьте результат у провайдера.';
    if (props.shot.takes.some(activeTake)) return 'Сначала завершите текущую попытку.';
    return '';
});
const label = computed(() => (props.shot.takes.length ? 'Создать новый дубль' : 'Создать кадр'));
function toggle(value: boolean) {
    if (form.processing) return;
    if (value) {
        form.source_prompt_id = props.shot.transition_mode === 'new_scene' ? null : props.shot.source_prompt_id;
        form.clearErrors();
    }
    open.value = value;
}
function generate() {
    if (reason.value || form.processing) return;
    form.new_take = props.shot.takes.length > 0;
    submit(form, ['shots.generate', { ...props.params }], {
        preserveScroll: true,
        preserveState: 'errors',
        onBeforeUpdate: keepShotLocation(props.shot.id),
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>
<template>
    <div class="grid justify-items-start gap-1">
        <Dialog :open="open" @update:open="toggle"
            ><DialogTrigger as-child
                ><Button :variant="secondary || reason ? 'outline' : 'default'" :disabled="!!reason || form.processing">{{
                    label
                }}</Button></DialogTrigger
            >
            <DialogContent
                ><DialogTitle>{{ label }}</DialogTitle
                ><DialogDescription class="wrap-anywhere">{{ heading }}</DialogDescription>
                <form class="grid gap-4" @submit.prevent="generate">
                    <label v-if="shot.transition_mode !== 'new_scene'" class="grid gap-2 text-sm"
                        >Откуда начать
                        <select
                            v-model="form.source_prompt_id"
                            class="bg-background border-input h-12 w-full min-w-0 rounded-lg border px-3"
                            :disabled="form.processing"
                        >
                            <option :value="null">Источник из сценария</option>
                            <option
                                v-if="form.source_prompt_id && !sourceOptions.some((source) => source.id === form.source_prompt_id)"
                                :value="form.source_prompt_id"
                                disabled
                            >
                                Сохранённый источник №{{ form.source_prompt_id }}
                            </option>
                            <option v-for="source in sourceOptions" :key="source.id" :value="source.id">{{ source.label }}</option>
                        </select>
                    </label>
                    <p class="text-muted-foreground text-sm">Провайдер может списать средства за генерацию.</p>
                    <InputError :message="Object.values(form.errors).join(' ') || reason" />
                    <DialogFooter
                        ><DialogClose as-child><Button type="button" variant="outline" :disabled="form.processing">Отмена</Button></DialogClose
                        ><Button type="submit" :disabled="!!reason || form.processing">Запустить генерацию</Button></DialogFooter
                    >
                </form>
            </DialogContent></Dialog
        >
        <p v-if="reason" class="text-muted-foreground text-xs">{{ reason }}</p>
    </div>
</template>
