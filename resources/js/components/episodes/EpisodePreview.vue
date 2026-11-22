<script setup lang="ts">
import { Pause, Play } from '@lucide/vue';
import { ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        src: string;
        poster?: string;
        label?: string;
        playerLabel?: string;
        playLabel?: string;
        pauseLabel?: string;
        timeline?: boolean;
    }>(),
    {
        label: 'Предпросмотр эпизода',
        playerLabel: 'Проигрыватель эпизода',
        playLabel: 'Воспроизвести эпизод',
        pauseLabel: 'Приостановить эпизод',
    },
);
const emit = defineEmits<{ progress: [video: HTMLVideoElement] }>();
const video = ref<HTMLVideoElement | null>(null);
const duration = ref(0);
const time = ref(0);
const playing = ref(false);
const error = ref(false);
const format = (seconds: number) => [Math.floor(seconds / 60), Math.floor(seconds) % 60].map((number) => String(number).padStart(2, '0')).join(':');
watch(
    () => props.src,
    () => {
        error.value = false;
        time.value = 0;
        duration.value = 0;
        playing.value = false;
    },
);
function syncProgress() {
    if (!video.value) return;
    duration.value = Number.isFinite(video.value.duration) ? video.value.duration : 0;
    time.value = video.value.currentTime;
    emit('progress', video.value);
}
function updateControls() {
    if (video.value) video.value.controls = document.fullscreenElement === video.value;
}
async function fullscreen() {
    if (!video.value?.requestFullscreen) return;
    try {
        await video.value.requestFullscreen();
    } catch {
        /* Native media controls remain available through the browser menu. */
    }
}
function seek(seconds: number) {
    if (video.value && Number.isFinite(seconds)) video.value.currentTime = Math.max(0, Math.min(seconds, video.value.duration || 0));
}
defineExpose({ seek });
async function play() {
    if (!video.value) return;
    if (playing.value) video.value.pause();
    else {
        try {
            await video.value.play();
            error.value = false;
        } catch {
            error.value = true;
        }
    }
}
</script>
<template>
    <section class="episode-preview" :aria-label="playerLabel">
        <video
            ref="video"
            :src="src"
            :poster="poster"
            :aria-label="label"
            preload="metadata"
            playsinline
            tabindex="0"
            aria-keyshortcuts="F"
            @keyup.f="fullscreen"
            @dblclick="fullscreen"
            @fullscreenchange="updateControls"
            @loadedmetadata="syncProgress"
            @timeupdate="syncProgress"
            @playing="playing = true"
            @pause="playing = false"
            @error="error = true"
        />
        <input
            v-if="timeline"
            type="range"
            min="0"
            :max="duration"
            step="0.001"
            :value="time"
            :disabled="!duration"
            class="preview-timeline"
            aria-label="Позиция воспроизведения"
            :aria-valuetext="format(time) + ' из ' + format(duration)"
            :style="{ '--position': (duration ? (time / duration) * 100 : 0) + '%' }"
            @input="seek(Number(($event.target as HTMLInputElement).value))"
        />
        <div class="controls">
            <button type="button" :aria-label="playing ? pauseLabel : playLabel" @click="play">
                <Pause v-if="playing" aria-hidden="true" /><Play v-else aria-hidden="true" />
            </button>
            <time>{{ format(time) }}</time><span>/</span><time>{{ format(duration) }}</time>
        </div>
        <p v-if="error" role="alert">Не удалось воспроизвести видео.</p>
    </section>
</template>
<style scoped>
.episode-preview {
    width: 100%;
    overflow: hidden;
    background: #111314;
    color: #b8bcb9;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding-top: 5px;
}
.episode-preview video {
    height: 366px;
    aspect-ratio: 9/16;
    max-width: 100%;
    object-fit: contain;
    display: block;
    margin-inline: auto;
    border-radius: 7px;
}
.controls {
    display: flex;
    align-items: center;
    gap: 16px;
    height: 46px;
    font: 14px monospace;
}
.controls button {
    display: grid;
    width: 34px;
    height: 100%;
    place-items: center;
}
.controls button svg {
    width: 13px;
    height: 13px;
}
.preview-timeline {
    display: block;
    width: calc(100% - 20px);
    height: 16px;
    margin: 0 10px;
    appearance: none;
    cursor: pointer;
    background: linear-gradient(to right, var(--primary) 0 var(--position), #373c3d var(--position) 100%) center / 100% 3px no-repeat;
}
.preview-timeline::-webkit-slider-thumb {
    appearance: none;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--primary);
    opacity: 0;
}
.preview-timeline:hover::-webkit-slider-thumb,
.preview-timeline:focus-visible::-webkit-slider-thumb {
    opacity: 1;
}
.episode-preview p {
    padding: 4px 12px;
}
@media (max-width: 767px) {
    .episode-preview video {
        height: 120px;
    }
}
</style>
