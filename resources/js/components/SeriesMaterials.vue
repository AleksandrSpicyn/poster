<script setup lang="ts">
import { computed, ref } from 'vue';
import { AudioLines, FileJson, Film, Image as ImageIcon, Play, Search } from '@lucide/vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { assetKinds, type SeriesAsset, type SeriesSummary } from '@/types/series';
import SeriesMaterialUpload from './SeriesMaterialUpload.vue';
import SeriesMaterialPreview from './SeriesMaterialPreview.vue';

const props = defineProps<{ series: SeriesSummary }>();
const formats = { all: 'Все', image: 'Изображения', video: 'Видео', audio: 'Аудио', json: 'JSON' } as const;
const icons = { image: ImageIcon, video: Film, audio: AudioLines, json: FileJson };
const search = ref('');
const filter = ref<keyof typeof formats>('all');
const assets = computed(() => props.series.assets ?? []);
const filtered = computed(() =>
    assets.value.filter(
        (asset) =>
            (filter.value === 'all' || filter.value === asset.format) &&
            [asset.label, assetKinds[asset.kind], asset.extension]
                .join(' ')
                .toLocaleLowerCase('ru')
                .includes(search.value.trim().toLocaleLowerCase('ru')),
    ),
);
const selected = ref<SeriesAsset>();
const trigger = ref<HTMLElement>();
function open(asset: SeriesAsset, event: MouseEvent) {
    trigger.value = event.currentTarget as HTMLElement;
    selected.value = asset;
}
function close() {
    selected.value = undefined;
    trigger.value?.focus();
}
function reset() {
    search.value = '';
    filter.value = 'all';
}
</script>
<template>
    <section class="min-w-0" aria-label="Материалы сериала">
        <SeriesMaterialUpload v-if="!series.archived_at" :series-id="series.id" @saved="reset" />
        <p v-else class="bg-muted rounded-lg border p-4">Сериал в архиве. Материалы доступны для просмотра.</p>
        <div class="mt-4 mb-3 flex flex-wrap items-center justify-between gap-2 min-[700px]:mt-6 min-[700px]:mb-4">
            <div class="flex flex-wrap" role="group" aria-label="Формат материала">
                <button
                    v-for="(name, key) in formats"
                    :key="key"
                    :aria-pressed="filter === key"
                    class="aria-pressed:bg-accent aria-pressed:text-accent-foreground min-h-11 rounded-lg px-1.5 text-[13px] aria-pressed:font-bold min-[700px]:px-3 min-[700px]:text-sm"
                    @click="filter = key"
                >
                    {{ name }} <small v-if="key === 'all'" class="font-mono">{{ assets.length }}</small>
                </button>
            </div>
            <label class="relative w-full min-[700px]:w-44"
                ><Search aria-hidden="true" class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" /><Input
                    v-model="search"
                    type="search"
                    class="bg-card h-12 pl-9"
                    aria-label="Найти материал"
                    placeholder="Найти материал"
            /></label>
        </div>
        <div v-if="filtered.length" class="grid grid-cols-1 gap-4 min-[700px]:grid-cols-2 min-[1100px]:grid-cols-3 min-[1100px]:gap-5">
            <button
                v-for="asset in filtered"
                :key="asset.id"
                class="bg-card hover:border-ring min-w-0 overflow-hidden rounded-xl border text-left"
                :aria-label="asset.label + ' · открыть'"
                @click="open(asset, $event)"
            >
                <span
                    class="relative grid aspect-video place-items-center overflow-hidden border-b"
                    :class="{
                        'bg-muted': asset.format !== 'audio' && asset.format !== 'json',
                        'bg-[#322c40] text-[#d6c4f5]': asset.format === 'audio',
                        'bg-[#d9d7c5]': asset.format === 'json',
                    }"
                >
                    <img v-if="asset.format === 'image'" :src="asset.url" loading="lazy" alt="" class="size-full object-cover" />
                    <video
                        v-else-if="asset.format === 'video'"
                        :src="asset.url"
                        muted
                        preload="metadata"
                        playsinline
                        class="size-full object-cover"
                    />
                    <span
                        v-else-if="asset.format === 'json'"
                        class="w-3/4 -rotate-3 border-t-4 border-[#626951] bg-[#f4f1df] p-[8%] font-serif text-xl text-[#3b4438] shadow-md"
                        >{{ asset.label }}<span class="mt-3 block w-3/4 border-b border-current opacity-30"></span
                    ></span>
                    <AudioLines v-else-if="asset.format === 'audio'" class="size-14 -translate-x-12 opacity-60" />
                    <span
                        v-if="asset.format === 'video' || asset.format === 'audio'"
                        class="absolute grid size-11 place-items-center rounded-full border border-white/50 bg-black/70 text-white"
                        ><Play class="size-5"
                    /></span>
                    <span class="absolute bottom-2.5 left-2.5 flex items-center gap-1 rounded-md bg-black/80 px-2 py-1 font-mono text-xs text-white"
                        ><component :is="icons[asset.format]" v-if="asset.format" class="size-3.5" />{{ asset.extension.toUpperCase() }}</span
                    >
                </span>
                <span class="block p-4"
                    ><strong class="block wrap-anywhere">{{ asset.label }}</strong
                    ><span class="text-muted-foreground mt-1 flex justify-between gap-2 text-xs"
                        >{{ assetKinds[asset.kind] }} <span class="font-mono">{{ Math.ceil(asset.size / 1024) }} КиБ</span></span
                    ></span
                >
            </button>
        </div>
        <div v-else class="bg-card space-y-2 rounded-xl border p-8 text-center" role="status">
            <h2 class="font-semibold">{{ assets.length ? 'Материалы не найдены' : 'Пока нет материалов' }}</h2>
            <template v-if="assets.length"
                ><p>Попробуйте другой запрос или формат.</p>
                <Button variant="outline" @click="reset">Сбросить фильтры</Button></template
            >
        </div>
        <SeriesMaterialPreview v-if="selected" :asset="selected" @close="close" />
    </section>
</template>
