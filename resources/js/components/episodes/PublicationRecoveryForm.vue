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
        onSuccess: () => window.location.replace('#release'),
    });
}
</script>

<template>
    <details class="mt-3 rounded-lg border p-3">
        <summary class="cursor-pointer text-sm font-medium">Сверить результат отправки</summary>
        <p class="text-muted-foreground mt-3 text-xs">
            Если ролик уже появился в подключённом аккаунте, введите его идентификатор. Poster сверит его с площадкой.
        </p>
        <form class="mt-3 grid gap-3" @submit.prevent="resolve">
            <Label :for="'publication-id-' + publication.id">Идентификатор опубликованного видео</Label
            ><Input
                :id="'publication-id-' + publication.id"
                v-model="form.external_id"
                required
                maxlength="128"
                :disabled="form.processing"
            /><InputError :message="errors" />
            <div><Button type="submit" variant="outline" class="h-11" :disabled="form.processing">Проверить публикацию</Button></div>
        </form>
    </details>
</template>
