<script setup lang="ts">
import EpisodePreview from '@/components/episodes/EpisodePreview.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { PromptSummary } from '@/types/content';
import { Play } from '@lucide/vue';
import { ref } from 'vue';

defineProps<{ prompt: PromptSummary }>();

const dimensions = ref('');
const timecode = ref('');

function clock(seconds: number) {
    const whole = Math.floor(seconds);
    return [Math.floor(whole / 60), whole % 60].map((part) => String(part).padStart(2, '0')).join(':');
}

function updatePlayer(video: HTMLVideoElement) {
    dimensions.value = video.videoWidth && video.videoHeight ? video.videoWidth + ' × ' + video.videoHeight : '';
    timecode.value = Number.isFinite(video.duration) ? clock(video.currentTime) + ' из ' + clock(video.duration) : '';
}

function clearPlayer() {
    dimensions.value = '';
    timecode.value = '';
}
</script>

<template>
    <Dialog v-if="prompt.url" @update:open="clearPlayer">
        <DialogTrigger as-child>
            <slot name="trigger"
                ><Button variant="outline" size="sm" class="h-11 sm:h-9">
                    <Play class="size-3.5" aria-hidden="true" />Предпросмотр<span class="sr-only"> · {{ prompt.title }}</span>
                </Button></slot
            >
        </DialogTrigger>
        <DialogContent class="studio-confirm generation-preview" :show-close="false">
            <DialogTitle>Предпросмотр генерации</DialogTitle>
            <EpisodePreview
                :src="prompt.url"
                :label="prompt.title"
                player-label="Проигрыватель генерации"
                play-label="Воспроизвести генерацию"
                pause-label="Приостановить генерацию"
                timeline
                @progress="updatePlayer"
            />
            <DialogDescription class="generation-caption">{{ prompt.title }}</DialogDescription>
            <dl>
                <div class="studio-row">
                    <dt>Результат генерации</dt>
                    <dd>{{ dimensions || '—' }}</dd>
                </div>
                <div class="studio-row">
                    <dt>Таймкод</dt>
                    <dd>{{ timecode || '—' }}</dd>
                </div>
            </dl>
            <DialogClose as-child><Button variant="outline" class="generation-close">Закрыть просмотр</Button></DialogClose>
        </DialogContent>
    </Dialog>
    <span v-else class="text-muted-foreground text-xs">Видео ещё не готово</span>
</template>

<style>
.generation-preview[data-slot='dialog-content'] {
    width: min(620px, calc(100% - 32px));
    padding: 18px 23px 27px;
}
.generation-preview > [data-slot='dialog-title'] {
    margin: 0 0 20px;
    font-size: 21px;
    text-align: left;
}
.generation-preview[data-slot='dialog-content'] .episode-preview video {
    height: 222px;
    border-radius: 5px;
}
.generation-preview .episode-preview {
    padding-top: 3px;
    border-color: transparent;
}
.generation-preview .preview-timeline {
    height: 14px;
}
.generation-preview .episode-preview .controls {
    height: 22px;
    gap: 8px;
    padding-bottom: 4px;
}
.generation-preview .episode-preview .controls button {
    width: 30px;
}
.generation-preview .generation-caption {
    margin: 5px 0 0;
    font-size: 14px;
    overflow-wrap: anywhere;
}
.generation-preview dl {
    margin-bottom: 14px;
}
.generation-preview .generation-close {
    justify-self: start;
    min-height: 44px;
    background: transparent;
    border-color: var(--muted-foreground);
    font-weight: 700;
}
.dark [data-slot='dialog-overlay']:has(+ .generation-preview) {
    background: rgb(16 18 21 / 74%);
}
@media (max-width: 639px) {
    .generation-preview[data-slot='dialog-content'] {
        padding: 9px 15px 18px;
    }
    .generation-preview[data-slot='dialog-content'] .episode-preview video {
        height: 178px;
    }
    .generation-preview > [data-slot='dialog-title'] {
        margin-bottom: 16px;
        font-size: 19px;
    }
    .generation-preview dl .studio-row {
        padding: 6px 0 7px;
    }
    .dark [data-slot='dialog-overlay']:has(+ .generation-preview) {
        background: linear-gradient(to bottom, rgb(16 18 21 / 74%) 0 calc(100% - 66px - env(safe-area-inset-bottom)), transparent 0);
    }
}
</style>
