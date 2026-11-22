<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { AssemblyParameters } from '@/types/series';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';
defineProps<{ series: { id: number; name: string }; parameters: AssemblyParameters }>();
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
            <label class="flex items-center gap-3"
                ><input type="checkbox" :checked="part.ready" disabled /><span
                    >Эпизод {{ part.number }}<template v-if="part.title"> · {{ part.title }}</template></span
                ></label
            >
            <span>{{ part.ready ? 'Принята версия ' + part.version : 'Ожидает готовности' }}</span>
        </div>
        <div class="studio-row">
            <span>Порядок</span><span>{{ parameters.parts.map((part) => part.number).join(' → ') }}</span>
        </div>
        <InputError :message="buildErrors.assembly" />
        <Button :disabled="!parameters.can_build || build.processing" @click="build.post(route('series_assemblies.store', series.id))"
            >Собрать версию</Button
        >
    </section>
</template>
