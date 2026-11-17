<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    assembly: { id: number; status: string; lock_version: number; video_url: string | null; duration_ms: number | null; error_message: string | null;
        manifest: { parts: { number: number; duration_ms: number }[] }; review: { notes: string } | null };
    series: { id: number; name: string; project_id: number };
    release_started: boolean;
}>();
const route = useRoute();
const video = ref<HTMLVideoElement | null>(null);
const form = useForm({ lock_version: props.assembly.lock_version, boundaries_reviewed: false, review_notes: '' });
const errors = computed<Record<string, string | undefined>>(() => form.errors);
const boundaries = computed(() => {
    let elapsed = 0;
    return props.assembly.manifest.parts.slice(0, -1).map(part => { elapsed += part.duration_ms / 1000; return { number: part.number, time: elapsed }; });
});
function playBoundary(time: number) {
    if (video.value) { video.value.currentTime = Math.max(0, time - 2); void video.value.play(); }
}
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Project', href: route('projects.show', series.project_id) }, { title: 'Complete video', href: route('series_assemblies.show', assembly.id) }]">
        <Head :title="series.name + ' · Complete video'" />
        <div class="workspace-page max-w-4xl space-y-6">
            <Link :href="route('projects.show', series.project_id)" class="text-primary underline">Back to project</Link>
            <h1 class="text-2xl font-semibold">{{ series.name }} · Complete video</h1>
            <p class="text-muted-foreground">{{ assembly.status }} · {{ (assembly.duration_ms ?? 0) / 1000 }} seconds</p>
            <p v-if="assembly.error_message" role="alert">{{ assembly.error_message }}</p>
            <video v-if="assembly.video_url" ref="video" :src="assembly.video_url" controls preload="metadata" class="mx-auto max-h-[70vh] w-full rounded-lg bg-black" />
            <Button v-else variant="outline" @click="router.reload()">Refresh build status</Button>
            <section v-if="boundaries.length" class="space-y-3">
                <h2 class="text-lg font-semibold">Check every boundary with sound</h2>
                <p class="text-muted-foreground text-sm">Play the end of each part and the beginning of the next. Check motion, continuity, speech and sound before accepting.</p>
                <div class="flex flex-wrap gap-2"><Button v-for="boundary in boundaries" :key="boundary.number" variant="outline" @click="playBoundary(boundary.time)">Parts {{ boundary.number }} → {{ boundary.number + 1 }}</Button></div>
            </section>
            <form v-if="assembly.status === 'ready' && !release_started" class="space-y-4 rounded-lg border p-5" @submit.prevent="form.post(route('series_assemblies.accept', assembly.id), { preserveScroll: true })">
                <label class="flex items-center gap-3 text-sm"><input v-model="form.boundaries_reviewed" type="checkbox" required class="size-4" />I reviewed the full video and all boundaries with sound</label>
                <InputError :message="form.errors.boundaries_reviewed" />
                <Label for="review-notes">Review notes</Label>
                <textarea id="review-notes" v-model="form.review_notes" required maxlength="2000" class="border-input bg-background min-h-24 w-full rounded-md border p-3" />
                <InputError :message="form.errors.review_notes" /><InputError :message="errors.assembly" />
                <Button :disabled="form.processing">Accept complete video</Button>
                <p class="text-muted-foreground text-sm">The active production run will publish this accepted file to its selected channels.</p>
            </form>
            <p v-if="assembly.review" class="rounded-lg border p-4 text-sm">{{ assembly.review.notes }}</p>
            <p v-if="release_started" class="text-muted-foreground">This release is locked to its submitted file. Rebuilding cannot create another publication.</p>
        </div>
    </AppLayout>
</template>
