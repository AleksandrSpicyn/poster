<script setup lang="ts">
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import { Button } from '@/components/ui/button';
import type { ProjectSummary } from '@/types/projects';
import { CircleCheck, CircleDashed, Clock3 } from '@lucide/vue';

defineProps<{
    receipt: { status: string; submitted_at: string | null; published_at: string | null; url: string | null };
    recipient: string | null;
    title: string;
    project: Pick<ProjectSummary, 'id' | 'name' | 'cover'> | null;
    assembly: number | null;
    busy: boolean;
    error: string;
}>();
defineEmits<{ refresh: [] }>();

const clock = new Intl.DateTimeFormat('ru', { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' });
function time(value: string) {
    return clock.format(new Date(value)) + ' UTC';
}
</script>

<template>
    <div class="receipt-layout">
        <section class="studio-panel receipt-panel" aria-label="Результат отправки">
            <h2>Получатель · {{ recipient || 'TikTok' }}</h2>
            <div class="receipt-notice" :class="{ published: receipt.status === 'published' }" role="status">
                <component :is="receipt.status === 'inbox' ? Clock3 : CircleCheck" aria-hidden="true" />
                <div>
                    <strong>{{ receipt.status === 'inbox' ? 'Доставлено во входящие' : 'Опубликовано' }}</strong>
                    <p>{{ receipt.status === 'inbox' ? 'TikTok получил видео в режиме загрузки.' : 'Площадка подтвердила размещение ролика.' }}</p>
                </div>
            </div>
            <ol class="receipt-steps">
                <li>
                    <CircleCheck class="complete" aria-hidden="true" />
                    <div>
                        <strong>{{ receipt.status === 'inbox' ? 'Отправлено во входящие' : 'Видео принято' }}</strong>
                        <p v-if="receipt.submitted_at">
                            {{ time(receipt.submitted_at) }}<template v-if="receipt.status === 'inbox'"> · файл доставлен</template>
                        </p>
                    </div>
                </li>
                <template v-if="receipt.status === 'inbox'">
                    <li>
                        <Clock3 class="active" aria-hidden="true" />
                        <div>
                            <strong>Действие автора</strong>
                            <p>Откройте входящие TikTok и завершите публикацию.</p>
                        </div>
                    </li>
                    <li>
                        <CircleDashed aria-hidden="true" />
                        <div>
                            <strong>Публичная ссылка</strong>
                            <p>Появится после подтверждённой публикации.</p>
                        </div>
                    </li>
                </template>
                <li v-else>
                    <CircleCheck class="complete" aria-hidden="true" />
                    <div>
                        <strong>Публикация подтверждена</strong>
                        <p v-if="receipt.published_at">
                            {{ time(receipt.published_at) }}<template v-if="recipient"> · {{ recipient }}</template>
                        </p>
                    </div>
                </li>
            </ol>
            <p v-if="error" role="alert" class="text-destructive">{{ error }}</p>
            <a v-if="receipt.status === 'published' && receipt.url" :href="receipt.url" target="_blank" rel="noopener noreferrer" class="receipt-link"
                ><strong>Открыть ролик на площадке ↗</strong><span>TikTok · ссылка получена</span></a
            >
            <Button v-else variant="outline" type="button" class="receipt-refresh" :aria-disabled="busy" @click="$emit('refresh')">{{
                busy ? 'Проверяем…' : 'Проверить статус'
            }}</Button>
        </section>
        <aside v-if="project" class="studio-panel receipt-project">
            <ProjectArtwork :name="project.name" :cover="project.cover" />
            <h2>{{ title }}</h2>
            <p>Проект</p>
            <strong>{{ project.name }}</strong>
            <template v-if="assembly !== null"
                ><p>Принятая сборка</p>
                <strong>Версия {{ String(assembly).padStart(2, '0') }}</strong></template
            >
        </aside>
    </div>
</template>

<style scoped>
.receipt-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 20px;
    align-items: start;
}
.receipt-panel {
    padding-block-start: 8px;
}
.receipt-panel h2 {
    margin: 0 0 19px;
}
.receipt-notice,
.receipt-steps li {
    display: grid;
    grid-template-columns: 20px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}
.receipt-notice {
    padding: 12px;
    border-left: 3px solid currentColor;
    border-radius: 6px;
    background: var(--muted);
    color: var(--progress);
}
.receipt-notice.published,
.complete,
.receipt-link strong {
    color: var(--sidebar-accent-foreground);
}
.receipt-panel svg {
    width: 20px;
    height: 20px;
    margin-top: 2px;
}
.receipt-panel p,
.receipt-link span,
.receipt-project p {
    color: var(--muted-foreground);
    font-size: 14px;
    line-height: 1.45;
}
.receipt-panel p,
.receipt-link span {
    display: block;
    margin-top: 4px;
}
.receipt-notice p {
    color: var(--muted-foreground);
}
.receipt-steps li {
    border-bottom: 1px solid var(--border);
    padding: 9px 0;
    line-height: 1.3;
}
.receipt-steps svg {
    color: var(--muted-foreground);
}
.receipt-steps .complete {
    color: var(--sidebar-accent-foreground);
}
.receipt-steps .active,
.receipt-steps li:has(.active) strong {
    color: var(--progress);
}
.receipt-refresh,
.receipt-link {
    margin-top: 16px;
}
.receipt-refresh {
    background: transparent;
    border-color: var(--input);
    min-height: 44px;
    font-weight: 700;
}
.receipt-link {
    display: block;
    width: fit-content;
}
.receipt-project {
    display: none;
}
@media (min-width: 768px) {
    .receipt-panel {
        padding-block-start: 16px;
    }
}
@media (min-width: 1100px) {
    .receipt-layout {
        grid-template-columns: minmax(0, 2.35fr) minmax(0, 1fr);
    }
    .receipt-project {
        display: block;
        padding: 16px;
    }
    .receipt-project > :first-child {
        aspect-ratio: 2.8;
        border-radius: 8px;
    }
    .receipt-project h2 {
        margin: 12px 0;
    }
    .receipt-project p {
        margin: 12px 0 0;
    }
}
@media (min-width: 1920px) {
    .receipt-layout {
        max-width: 1236px;
    }
}
</style>
