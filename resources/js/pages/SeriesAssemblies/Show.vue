<script setup lang="ts">
import SeriesAssemblyParameters from '@/components/SeriesAssemblyParameters.vue';
import type { AssemblyParameters } from '@/types/series';
import SeriesBoundaryReview from '@/components/SeriesBoundaryReview.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    assembly: {
        id: number;
        number: number;
        status: string;
        lock_version: number;
        started_at: string | null;
        video_url: string | null;
        duration_ms: number | null;
        error_message: string | null;
        manifest: { parts?: { number: number; duration_ms: number }[] };
        review: { notes?: string; boundaries?: Record<string, { notes: string; reviewed: boolean }> } | null;
    };
    series: { id: number; name: string; project_id: number; final_assembly_id: number | null; release_started_at: string | null };
    release_started: boolean;
    parameters: AssemblyParameters;
}>();
const route = useRoute();
const tabs = { assembly: 'Сборка', parameters: 'Параметры', status: 'Статус' } as const;
type Tab = keyof typeof tabs;
const tab = ref<Tab>('assembly');
const jointNumber = ref<number | null>(null);
const playOnOpen = ref(false);
function restoreTab() {
    const hash = window.location.hash.slice(1);
    const match = /^joint-(\d+)$/.exec(hash);
    jointNumber.value = match && boundaries.value.some((item) => item.number === Number(match[1])) ? Number(match[1]) : null;
    if (jointNumber.value !== null) {
        tab.value = 'status';
        return;
    }
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
const pinned = computed(() => props.release_started && props.assembly.id === props.series.final_assembly_id);
const releaseTime = new Intl.DateTimeFormat('ru', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', timeZone: 'UTC' });
watch(
    () => props.assembly.status,
    (status) => {
        if (status === 'ready') form.lock_version = props.assembly.lock_version;
    },
);
const errors = computed<Record<string, string | undefined>>(() => form.errors);
const playbackError = ref(false);
const boundaries = computed(() => {
    let elapsed = 0;
    return (props.assembly.manifest.parts ?? []).slice(0, -1).map((part) => {
        elapsed += part.duration_ms / 1000;
        return { number: part.number, time: elapsed };
    });
});
const joint = computed(() => (tab.value === 'status' ? boundaries.value.find((item) => item.number === jointNumber.value) : undefined));
function showJoint(number: number) {
    playbackError.value = false;
    playOnOpen.value = true;
    window.location.hash = 'joint-' + number;
}
function savedJoint(number: number) {
    form.lock_version = props.assembly.lock_version;
    window.history.replaceState(window.history.state, '', '#joint-' + number);
}
const boundariesComplete = computed(() => boundaries.value.every((part) => props.assembly.review?.boundaries?.['after_' + part.number]?.reviewed));
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
                    :disabled="form.processing || !boundariesComplete"
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
                    @click="
                        jointNumber = null;
                        tab = key;
                    "
                    >{{ label }}</a
                >
            </nav>
            <SeriesAssemblyParameters v-if="tab === 'parameters'" :series="series" :parameters="parameters" />
            <div v-if="joint" class="grid items-start gap-4 lg:grid-cols-[minmax(0,2.2fr)_minmax(0,1fr)]">
                <SeriesBoundaryReview
                    :key="assembly.id + '-' + joint.number"
                    :assembly="assembly.id"
                    :number="joint.number"
                    :next="joint.number + 1"
                    :time="joint.time"
                    :version="assembly.lock_version"
                    :video-url="assembly.video_url"
                    :assembly-number="assembly.number"
                    :part-count="assembly.manifest.parts?.length ?? 0"
                    :duration="assembly.duration_ms"
                    :editable="assembly.status === 'ready' && !release_started"
                    :saved="assembly.review?.boundaries?.['after_' + joint.number]"
                    @saved="savedJoint(joint.number)"
                />
                <aside class="studio-panel">
                    <video
                        v-if="assembly.video_url"
                        ref="video"
                        controls
                        preload="metadata"
                        :src="assembly.video_url"
                        aria-label="Предпросмотр стыка"
                        class="aspect-video w-full rounded-lg bg-black object-contain"
                        @loadedmetadata="playOnOpen && playBoundary(joint.time)"
                    />
                    <h2>{{ series.name }}</h2>
                    <p>Эпизод {{ joint.number + 1 }}</p>
                    <p v-if="playbackError" role="status">Воспроизведение доступно через элементы управления плеера.</p>
                </aside>
            </div>
            <section v-else-if="tab === 'status'" class="studio-panel space-y-3" aria-live="polite">
                <h2>{{ pinned ? 'Версия ' + assembly.number + ' закреплена' : statusLabels[assembly.status] }}</h2>
                <template v-if="pinned">
                    <p>В заявке на выпуск сохранена именно эта принятая сборка.</p>
                    <div v-if="series.release_started_at" class="studio-row">
                        <span>Отправка</span
                        ><time :datetime="series.release_started_at">{{ releaseTime.format(new Date(series.release_started_at)) }} UTC</time>
                    </div>
                </template>
                <p>
                    Версия {{ assembly.number }}<span v-if="assembly.duration_ms"> · {{ (assembly.duration_ms / 1000).toLocaleString('ru') }} с</span>
                </p>
                <p v-if="assembly.status === 'failed'" role="alert">Проверьте готовность эпизодов и исходные видео.</p>
                <div v-else-if="assembly.status === 'building'" class="grid gap-2">
                    <p v-if="assembly.started_at">Начата в {{ releaseTime.format(new Date(assembly.started_at)) }} UTC</p>
                    <div class="studio-row"><span>Монтаж</span><strong>Соединяются эпизоды</strong></div>
                    <div class="studio-row"><span>Проверка файла и границ</span><span>Ожидает монтажа</span></div>
                </div>
                <p v-if="release_started && !pinned">Для этого выпуска закреплена другая версия.</p>
                <video
                    v-if="assembly.video_url"
                    :src="assembly.video_url"
                    controls
                    preload="metadata"
                    aria-label="Видео итоговой версии"
                    class="max-h-72 w-full rounded-xl bg-black"
                />
                <a v-if="assembly.video_url" href="#assembly" class="underline" @click="tab = 'assembly'">{{
                    pinned ? 'Посмотреть отправленную сборку' : 'Посмотреть сборку'
                }}</a>
                <Button v-else variant="outline" @click="router.reload()">Обновить состояние сборки</Button>
            </section>
            <div v-if="tab === 'assembly'" class="grid items-start gap-4 md:grid-cols-[min(32%,380px)_minmax(0,1fr)]">
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
                            <Button
                                v-for="boundary in boundaries"
                                :key="boundary.number"
                                type="button"
                                variant="outline"
                                @click="showJoint(boundary.number)"
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
                        <p v-if="!boundariesComplete" class="text-muted-foreground text-sm">
                            Сохраните проверку каждого стыка перед приёмкой сборки.
                        </p>
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
                    <p v-if="assembly.review?.notes" class="rounded-lg border p-4 text-sm">{{ assembly.review.notes }}</p>
                    <p v-if="release_started" class="text-muted-foreground">
                        {{ pinned ? 'Для этого выпуска закреплена эта версия.' : 'Для этого выпуска закреплена другая версия.' }}
                    </p>
                    <Link :href="route('projects.show', series.project_id)" class="underline">Вернуться в проект</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
