<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    assembly: number;
    number: number;
    next: number;
    time: number;
    version: number;
    editable: boolean;
    saved?: { notes: string; reviewed: boolean };
}>();
const emit = defineEmits<{ play: [time: number]; saved: [] }>();
const route = useRoute();
const open = ref(false);
const form = useForm({ lock_version: props.version, notes: props.saved?.notes ?? '', reviewed: props.saved?.reviewed ?? false });
const errors = computed<Record<string, string | undefined>>(() => form.errors);
function show() {
    if (!open.value && !form.isDirty) form.lock_version = props.version;
    open.value = true;
    emit('play', props.time);
}
watch(
    () => props.saved,
    (saved) => {
        if (!form.isDirty && saved) {
            form.defaults({ lock_version: props.version, notes: saved.notes, reviewed: saved.reviewed });
            form.reset();
        }
    },
);
function save() {
    form.put(route('series_assemblies.boundaries.update', { assembly: props.assembly, boundary: props.number }), {
        preserveScroll: true,
        onSuccess: () => {
            form.lock_version = props.version;
            form.defaults();
            emit('saved');
        },
    });
}
</script>
<template>
    <div>
        <Button type="button" variant="outline" :aria-expanded="open" :aria-controls="'boundary-' + number" @click="show">
            Эпизоды {{ number }} → {{ next }}
        </Button>
        <form v-if="open" :id="'boundary-' + number" class="mt-3 grid gap-3 rounded-lg border p-4" @submit.prevent="save">
            <h3 class="font-semibold">Стык {{ number }} → {{ next }}</h3>
            <p class="text-muted-foreground text-sm">Граница {{ time.toLocaleString('ru', { maximumFractionDigits: 3 }) }} с</p>
            <Label :for="'boundary-notes-' + number">Заметка по стыку</Label>
            <textarea
                :id="'boundary-notes-' + number"
                v-model="form.notes"
                maxlength="2000"
                :readonly="!editable"
                class="border-input bg-background min-h-20 rounded-lg border p-3"
            />
            <InputError :message="form.errors.notes" />
            <label class="flex items-center gap-2"><input v-model="form.reviewed" type="checkbox" :disabled="!editable" />Стык проверен</label>
            <InputError :message="errors.review || form.errors.lock_version" />
            <p v-if="form.recentlySuccessful" role="status">Проверка сохранена.</p>
            <Button v-if="editable" type="submit" class="justify-self-start" :disabled="form.processing">Сохранить проверку</Button>
        </form>
    </div>
</template>
