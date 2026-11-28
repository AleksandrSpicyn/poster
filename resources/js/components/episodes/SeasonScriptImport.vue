<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useFormApi } from '@/lib/api/useFormApi';
import type { SeriesSummary } from '@/types/series';
import type { EpisodeWorkspaceProps } from '@/types/episodes';
import { Button } from '@/components/ui/button';
const props = defineProps<{ series: SeriesSummary; episode: EpisodeWorkspaceProps['episode']; disabled: boolean }>();
const scripts = computed(() => props.series.assets?.filter((asset) => asset.format === 'json' && asset.kind === 'season') ?? []);
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
    <section v-if="scripts.length" class="grid gap-2 border-t pt-3">
        <h3 class="text-sm font-medium">Импорт из сценария сезона</h3>
        <ul class="max-h-48 space-y-2 overflow-y-auto">
            <li v-for="asset in scripts" :key="asset.id" class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm wrap-anywhere">{{ asset.label }}</span>
                <Button type="button" variant="outline" :disabled="disabled || season.processing" @click="importEpisode(asset.id)">
                    Импортировать эпизод {{ episode.number }}
                </Button>
            </li>
        </ul>
        <p v-if="season.hasErrors" role="alert">{{ Object.values(season.errors).join(' ') }}</p>
    </section>
</template>
