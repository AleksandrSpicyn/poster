<script setup lang="ts">
import { useFormApi } from '@/lib/api/useFormApi';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EpisodeRouteParams, PublicationSummary } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    publication: PublicationSummary;
    expanded?: boolean;
    params: EpisodeRouteParams & { revision: number; assembly: number };
}>();
const submitForm = useFormApi();
const form = useForm({ external_id: '' });
const errors = computed(() => Object.values(form.errors).join(' '));
function resolve() {
    submitForm(form, ['episodes.publications.resolve', { ...props.params, publication: props.publication.id }], {
        preserveScroll: true,
        preserveState: 'errors',
        onSuccess: () => window.location.replace('#release'),
    });
}
</script>

<template>
    <component :is="expanded ? 'section' : 'details'" class="mt-3">
        <summary v-if="!expanded" class="cursor-pointer text-sm font-medium">Сверить результат отправки</summary>
        <p class="text-muted-foreground mt-3 text-xs">
            Если ролик уже появился в подключённом аккаунте, введите ссылку или ID. Poster сверит его с площадкой.
        </p>
        <form class="mt-3 grid gap-3" @submit.prevent="resolve">
            <Label :for="'publication-id-' + publication.id">Ссылка или ID публикации</Label
            ><Input
                :id="'publication-id-' + publication.id"
                v-model="form.external_id"
                required
                maxlength="2048"
                :disabled="form.processing"
            /><InputError :message="errors" />
            <div><Button type="submit" variant="outline" class="h-11" :disabled="form.processing">Проверить публикацию</Button></div>
        </form>
    </component>
</template>
