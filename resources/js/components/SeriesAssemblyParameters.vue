<script setup lang="ts">
import { useFormApi } from '@/lib/api/useFormApi';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { AssemblyParameters } from '@/types/series';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from '@/composables/useRoute';
defineProps<{ series: { id: number; name: string }; parameters: AssemblyParameters }>();
const submitForm = useFormApi();
const route = useRoute();
const build = useForm({});
const buildErrors = computed<Record<string, string | undefined>>(() => build.errors);
</script>
<template>
    <section class="studio-panel">
        <h2>Параметры сборки</h2>
        <div class="studio-row">
            <span>Сериал</span><strong>{{ series.name }}</strong>
        </div>
        <div v-for="part in parameters.parts" :key="part.number" class="studio-row">
            <div>
                <strong
                    >Эпизод {{ part.number }}<template v-if="part.title"> · {{ part.title }}</template></strong
                >
                <p>{{ part.ready ? 'Принята версия ' + part.version : part.id ? 'Ожидает готовности' : 'Эпизод ещё не создан' }}</p>
            </div>
            <Link v-if="!part.ready && part.id" :href="route('episodes.show', { series: series.id, episode: part.id }) + '#release'" class="underline"
                >Открыть эпизод</Link
            >
        </div>
        <p v-if="!parameters.parts.length">Чтобы собрать сериал, добавьте эпизоды и примите их монтаж.</p>
        <InputError :message="buildErrors.assembly" />
        <Button :disabled="!parameters.can_build || build.processing" @click="submitForm(build, ['series_assemblies.store', series.id])"
            >Собрать версию</Button
        >
    </section>
</template>
