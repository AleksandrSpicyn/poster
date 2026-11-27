<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useFormApi } from '@/lib/api/useFormApi';
import type { SeriesSummary } from '@/types/series';
import type { EpisodeWorkspaceProps } from '@/types/episodes';
import { Button } from '@/components/ui/button';
const props = defineProps<{ series: SeriesSummary; episode?: EpisodeWorkspaceProps['episode'] }>();
const form = useForm({ label: '', kind: 'location', file: null as File | null });
const submit = useFormApi();
const season = useForm({ lock_version: props.episode?.lock_version ?? 0 });
function importEpisode(assetId: number) {
    if (!props.episode) return;
    season.lock_version = props.episode.lock_version;
    submit(season, ['episodes.scripts.season', { series: props.series.id, episode: props.episode.id, assetId }], {
        preserveScroll: true,
        preserveState: 'errors',
    });
}
function upload() {
    submit(form, ['series.assets.store', { series: props.series.id }], {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => form.reset(),
    });
}
</script>
<template>
    <details class="space-y-2 rounded border p-3">
        <summary>Материалы сериала</summary>
        <ul class="max-h-48 overflow-auto">
            <li v-for="asset in props.series.assets" :key="asset.id">
                <a :href="asset.url" target="_blank" rel="noopener" class="underline">{{ asset.label }}</a>
                <Button
                    v-if="props.episode && asset.kind === 'season'"
                    type="button"
                    variant="outline"
                    :disabled="season.processing"
                    @click="importEpisode(asset.id)"
                    >Импортировать эпизод {{ props.episode.number }} в новую версию</Button
                >
            </li>
        </ul>
        <label class="block"
            >Вид материала
            <select v-model="form.kind">
                <option value="location">Место</option>
                <option value="ensemble">Ансамбль</option>
                <option value="prop">Реквизит</option>
                <option value="insert">Готовая вставка</option>
                <option value="sound">Звук</option>
                <option value="season">Сценарий сезона (JSON)</option>
            </select></label
        >
        <label class="block">Название <input v-model="form.label" maxlength="150" class="rounded border p-2" /></label>
        <input
            aria-label="Файл материала"
            type="file"
            :accept="form.kind === 'sound' ? '.wav,.mp3' : form.kind === 'season' ? '.json' : '.png,.jpg,.jpeg,.webp'"
            @change="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
        />
        <Button type="button" variant="outline" :disabled="form.processing || !form.label.trim() || !form.file" @click="upload"
            >Сохранить материал</Button
        >
        <p role="alert">{{ [...Object.values(form.errors), ...Object.values(season.errors)].join(' ') }}</p>
    </details>
</template>
