<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useFormApi } from '@/lib/api/useFormApi';
import type { SeriesSummary } from '@/types/series';
import type { EpisodeWorkspaceProps } from '@/types/episodes';
import { Button } from '@/components/ui/button';
const props = defineProps<{ series: SeriesSummary; episode: EpisodeWorkspaceProps['episode'] }>();
const submit = useFormApi();
const season = useForm({ lock_version: props.episode.lock_version });
function importEpisode(assetId: number) {
    season.lock_version = props.episode.lock_version;
    submit(season, ['episodes.scripts.season', { series: props.series.id, episode: props.episode.id, assetId }], {
        preserveScroll: true,
        preserveState: 'errors',
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
                    v-if="asset.format === 'json' && asset.kind === 'season'"
                    type="button"
                    variant="outline"
                    :disabled="season.processing"
                    @click="importEpisode(asset.id)"
                    >Импортировать эпизод {{ props.episode.number }} в новую версию</Button
                >
            </li>
        </ul>
        <p role="alert">{{ Object.values(season.errors).join(' ') }}</p>
    </details>
</template>
