<script setup lang="ts">
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import { Button } from '@/components/ui/button';
import type { PaginatedResource } from '@/types/content';
import type { ProjectSummary } from '@/types/projects';
import { Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

const props = defineProps<{ projects: PaginatedResource<ProjectSummary>; loading: boolean }>();
const emit = defineEmits<{ page: [value: number] }>();
const route = useRoute();
const plurals = new Intl.PluralRules('ru');
function countLabel(total: number) {
    const ending = plurals.select(total);
    return total + (ending === 'one' ? ' проект' : ending === 'few' ? ' проекта' : ' проектов');
}
</script>

<template>
    <section class="studio-panel project-clipping-gallery" :aria-busy="loading">
        <h2>Клиппинг</h2>
        <div v-if="projects.data.length" class="cover-gallery">
            <Link
                v-for="project in projects.data"
                :key="project.id"
                :href="route('projects.show', project.id)"
                :aria-label="'Открыть проект ' + project.name"
                class="cover-card"
            >
                <ProjectArtwork :name="project.name" :cover="project.cover" class="cover-image" />
                <div class="cover-caption">
                    <h3>{{ project.name }}</h3>
                    <p>Клиппинг</p>
                    <p>
                        {{
                            project.cover?.source === 'upload'
                                ? 'Своя обложка'
                                : project.cover?.source === 'frame'
                                  ? 'Кадр из ролика'
                                  : 'Без изображения'
                        }}
                    </p>
                </div>
            </Link>
        </div>
        <p v-else class="text-muted-foreground">Пока нет проектов клиппинга.</p>
        <div class="gallery-total">
            <span>Тип проектов</span><strong>Клиппинг · {{ countLabel(props.projects.meta.total) }} владельца</strong>
        </div>
        <nav v-if="projects.meta.last_page > 1" class="mt-4 flex flex-wrap items-center gap-3" aria-label="Страницы проектов клиппинга">
            <Button variant="outline" :disabled="loading || projects.meta.current_page === 1" @click="emit('page', projects.meta.current_page - 1)"
                >Назад</Button
            >
            <span>{{ projects.meta.current_page }} из {{ projects.meta.last_page }}</span>
            <Button
                variant="outline"
                :disabled="loading || projects.meta.current_page === projects.meta.last_page"
                @click="emit('page', projects.meta.current_page + 1)"
                >Далее</Button
            >
        </nav>
    </section>
</template>
<style scoped>
.project-clipping-gallery {
    margin-top: 2px;
}
.project-clipping-gallery h2 {
    margin-bottom: 10px;
}
.cover-gallery {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}
.cover-card {
    display: block;
    min-width: 0;
    overflow: hidden;
    background: var(--background);
    border: 1px solid var(--border);
    border-radius: 10px;
}
.cover-card:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
.cover-image {
    width: 100%;
    height: clamp(120px, 17vw, 155px);
}
.cover-caption {
    display: grid;
    gap: 2px;
    padding: 8px 10px;
}
.cover-caption h3 {
    font-size: 16px;
    line-height: 22px;
    font-weight: 700;
    overflow-wrap: anywhere;
}
.cover-caption p,
.gallery-total span {
    color: var(--muted-foreground);
    font-size: 14px;
}
.cover-caption p {
    line-height: 19px;
}
.gallery-total {
    display: grid;
    grid-template-columns: minmax(0, 38%) minmax(0, 1fr);
    padding: 12px 0 14px;
    border-bottom: 1px solid var(--border);
    gap: 8px;
    align-items: center;
}
.gallery-total strong {
    font-weight: 500;
}
@media (min-width: 700px) and (max-width: 1099px) {
    .cover-image {
        height: 120px;
    }
}
@media (max-width: 699px) {
    .project-clipping-gallery {
        margin-top: 4px;
    }
    .project-clipping-gallery h2 {
        margin-bottom: 12px;
    }
    .cover-gallery {
        grid-template-columns: minmax(0, 1fr);
    }
    .gallery-total {
        grid-template-columns: minmax(0, 1fr);
    }
    .cover-image {
        height: 155px;
    }
}
</style>
