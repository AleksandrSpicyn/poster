<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProjectSummary } from '@/types/projects';
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    id: number;
    title: string;
    channel: string;
    platform: string;
    project: Pick<ProjectSummary, 'name' | 'cover'>;
    assemblyNumber: number | null;
    submittedAt: string | null;
}>();
const route = useRoute();
const tabs = ['Запрос отправлен', 'Ответ потерян', 'Сверка вручную'] as const;
const tab = ref<(typeof tabs)[number]>(tabs[0]);
const anchors = ['sent', 'response', 'manual'] as const;
function restoreTab() {
    tab.value = tabs[anchors.findIndex((anchor) => '#' + anchor === window.location.hash)] ?? tabs[0];
}
function selectTab(item: (typeof tabs)[number]) {
    window.location.hash = anchors[tabs.indexOf(item)];
}
onMounted(() => {
    restoreTab();
    window.addEventListener('hashchange', restoreTab);
});
onUnmounted(() => window.removeEventListener('hashchange', restoreTab));
const form = useForm({ external_id: '' });
function verify() {
    form.post(route('posts.requests.resolve', props.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Публикации', href: route('posts') }]">
        <Head title="Сверка операции" />
        <div class="workspace-page review" :class="{ detail: tab !== tabs[0] }">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    :title="tab === tabs[0] ? 'Сверка операции' : 'Сверка публикации'"
                    :description="tab === tabs[0] ? 'Результат отправки уточняется' : tab"
                />
                <Button v-if="tab === tabs[0]" form="publication-review" type="submit" :disabled="form.processing">Сохранить результат</Button>
            </header>
            <div v-if="tab === tabs[0]" class="review-main">
                <nav aria-label="Ход отправки" class="studio-panel flex flex-wrap gap-2 min-[1100px]:grid">
                    <h2 class="hidden font-bold min-[1100px]:block">В этом разделе</h2>
                    <button
                        v-for="item in tabs"
                        :key="item"
                        class="rounded-md px-3 py-2 text-left"
                        :class="{ 'bg-accent': tab === item }"
                        :aria-current="tab === item ? 'step' : undefined"
                        @click="selectTab(item)"
                    >
                        {{ item }}
                    </button>
                </nav>
                <form id="publication-review" class="studio-panel studio-form" @submit.prevent="verify">
                    <h2>Параметры</h2>
                    <Label for="title">Публикация</Label><Input id="title" :model-value="title" readonly /> <Label for="channel">Канал</Label
                    ><Input id="channel" :model-value="channel" readonly /> <Label for="decision">Решение</Label
                    ><Input id="decision" model-value="Проверить на площадке" readonly /> <Label for="reference">Ссылка</Label
                    ><Input id="reference" v-model="form.external_id" maxlength="2048" required placeholder="Ссылка или ID публикации" />
                    <p v-if="Object.keys(form.errors).length" role="alert">{{ Object.values(form.errors).join(' ') }}</p>
                </form>
            </div>
            <div v-else class="review-detail">
                <section v-if="tab === tabs[1]" class="studio-panel">
                    <h2>Отправка видео</h2>
                    <div class="review-notice">
                        <strong>Ответ потерян</strong>
                        <p>Запрос отправлен. Площадка не вернула окончательный результат.</p>
                    </div>
                    <p class="studio-row">
                        <span>Назначение</span><strong>{{ channel }} · {{ platform }}</strong>
                    </p>
                    <p v-if="submittedAt" class="studio-row">
                        <span>Последняя отправка</span><time :datetime="submittedAt">{{ new Date(submittedAt).toLocaleString('ru') }}</time>
                    </p>
                    <div class="review-notice">
                        <strong>Повторная отправка приостановлена</strong>
                        <p>Сначала проверьте результат, чтобы избежать дубликата.</p>
                    </div>
                    <Button class="mt-4" @click="selectTab(tabs[2])">Сверить вручную</Button>
                </section>
                <form v-else class="studio-panel studio-form" @submit.prevent="verify">
                    <h2>Сверка вручную</h2>
                    <div class="review-notice">
                        <strong>Проверьте аккаунт на площадке</strong>
                        <p>Найдите ролик именно в выбранном аккаунте.</p>
                    </div>
                    <p class="studio-row">
                        <span>Аккаунт назначения</span><strong>{{ channel }} · {{ platform }}</strong>
                    </p>
                    <Label for="manual-reference">Ссылка или ID опубликованного видео</Label
                    ><Input id="manual-reference" v-model="form.external_id" required maxlength="2048" />
                    <p class="bg-muted p-3">Poster запросит состояние этой публикации. Видео повторно не отправляется.</p>
                    <p v-if="Object.keys(form.errors).length" role="alert">{{ Object.values(form.errors).join(' ') }}</p>
                    <Button type="submit" :disabled="form.processing">Проверить публикацию</Button>
                </form>
                <aside class="studio-panel grid gap-2">
                    <ProjectArtwork :name="project.name" :cover="project.cover" class="aspect-[3/1] rounded-md" />
                    <h2>{{ title }}</h2>
                    <small>Проект</small><span>{{ project.name }}</span>
                    <template v-if="assemblyNumber !== null"
                        ><small>Принятая сборка</small><span>Версия {{ String(assemblyNumber).padStart(2, '0') }}</span></template
                    >
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.review-main,
.review-detail {
    display: grid;
    gap: 16px;
    align-items: start;
}
.review-main .studio-form {
    display: block;
    max-width: none;
    min-height: 468px;
}
.review-main .studio-form label {
    display: block;
    color: var(--muted-foreground);
    margin: 12px 0 6px;
    line-height: 20px;
    font-weight: 400;
}
.review-main .studio-form h2 {
    margin-bottom: 24px;
}
.review .studio-form input {
    height: 48px;
    background: var(--background);
    font-size: 16px;
}
.review header button {
    min-height: 44px;
    font-weight: 700;
}
.review-main nav {
    gap: 6px;
}
.review-main nav button {
    padding: 10px 14px;
    line-height: 20px;
    font-size: 14px;
}
.review-main nav [aria-current] {
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 14%, var(--card));
}
.review-detail {
    gap: 20px;
}
.review-detail .studio-form {
    max-width: none;
}
.review-notice {
    padding: 12px 16px;
    border-left: 3px solid #c58c60;
    background: var(--muted);
    border-radius: 5px;
}
.review-notice p {
    color: var(--muted-foreground);
    margin-top: 4px;
}
@media (min-width: 1100px) {
    .review-main {
        grid-template-columns: 276px minmax(0, 852px);
    }
    .review-main nav {
        min-height: 240px;
        align-content: start;
    }
    .review-detail {
        grid-template-columns: minmax(0, 2.36fr) minmax(0, 1fr);
    }
}
@media (min-width: 1920px) {
    .review-main {
        grid-template-columns: 276px minmax(0, 820px);
    }
    .review-detail {
        max-width: 1236px;
    }
}
@media (max-width: 1099px) {
    .review header {
        flex-direction: column;
    }
    .review-main nav {
        padding: 0;
        border: none;
        background: transparent;
    }
    .review-main nav button {
        background: var(--muted);
    }
    .review-detail aside {
        display: none;
    }
}
</style>
