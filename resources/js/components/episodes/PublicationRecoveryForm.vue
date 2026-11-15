<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EpisodeRouteParams, PublicationSummary } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ publication: PublicationSummary; params: EpisodeRouteParams & { revision: number; assembly: number } }>();
const route = useRoute();
const form = useForm({ external_id: '' });
const errors = computed(() => Object.values(form.errors).join(' '));
function resolve() {
    form.post(route('episodes.publications.resolve', { ...props.params, publication: props.publication.id }), {
        preserveScroll: true,
        preserveState: 'errors',
    });
}
</script>

<template>
    <details class="mt-3 rounded-lg border p-3">
        <summary class="cursor-pointer text-sm font-medium">Verify an existing published video</summary>
        <p class="text-muted-foreground mt-3 text-xs">
            If this video is already visible in the connected account, enter its platform video ID. The account and video are checked without
            publishing again.
        </p>
        <form class="mt-3 grid gap-3" @submit.prevent="resolve">
            <Label :for="'publication-id-' + publication.id">Published video ID</Label
            ><Input
                :id="'publication-id-' + publication.id"
                v-model="form.external_id"
                required
                maxlength="128"
                :disabled="form.processing"
            /><InputError :message="errors" />
            <div><Button type="submit" variant="outline" class="h-11" :disabled="form.processing">Verify existing publication</Button></div>
        </form>
    </details>
</template>
