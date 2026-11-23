<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { formatProjectDate } from '@/lib/formatProjectDate';
import ListControls from '@/components/ListControls.vue';
import VideoPreview from '@/components/VideoPreview.vue';
import { Button } from '@/components/ui/button';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PromptsPageProps } from '@/types/content';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const route = useRoute();
const page = usePage<PromptsPageProps>();
const { filters, loading, error, applyFilters, changeSort, changePerPage, changePage, refresh } = useListFilters('prompts', () => ({
    sort: page.props.filters.sort ?? page.props.defaultSort,
    perPage: page.props.prompts.meta.per_page,
    page: page.props.prompts.meta.current_page,
    project: page.props.filters.project ?? null,
}));
const filterOpen = ref(false);

const projectName = computed(() => page.props.projects.find((p) => p.id === page.props.filters.project)?.name ?? 'Все проекты');
</script>
<template>
    <AppLayout
        :breadcrumbs="
            page.props.filters.project
                ? [
                      { title: projectName, href: route('projects.show', page.props.filters.project) },
                      { title: 'Генерации', href: page.url },
                  ]
                : [{ title: 'Генерации', href: route('prompts') }]
        "
    >
        <Head title="Генерации" />
        <div class="workspace-page">
            <header class="generation-heading">
                <Heading title="Генерации" :description="projectName + ' / Последние операции'" /><Button
                    variant="outline"
                    :aria-expanded="filterOpen"
                    @click="filterOpen = !filterOpen"
                    >Фильтры</Button
                >
            </header>
            <div v-if="filterOpen" class="studio-panel flex flex-wrap items-end gap-4">
                <label class="grid gap-1 text-sm"
                    >Проект<select v-model="filters.project" class="list-select max-w-full" :disabled="loading" @change="applyFilters">
                        <option :value="null">Все проекты</option>
                        <option v-for="project in page.props.projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select></label
                >
                <ListControls
                    :sort="filters.sort"
                    :per-page="filters.perPage"
                    :directions="page.props.sortDirections"
                    :disabled="loading"
                    @sort="changeSort"
                    @per-page="changePerPage"
                />
                <Button variant="outline" :disabled="loading" @click="refresh">Обновить</Button>
            </div>
            <section :aria-busy="loading" class="generation-list">
                <h2>Проект и операция</h2>
                <p v-if="error" role="alert">{{ error }} <Button variant="outline" @click="refresh">Повторить</Button></p>
                <p v-if="!page.props.prompts.data.length" class="text-muted-foreground">Пока нет генераций</p>
                <div v-for="prompt in page.props.prompts.data" :key="prompt.id" class="generation-row">
                    <div>
                        <strong>{{ prompt.title }}</strong>
                        <p>{{ prompt.project ?? 'Проект удалён' }} · {{ formatProjectDate(prompt.created_at) }}</p>
                    </div>
                    <span :data-stage="prompt.stage">{{ prompt.stage_label }}</span>
                    <VideoPreview v-if="prompt.url" :prompt="prompt" />
                </div>
                <nav v-if="page.props.prompts.meta.last_page > 1" class="flex items-center gap-3 py-4" aria-label="Страницы генераций">
                    <Button variant="outline" :disabled="loading || filters.page <= 1" @click="changePage(filters.page - 1)">Назад</Button
                    ><span>{{ filters.page }} из {{ page.props.prompts.meta.last_page }}</span
                    ><Button
                        variant="outline"
                        :disabled="loading || filters.page >= page.props.prompts.meta.last_page"
                        @click="changePage(filters.page + 1)"
                        >Далее</Button
                    >
                </nav>
            </section>
        </div>
    </AppLayout>
</template>
<style scoped>
.generation-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.generation-list h2 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 12px;
}
.generation-row {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
    gap: 12px;
    text-align: left;
    align-items: center;
    width: 100%;

    border-bottom: 1px solid var(--border);
    padding: 15px 0;
}
.generation-row span,
.generation-row p {
    font-size: 14px;
    color: var(--muted-foreground);
}
@media (max-width: 1099px) {
    .generation-heading {
        flex-direction: column;
    }
}
@media (max-width: 639px) {
    .generation-row {
        grid-template-columns: 1fr;
        gap: 2px;
        padding: 10px 0;
    }
}
@media (max-width: 767px) {
    .workspace-page {
        padding-block-start: 16px;
    }
}
.generation-row span[data-stage='ready'] {
    color: var(--sidebar-accent-foreground);
}
.generation-row span[data-stage='image'],
.generation-row span[data-stage='video'],
.generation-row span[data-stage='audio'],
.generation-row span[data-stage='media'] {
    color: #605588;
}
.dark .generation-row span[data-stage='image'],
.dark .generation-row span[data-stage='video'],
.dark .generation-row span[data-stage='audio'],
.dark .generation-row span[data-stage='media'] {
    color: #c7c2e0;
}
</style>
