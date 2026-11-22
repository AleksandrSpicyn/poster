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
        <div class="workspace-page">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <Heading
                    :title="tab === tabs[2] ? 'Сверка публикации' : 'Сверка операции'"
                    :description="tab === tabs[2] ? tab : 'Результат отправки уточняется'"
                />
                <Button v-if="tab === tabs[0]" form="publication-review" type="submit" :disabled="form.processing">Сохранить результат</Button>
            </header>
            <div v-if="tab !== tabs[2]" class="grid items-start gap-4 min-[1100px]:grid-cols-[minmax(220px,1fr)_3fr]">
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
                <form v-if="tab === tabs[0]" id="publication-review" class="studio-panel studio-form" @submit.prevent="verify">
                    <h2>Параметры</h2>
                    <Label for="title">Публикация</Label><Input id="title" :model-value="title" readonly /> <Label for="channel">Канал</Label
                    ><Input id="channel" :model-value="channel" readonly /> <Label for="decision">Решение</Label
                    ><Input id="decision" model-value="Проверить на площадке" readonly /> <Label for="reference">Ссылка</Label
                    ><Input id="reference" v-model="form.external_id" maxlength="2048" required placeholder="Ссылка или ID публикации" />
                    <p v-if="Object.keys(form.errors).length" role="alert">{{ Object.values(form.errors).join(' ') }}</p>
                </form>
                <section v-else class="studio-panel space-y-3">
                    <h2>Ответ потерян</h2>
                    <p>Площадка не подтвердила результат отправки.</p>
                    <time v-if="submittedAt" :datetime="submittedAt">{{ new Date(submittedAt).toLocaleString('ru') }}</time>
                    <Button @click="selectTab(tabs[2])">Сверка вручную</Button>
                </section>
            </div>
            <div v-else class="grid items-start gap-4 min-[1100px]:grid-cols-[minmax(0,2.4fr)_minmax(0,1fr)]">
                <form class="studio-panel studio-form" @submit.prevent="verify">
                    <h2>Сверка вручную</h2>
                    <div class="bg-muted border-l-2 border-amber-500 p-3">
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
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
