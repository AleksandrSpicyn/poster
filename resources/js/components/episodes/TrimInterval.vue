<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
const props = defineProps<{ shotId: number; duration: number; thumbnail?: string }>();
const start = defineModel<number>('start', { required: true });
const end = defineModel<number>('end', { required: true });
const valid = ref<[number, number]>([0, props.duration]);
const track = ref<HTMLElement | null>(null);
const focused = ref<0 | 1 | null>(null);
const seconds = new Intl.NumberFormat('ru', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
const drag = ref<{ side: 0 | 1; id: number } | null>(null);
const bounded = (n: number) => Number.isInteger(n) && n >= 0 && n <= props.duration;
const error = computed(() =>
    !bounded(start.value) || !bounded(end.value)
        ? 'Введите целые значения от 0 до ' + props.duration + ' мс.'
        : end.value <= start.value
          ? 'Конец должен быть позже начала.'
          : '',
);
watchEffect(() => {
    if (!error.value) valid.value = [start.value, end.value];
});
const bounds = (side: 0 | 1) => (side === 0 ? [0, valid.value[1] - 1] : [valid.value[0] + 1, props.duration]);
function change(side: 0 | 1, n: number) {
    const [min, max] = bounds(side);
    const value = Math.min(max, Math.max(min, Math.round(n)));
    start.value = side === 0 ? value : valid.value[0];
    end.value = side === 1 ? value : valid.value[1];
}
function key(event: KeyboardEvent, side: 0 | 1) {
    const step = event.shiftKey ? 100 : 1;
    const values: Record<string, number> = {
        Home: 0,
        End: props.duration,
        ArrowLeft: valid.value[side] - step,
        ArrowDown: valid.value[side] - step,
        ArrowRight: valid.value[side] + step,
        ArrowUp: valid.value[side] + step,
    };
    if (!(event.key in values)) return;
    event.preventDefault();
    change(side, values[event.key]);
}
function position(event: PointerEvent) {
    const rect = track.value!.getBoundingClientRect();
    return (props.duration * (event.clientX - rect.left)) / Math.max(1, rect.width);
}
function press(event: PointerEvent) {
    if (event.button !== 0 || drag.value || !track.value) return;
    const n = position(event);
    const handle = event.target instanceof HTMLElement ? event.target.closest<HTMLElement>('[role=slider]') : null;
    const side: 0 | 1 = handle ? (handle.dataset.side === '1' ? 1 : 0) : Math.abs(n - valid.value[1]) < Math.abs(n - valid.value[0]) ? 1 : 0;
    drag.value = { side, id: event.pointerId };
    track.value.setPointerCapture(event.pointerId);
    track.value.querySelector<HTMLElement>('[data-side="' + side + '"]')?.focus();
    if (!handle) change(side, n);
}
function move(event: PointerEvent) {
    if (drag.value?.id === event.pointerId) change(drag.value.side, position(event));
}
function release(event: PointerEvent) {
    if (drag.value?.id === event.pointerId) drag.value = null;
}
</script>
<template>
    <div class="space-y-3 sm:col-span-3">
        <div class="flex items-center justify-between gap-3">
            <h3 class="font-semibold">Границы фрагмента</h3>
            <span class="bg-sidebar-accent text-primary rounded px-2 py-1 text-sm font-bold max-sm:hidden"
                >{{ seconds.format((valid[1] - valid[0]) / 1000) }} с</span
            >
        </div>
        <div aria-hidden="true" class="text-muted-foreground flex justify-between text-xs">
            <span v-for="tick in [0, 1, 2, 3, 4]" :key="tick">{{ ((duration * tick) / 4000).toLocaleString('ru') }} с</span>
        </div>
        <div
            ref="track"
            class="trim-track"
            :style="{ '--trim-start': (valid[0] / duration) * 100 + '%', '--trim-end': (valid[1] / duration) * 100 + '%' }"
            @pointerdown="press"
            @pointermove="move"
            @pointerup="release"
            @pointercancel="release"
            @lostpointercapture="release"
        >
            <div class="strip" :style="thumbnail ? { backgroundImage: 'url(' + JSON.stringify(thumbnail) + ')' } : {}"></div>
            <div class="selection" :style="{ left: (valid[0] / duration) * 100 + '%', width: ((valid[1] - valid[0]) / duration) * 100 + '%' }"></div>
            <button
                v-for="side in [0, 1] as const"
                :key="side"
                type="button"
                role="slider"
                class="handle"
                :data-side="side"
                :aria-label="side === 0 ? 'Начало фрагмента' : 'Конец фрагмента'"
                :aria-describedby="'trim-help-' + shotId"
                aria-orientation="horizontal"
                :aria-valuemin="bounds(side)[0]"
                :aria-valuemax="bounds(side)[1]"
                :aria-valuenow="valid[side]"
                :aria-valuetext="valid[side] + ' мс'"
                :style="{ left: (valid[side] / duration) * 100 + '%' }"
                @focus="focused = side"
                @blur="focused = null"
                @keydown="key($event, side)"
            ></button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <label
                >Начало, мс<input
                    :id="'trim-start-' + shotId"
                    class="studio-field w-full"
                    v-model.number="start"
                    type="number"
                    min="0"
                    :max="duration"
                    :aria-invalid="!bounded(start)"
            /></label>
            <label
                >Конец, мс<input
                    :id="'trim-end-' + shotId"
                    class="studio-field w-full"
                    v-model.number="end"
                    type="number"
                    min="1"
                    :max="duration"
                    :aria-invalid="!bounded(end) || end <= start"
            /></label>
        </div>
        <p v-if="error" role="alert" class="text-destructive">{{ error }}</p>
        <p v-if="error" class="text-muted-foreground text-sm">На шкале сохранён последний допустимый интервал: {{ valid[0] }}–{{ valid[1] }} мс.</p>
        <div v-else class="space-y-1 border-b pb-3 sm:hidden">
            <p class="text-muted-foreground text-sm">Длительность</p>
            <p>{{ seconds.format((valid[1] - valid[0]) / 1000) }} с из {{ seconds.format(duration / 1000) }} с</p>
        </div>
        <div :id="'trim-help-' + shotId" :class="error ? 'sr-only' : 'trim-help'">
            <strong v-if="focused !== null" class="text-primary hidden text-sm sm:block">{{
                focused === 0 ? 'Фокус на начале фрагмента' : 'Фокус на конце фрагмента'
            }}</strong>
            <strong class="text-sm sm:hidden">Управление границами</strong>
            <p class="text-muted-foreground text-sm">Tab — выбор границы. Стрелки — 1 мс, Shift со стрелками — 100 мс.</p>
        </div>
    </div>
</template>
<style scoped>
input[type='number'] {
    background-image: none;
    padding-inline: 12px;
}
input[aria-invalid='true'] {
    border-color: var(--destructive);
}
.trim-track {
    position: relative;
    height: 62px;
    margin: 0 22px 16px;
    touch-action: none;
    cursor: crosshair;
}
.strip {
    position: absolute;
    inset: 8px 0;
    background-color: var(--muted);
    background-size: auto 100%;
    background-repeat: repeat-x;
}
.strip::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to right,
        rgb(0 0 0 / 65%) 0 var(--trim-start),
        transparent var(--trim-start) var(--trim-end),
        rgb(0 0 0 / 65%) var(--trim-end) 100%
    );
}
.trim-help {
    padding-top: 10px;
    border-top: 1px solid var(--border);
}
@media (max-width: 639px) {
    .trim-help {
        padding: 12px;
        background: var(--muted);
        border-left: 3px solid var(--muted-foreground);
        border-top: 0;
        border-radius: 5px;
    }
    .trim-track {
        margin-inline: 10px;
    }
}
.selection {
    position: absolute;
    top: 5px;
    bottom: 5px;
    border: 3px solid var(--ring);
    pointer-events: none;
}
.handle {
    width: 28px;
    height: 100%;
    position: absolute;
    top: 0;
    transform: translateX(-50%);
    cursor: ew-resize;
    touch-action: none;
}
.handle::before {
    content: '';
    position: absolute;
    width: 9px;
    inset: 3px auto 3px calc(50% - 4px);
    background: var(--primary);
    border: 1px solid var(--ring);
    border-radius: 4px;
}
.handle:focus-visible {
    z-index: 2;
    outline: 2px solid var(--ring);
    outline-offset: 2px;
    border-radius: 5px;
}
@media (pointer: coarse) {
    .handle {
        width: 44px;
    }
}
</style>
