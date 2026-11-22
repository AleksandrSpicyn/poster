<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
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

function updatePlayer(event: Event) {
    const video = event.currentTarget;
    if (!(video instanceof HTMLVideoElement)) return;
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
        <DialogContent class="studio-confirm max-h-[90svh] overflow-y-auto" :show-close="false">
            <DialogHeader>
                <DialogTitle>Предпросмотр видео</DialogTitle>
                <DialogDescription class="break-words">{{ prompt.title }}</DialogDescription>
            </DialogHeader>
            <video
                controls
                playsinline
                preload="metadata"
                class="mx-auto max-h-[48svh] w-full rounded-lg bg-black"
                :aria-label="prompt.title"
                @loadedmetadata="updatePlayer"
                @timeupdate="updatePlayer"
                @durationchange="updatePlayer"
                @error="clearPlayer"
            >
                <source :src="prompt.url" type="video/mp4" />
                Браузер не поддерживает воспроизведение видео.
            </video>
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
            <DialogClose as-child><Button variant="outline">Закрыть просмотр</Button></DialogClose>
        </DialogContent>
    </Dialog>
    <span v-else class="text-muted-foreground text-xs">Видео ещё не готово</span>
</template>
