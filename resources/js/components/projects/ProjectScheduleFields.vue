<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProjectFormProps, ProjectSchedule } from '@/types/projects';
import { computed } from 'vue';

const props = defineProps<{
    saved?: ProjectFormProps['scheduleSettings'];
    disabled: boolean;
    isSeries: boolean;
    errors: Record<string, string | undefined>;
}>();
const schedule = defineModel<ProjectSchedule>({ required: true });
function changeSchedule(patch: Partial<ProjectSchedule>) {
    const { enabled, time, timezone } = schedule.value;
    schedule.value = { enabled, time, timezone, ...patch };
}
const nextRunLabel = computed(() => {
    const saved = props.saved;
    const current = schedule.value;
    if (!saved) return '';
    if (!current.enabled) return 'Выключен';
    if (saved.time !== current.time || saved.timezone !== current.timezone || saved.enabled !== current.enabled) return 'После сохранения';
    return saved.next
        ? new Intl.DateTimeFormat('ru', { timeZone: saved.timezone, day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }).format(
              new Date(saved.next),
          )
        : 'Не запланирован';
});
</script>

<template>
    <fieldset class="grid gap-4" :disabled="disabled">
        <legend v-if="isSeries" class="mb-3 font-semibold">Расписание производства</legend>
        <label class="flex items-center gap-3 rounded-lg border p-3">
            <input type="checkbox" :checked="schedule.enabled" @change="changeSchedule({ enabled: ($event.target as HTMLInputElement).checked })" />
            <span><strong class="block">Автоматический запуск</strong><small>По расписанию проекта</small></span>
        </label>
        <div class="grid gap-2">
            <Label for="project-start-time">Время запуска</Label>
            <Input
                id="project-start-time"
                type="time"
                required
                :aria-invalid="!!errors['schedule.time']"
                :aria-describedby="errors['schedule.time'] ? 'schedule-time-error' : undefined"
                :model-value="schedule.time"
                @update:model-value="changeSchedule({ time: String($event) })"
            />
            <InputError id="schedule-time-error" :message="errors['schedule.time']" />
        </div>
        <div class="grid gap-2">
            <Label for="project-timezone">Часовой пояс</Label>
            <select
                id="project-timezone"
                class="list-select"
                :aria-invalid="!!errors['schedule.timezone']"
                :aria-describedby="errors['schedule.timezone'] ? 'schedule-timezone-error' : undefined"
                :value="schedule.timezone"
                @change="changeSchedule({ timezone: ($event.target as HTMLSelectElement).value })"
            >
                <option v-for="timezone in saved?.timezones" :key="timezone">{{ timezone }}</option></select
            ><InputError id="schedule-timezone-error" :message="errors['schedule.timezone']" />
        </div>
        <p class="flex justify-between gap-3">
            <span>Следующий запуск</span><output>{{ nextRunLabel }}</output>
        </p>
    </fieldset>
</template>
