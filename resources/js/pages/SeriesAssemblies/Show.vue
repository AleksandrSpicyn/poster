<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    assembly: {
        id: number;
        number: number;
        status: string;
        lock_version: number;
        video_url: string | null;
        duration_ms: number | null;
        error_message: string | null;
        manifest: { parts?: { number: number; duration_ms: number }[] };
        review: { notes: string } | null;
    };
    series: { id: number; name: string; project_id: number };
    release_started: boolean;
}>();
const route = useRoute();
const tabs = { assembly: 'Сборка', parameters: 'Параметры', status: 'Статус' } as const;
type Tab = keyof typeof tabs;
const tab = ref<Tab>('assembly');
function restoreTab() {
    const hash = window.location.hash.slice(1);
    tab.value = hash === 'parameters' || hash === 'status' ? hash : 'assembly';
}
onMounted(() => {
    restoreTab();
    window.addEventListener('hashchange', restoreTab);
});
onUnmounted(() => window.removeEventListener('hashchange', restoreTab));
const statusLabels: Record<string, string> = {
    draft: 'Черновик',
    queued: 'В очереди',
    building: 'Сборка выполняется',
    ready: 'Сборка готова',
    accepted: 'Сборка принята',
    failed: 'Не удалось собрать версию',
};
const video = ref<HTMLVideoElement | null>(null);
const form = useForm({ lock_version: props.assembly.lock_version, boundaries_reviewed: false, review_notes: '' });
const errors = computed<Record<string, string | undefined>>(() => form.errors);
const playbackError = ref(false);
const boundaries = computed(() => {
    let elapsed = 0;
    return (props.assembly.manifest.parts ?? []).slice(0, -1).map((part) => {
        elapsed += part.duration_ms / 1000;
        return { number: part.number, time: elapsed };
    });
});
async function playBoundary(time: number) {
    if (video.value) {
        video.value.currentTime = Math.max(0, time - 2);
        playbackError.value = false;
        try {
            await video.value.play();
        } catch {
            playbackError.value = true;
        }
    }
}
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Проект', href: route('projects.show', series.project_id) },
            { title: 'Сборка сериала', href: route('series_assemblies.show', assembly.id) },
        ]"
    >
        <Head :title="series.name + ' · Сборка сериала'" />
        <div class="workspace-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading title="Сборка сериала" :description="series.name + ' / Полная версия · ' + assembly.number" />
                <Button
                    v-if="tab === 'assembly' && assembly.status === 'ready' && !release_started"
                    form="assembly-review"
                    type="submit"
                    :disabled="form.processing"
                    >Принять сборку</Button
                >
            </div>
            <nav class="flex flex-wrap gap-2" aria-label="Разделы сборки">
                <a
                    v-for="(label, key) in tabs"
                    :key="key"
                    :href="'#' + key"
                    :aria-current="tab === key ? 'page' : undefined"
                    class="bg-muted aria-[current=page]:bg-sidebar-accent aria-[current=page]:text-sidebar-accent-foreground flex min-h-11 items-center rounded-lg px-4 text-sm font-semibold"
                    @click="tab = key"
                    >{{ label }}</a
                >
            </nav>
            <section v-if="tab === 'parameters'" class="studio-panel">
                <h2>Параметры сборки</h2>
                <div class="studio-row">
                    <span>Сериал</span><strong>{{ series.name }}</strong>
                </div>
                <div v-for="part in assembly.manifest.parts" :key="part.number" class="studio-row">
                    <span>Эпизод {{ part.number }}</span
                    ><span>{{ (part.duration_ms / 1000).toLocaleString('ru') }} с</span>
                </div>
                <div class="studio-row">
                    <span>Порядок</span><span>{{ assembly.manifest.parts?.map((part) => part.number).join(' → ') }}</span>
                </div>
            </section>
            <section v-if="tab === 'status'" class="studio-panel space-y-3" aria-live="polite">
                <h2>{{ statusLabels[assembly.status] }}</h2>
                <p>
                    Версия {{ assembly.number }}<span v-if="assembly.duration_ms"> · {{ (assembly.duration_ms / 1000).toLocaleString('ru') }} с</span>
                </p>
                <p v-if="assembly.status === 'failed'" role="alert">Проверьте готовность эпизодов и исходные видео.</p>
                <p v-else-if="assembly.status === 'building'">Соединяются эпизоды.</p>
                <p v-if="release_started">Для этого выпуска закреплена отправленная версия файла.</p>
                <a v-if="assembly.video_url" href="#assembly" class="underline" @click="tab = 'assembly'">Посмотреть сборку</a>
                <Button v-else variant="outline" @click="router.reload()">Обновить состояние сборки</Button>
            </section>
            <div v-show="tab === 'assembly'" class="grid items-start gap-4 md:grid-cols-[min(32%,380px)_minmax(0,1fr)]">
                <div>
                    <video
                        v-if="assembly.video_url"
                        ref="video"
                        :src="assembly.video_url"
                        controls
                        preload="metadata"
                        aria-label="Предпросмотр сборки сериала"
                        class="aspect-[3/4] w-full rounded-xl bg-black object-contain"
                    />
                    <Button v-else variant="outline" @click="router.reload()">Обновить состояние сборки</Button>
                    <p v-if="playbackError" role="alert">Не удалось запустить воспроизведение. Откройте видео через элементы управления плеера.</p>
                </div>
                <div class="studio-panel grid gap-4">
                    <p>{{ statusLabels[assembly.status] }} · {{ (assembly.duration_ms ?? 0) / 1000 }} с</p>
                    <ol class="divide-y">
                        <li v-for="part in assembly.manifest.parts ?? []" :key="part.number" class="flex justify-between gap-4 py-3">
                            <span>Эпизод {{ part.number }}</span
                            ><span>{{ (part.duration_ms / 1000).toLocaleString('ru') }} с</span>
                        </li>
                    </ol>
                    <section v-if="boundaries.length" class="space-y-3">
                        <h2 class="text-lg font-semibold">Проверка переходов</h2>
                        <p class="text-muted-foreground text-sm">
                            Перед приёмкой просмотрите стыки эпизодов со звуком. Проверьте движение, речь и последовательность событий.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button v-for="boundary in boundaries" :key="boundary.number" variant="outline" @click="playBoundary(boundary.time)"
                                >Эпизоды {{ boundary.number }} → {{ boundary.number + 1 }}</Button
                            >
                        </div>
                    </section>
                    <form
                        id="assembly-review"
                        v-if="assembly.status === 'ready' && !release_started"
                        class="space-y-4 rounded-lg border p-5"
                        @submit.prevent="form.post(route('series_assemblies.accept', assembly.id), { preserveScroll: true })"
                    >
                        <label class="flex items-center gap-3 text-sm"
                            ><input v-model="form.boundaries_reviewed" type="checkbox" required class="size-4" />Я проверил видео и переходы между
                            эпизодами со звуком</label
                        >
                        <InputError :message="form.errors.boundaries_reviewed" />
                        <Label for="review-notes">Заметки о проверке</Label>
                        <textarea
                            id="review-notes"
                            v-model="form.review_notes"
                            required
                            maxlength="2000"
                            class="border-input bg-background min-h-24 w-full rounded-md border p-3"
                        />
                        <InputError :message="form.errors.review_notes" /><InputError :message="errors.assembly" />

                        <p class="text-muted-foreground text-sm">Действующий производственный запуск отправит принятую сборку в выбранные каналы.</p>
                    </form>
                    <p v-if="assembly.review" class="rounded-lg border p-4 text-sm">{{ assembly.review.notes }}</p>
                    <p v-if="release_started" class="text-muted-foreground">Для этого выпуска закреплена отправленная версия файла.</p>
                    <Link :href="route('projects.show', series.project_id)" class="underline">Вернуться в проект</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
