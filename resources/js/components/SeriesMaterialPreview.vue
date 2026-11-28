<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { X, AudioLines } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { assetKinds, type SeriesAsset } from '@/types/series';
const props = defineProps<{ asset: SeriesAsset }>();
const emit = defineEmits<{ close: [] }>();
const dialog = ref<HTMLDialogElement>();
const player = ref<HTMLMediaElement>();
const raw = ref('');
const season = ref<{ title?: string; episodes: ({ title?: string } | null)[] }>();
const error = ref('');
const controller = new AbortController();
onMounted(async () => {
    dialog.value?.showModal();
    if (props.asset.format !== 'json') return;
    try {
        const response = await fetch(props.asset.url, { signal: controller.signal });
        if (!response.ok) throw Error();
        const source = await response.text();
        const plan = JSON.parse(source);
        if (plan.format !== 'poster.season.v1' || !Array.isArray(plan.episodes)) throw Error();
        season.value = plan;
        raw.value = source;
    } catch {
        if (!controller.signal.aborted) error.value = 'Не удалось открыть сценарий сезона.';
    }
});
onBeforeUnmount(() => {
    controller.abort();
    player.value?.pause();
});
</script>
<template>
    <dialog
        ref="dialog"
        aria-labelledby="asset-title"
        class="bg-card text-foreground fixed inset-0 m-auto max-h-[90dvh] w-[min(680px,calc(100%-2rem))] rounded-xl border backdrop:bg-black/85"
        @close="emit('close')"
    >
        <header class="flex items-center justify-between gap-3 border-b px-5 py-3">
            <h2 id="asset-title" class="text-xl font-bold wrap-anywhere">{{ asset.label }}</h2>
            <Button variant="outline" size="icon" aria-label="Закрыть просмотр" @click="dialog?.close()"><X /></Button>
        </header>
        <div class="space-y-4 p-5">
            <img
                v-if="asset.format === 'image'"
                :src="asset.url"
                :alt="asset.label"
                class="max-h-[55vh] w-full rounded-lg bg-black/90 object-contain"
            />
            <video
                v-else-if="asset.format === 'video'"
                ref="player"
                :src="asset.url"
                controls
                preload="metadata"
                playsinline
                class="max-h-[55vh] w-full rounded-lg bg-black"
                @error="error = 'Браузер не смог воспроизвести видео.'"
            />
            <template v-else-if="asset.format === 'audio'"
                ><div class="grid h-28 place-items-center rounded-lg bg-[#322c40] text-[#d6c4f5]"><AudioLines class="size-12" /></div>
                <audio
                    ref="player"
                    :src="asset.url"
                    controls
                    preload="metadata"
                    class="w-full"
                    @error="error = 'Браузер не смог воспроизвести звук.'"
            /></template>
            <template v-else-if="season"
                ><h3 class="font-bold">{{ season.title || asset.label }}</h3>
                <ol class="list-decimal space-y-1 pl-6">
                    <li v-for="(episode, i) in season.episodes" :key="i">{{ episode?.title || 'Эпизод ' + (i + 1) }}</li>
                </ol>
                <details>
                    <summary class="cursor-pointer py-3">Исходный JSON</summary>
                    <pre class="max-h-60 overflow-auto text-xs wrap-anywhere whitespace-pre-wrap">{{ raw }}</pre>
                </details></template
            >
            <p v-else-if="!error" role="status">{{ asset.format === 'json' ? 'Загрузка сценария…' : 'Предпросмотр недоступен.' }}</p>
            <p v-if="error" role="alert">{{ error }}</p>
            <p class="text-muted-foreground text-sm">
                {{ assetKinds[asset.kind] }} · {{ asset.extension.toUpperCase() }} · {{ Math.ceil(asset.size / 1024) }} КиБ
            </p>
        </div>
    </dialog>
</template>
