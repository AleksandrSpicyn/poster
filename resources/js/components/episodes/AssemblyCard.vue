<script setup lang="ts">
import { useFormApi } from '@/lib/api/useFormApi';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import type { ProjectSummary } from '@/types/projects';
import { platformNames } from '@/lib/analytics';
import { publicationStates } from '@/lib/publicationStatus';
import InputError from '@/components/InputError.vue';
import PublicationRecoveryForm from '@/components/episodes/PublicationRecoveryForm.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { EpisodeAssemblySummary, EpisodeRouteParams, PublicationSummary } from '@/types/episodes';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<{
    assembly: EpisodeAssemblySummary;
    section: 'preview' | 'recipients' | 'confirmation' | 'results';
    project: Pick<ProjectSummary, 'name' | 'cover'>;
    episodeTitle: string;
    episodeNumber: number;
    caption?: string | null;
    params: EpisodeRouteParams;
    channels: { id: number; name: string; type: string; type_label: string }[];
    publications: PublicationSummary[];
    currentAccepted: boolean;
    archived: boolean;
    projectEnabled: boolean;
    canPublish?: boolean;
}>();
const emit = defineEmits<{ review: [value: boolean]; navigate: [value: 'preview' | 'recipients' | 'confirmation'] }>();
const assemblyStates: Record<string, string> = {
    draft: 'Черновик',
    queued: 'В очереди',
    building: 'Идёт сборка',
    ready: 'Готова к проверке',
    accepted: 'Принята',
    failed: 'Ошибка сборки',
};
const submitForm = useFormApi();
const route = useRoute();
const params = { ...props.params, revision: props.assembly.episode_revision_id, assembly: props.assembly.id };
const acceptForm = useForm({
    lock_version: props.assembly.lock_version,
    confirmed_events: '',
    character_state: '',
    open_threads: '',
    scene_closed: false,
});
const publishForm = useForm({ channel_ids: [] as number[], caption: props.caption ?? '' });
const reviewId = ref<number | null>(null);
const mixed = computed(() => props.publications.some((item) => item.status === 'submission_unknown'));
const ordered = computed(() => props.publications.toSorted((a, b) => Number(b.status === 'published') - Number(a.status === 'published')));
watch(reviewId, (value) => emit('review', value !== null));
const confirmed = ref(false);
const reviewedVersion = ref(false);
watch([() => publishForm.channel_ids.slice(), () => publishForm.caption], () => {
    confirmed.value = false;
});
const acceptErrors = computed(() => Object.values(acceptForm.errors).join(' '));
const publishErrors = computed(() => Object.values(publishForm.errors).join(' '));
function accept() {
    if (!reviewedVersion.value) return;
    submitForm(acceptForm, ['episodes.assemblies.accept', params], {
        preserveScroll: true,
        preserveState: 'errors',
        onSuccess: () => window.location.replace('#release'),
    });
}
const available = computed(() => props.canPublish !== false && props.currentAccepted && !props.archived && props.projectEnabled);
const selected = computed(() => props.channels.filter((channel) => publishForm.channel_ids.includes(channel.id)));
function publish() {
    if (selected.value.length && confirmed.value && available.value && !publishForm.processing)
        submitForm(publishForm, ['episodes.assemblies.publish', params], {
            preserveScroll: true,
            preserveState: 'errors',
            onSuccess: () => window.location.replace('#release'),
        });
}
</script>

<template>
    <div>
        <section class="studio-panel assembly-card space-y-5" :class="{ preview: section === 'preview' }">
            <template v-if="section === 'preview' && (assembly.status !== 'ready' || archived)">
                <h3 class="font-semibold">Параметры</h3>
                <dl class="grid gap-3">
                    <div>
                        <dt>Сборка</dt>
                        <dd>Эпизод {{ String(episodeNumber).padStart(2, '0') }} · версия {{ String(assembly.number).padStart(2, '0') }}</dd>
                    </div>
                    <div>
                        <dt>Канал</dt>
                        <dd>{{ selected.map((channel) => channel.name).join(', ') || 'Получатели ещё не выбраны' }}</dd>
                    </div>
                    <div>
                        <dt>Площадка</dt>
                        <dd>{{ [...new Set(selected.map((channel) => channel.type_label))].join(', ') || '—' }}</dd>
                    </div>
                    <div>
                        <dt>Состояние</dt>
                        <dd>
                            {{
                                available && assembly.status === 'accepted'
                                    ? 'Готово к отправке'
                                    : (assemblyStates[assembly.status] ?? assembly.status)
                            }}
                        </dd>
                    </div>
                </dl>
                <div v-if="assembly.error_message" class="grid gap-2">
                    <p role="alert">Не удалось собрать видео. <a href="#montage" class="underline">Перейти к монтажу</a></p>
                    <details>
                        <summary>Подробности ошибки</summary>
                        <p class="break-words">{{ assembly.error_message }}</p>
                    </details>
                </div>
            </template>
            <form v-if="section === 'preview' && assembly.status === 'ready' && !archived" class="grid gap-4 border-t pt-5" @submit.prevent="accept">
                <h3>Приёмка версии {{ String(assembly.number).padStart(2, '0') }}</h3>
                <video
                    v-if="assembly.media.find((item) => item.collection === 'episode-video')"
                    :src="assembly.media.find((item) => item.collection === 'episode-video')?.url"
                    controls
                    preload="metadata"
                    class="h-40 max-w-full rounded"
                    aria-label="Видео принимаемой версии"
                />
                <div class="grid gap-2">
                    <Label :for="'events-' + assembly.id">События эпизода</Label
                    ><textarea
                        :id="'events-' + assembly.id"
                        v-model="acceptForm.confirmed_events"
                        required
                        maxlength="12000"
                        rows="3"
                        class="border-input bg-background focus-visible:ring-ring/50 rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="'state-' + assembly.id">Состояние героев</Label
                    ><textarea
                        :id="'state-' + assembly.id"
                        v-model="acceptForm.character_state"
                        required
                        maxlength="8000"
                        rows="3"
                        class="border-input bg-background focus-visible:ring-ring/50 rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="'threads-' + assembly.id">Незавершённые сюжетные линии</Label
                    ><textarea
                        :id="'threads-' + assembly.id"
                        v-model="acceptForm.open_threads"
                        maxlength="8000"
                        rows="2"
                        class="border-input bg-background focus-visible:ring-ring/50 rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                    />
                </div>
                <label class="flex min-h-11 items-center gap-3 text-sm"
                    ><input v-model="acceptForm.scene_closed" type="checkbox" class="size-4" />Последняя сцена завершена</label
                >
                <label class="flex gap-2"><input v-model="reviewedVersion" type="checkbox" required />Проверена эта версия</label>
                <InputError :message="acceptErrors" />
                <div>
                    <Button type="submit" class="h-11" :disabled="!reviewedVersion || acceptForm.processing"
                        >Принять версию {{ String(assembly.number).padStart(2, '0') }}</Button
                    >
                </div>
            </form>

            <template v-if="section === 'recipients'">
                <h3 class="font-semibold">Получатели</h3>
                <p class="studio-row"><strong>Готовое видео</strong>Версия {{ String(assembly.number).padStart(2, '0') }}</p>

                <template v-if="available">
                    <p v-if="!channels.length" class="text-muted-foreground">У проекта пока нет каналов публикации.</p>
                    <label v-for="channel in channels" :key="channel.id" class="flex min-h-11 items-center gap-3 rounded-lg border px-3 text-sm">
                        <input v-model="publishForm.channel_ids" type="checkbox" :value="channel.id" class="size-4" />{{ channel.name }} ·
                        {{ channel.type_label }}
                    </label>
                    <p v-if="!selected.length">Выберите получателей. Для продолжения нужен хотя бы один канал.</p>
                    <Button :disabled="!selected.length" @click="emit('navigate', 'confirmation')"
                        >Продолжить<span v-if="selected.length"> · {{ selected.length }}</span></Button
                    >
                </template>
                <p v-else>Отправка этой сборки недоступна.</p>
            </template>
            <form v-if="section === 'confirmation'" class="grid gap-3" @submit.prevent="publish">
                <h3 class="font-semibold">Проверка перед выпуском</h3>
                <div class="flex items-center gap-3">
                    <ProjectArtwork :name="project.name" :cover="project.cover" class="h-14 w-20 rounded" /><strong
                        >{{ episodeTitle }} · версия {{ String(assembly.number).padStart(2, '0') }}</strong
                    >
                </div>
                <p v-if="!available">Отправка этой сборки недоступна.</p>
                <template v-else>
                    <p v-if="!selected.length">Выберите каналы для отправки.</p>
                    <p v-else class="studio-row">
                        <strong>Получатели</strong>{{ selected.map((channel) => channel.name + ' · ' + channel.type_label).join(', ') }}
                    </p>
                    <p v-if="selected.some((channel) => channel.type === 'tiktok')">
                        Видео поступит во входящие TikTok. Чтобы опубликовать его, откройте уведомление в приложении.
                    </p>
                    <label class="grid gap-2">Подпись<input v-model="publishForm.caption" maxlength="1000" class="studio-field" /></label>
                    <label v-if="selected.length" class="flex items-center gap-2"
                        ><input v-model="confirmed" type="checkbox" required />Подтверждаю выпуск в выбранные аккаунты</label
                    >
                    <div class="flex flex-wrap gap-3">
                        <Button type="button" variant="outline" @click="emit('navigate', 'recipients')">Изменить получателей</Button>
                        <Button type="submit" :disabled="publishForm.processing || !selected.length || !confirmed"
                            >Отправить в выбранные каналы</Button
                        >
                    </div>
                </template>
                <InputError :message="publishErrors" />
            </form>
            <p v-if="section === 'results' && !publications.length">Эта сборка ещё не отправлена.</p>
            <section v-if="section === 'results' && publications.length" class="space-y-4">
                <h3>{{ reviewId ? 'Сверка вручную' : mixed ? 'Результаты' : 'Результаты по каналам' }}</h3>
                <div v-if="!reviewId" class="flex items-center gap-3">
                    <ProjectArtwork v-if="mixed" :name="project.name" :cover="project.cover" class="h-14 w-20 rounded" />
                    <div>
                        <strong v-if="!mixed">Заявки приняты</strong>
                        <p>{{ episodeTitle }} · версия {{ String(assembly.number).padStart(2, '0') }}</p>
                    </div>
                </div>
                <ul class="space-y-2 text-sm">
                    <li v-for="publication in ordered" :key="publication.id">
                        <div class="receipt-status" :class="publication.status">
                            <strong
                                >{{ publication.channel_name
                                }}<template v-if="publication.platform">
                                    · {{ platformNames[publication.platform] ?? publication.platform }}</template
                                ></strong
                            >
                            <p>
                                {{ publicationStates[publication.status] ?? publication.status
                                }}<template v-if="publication.published_at">
                                    · {{ new Date(publication.published_at).toLocaleString('ru', { timeZone: 'UTC' }) }} UTC</template
                                >
                            </p>
                        </div>
                        <a
                            v-if="publication.status === 'published' && publication.url && !reviewId"
                            :href="publication.url"
                            class="block underline"
                            target="_blank"
                            rel="noopener"
                            >Открыть публикацию</a
                        >
                        <time v-if="publication.scheduled_at" :datetime="publication.scheduled_at">{{
                            new Date(publication.scheduled_at).toLocaleString()
                        }}</time>
                        <Link
                            v-if="publication.status === 'failed' && publication.channel_id"
                            :href="route('channels.edit', publication.channel_id) + '#connection-heading'"
                            class="underline"
                            >Проверить подключение канала</Link
                        >
                        <details v-if="publication.error_message">
                            <summary>Подробности ошибки</summary>
                            <p class="break-words">{{ publication.error_message }}</p>
                        </details>
                        <template v-if="publication.status === 'submission_unknown' && publication.can_verify">
                            <Button v-if="reviewId !== publication.id" class="mt-3" @click="reviewId = publication.id"
                                >Сверить результат отправки</Button
                            >
                            <template v-else
                                ><PublicationRecoveryForm :publication="publication" :params="params" expanded />
                                <Button variant="outline" class="mt-3" @click="reviewId = null">Вернуться к результатам</Button></template
                            >
                        </template>
                    </li>
                </ul>
                <Button variant="outline" @click="router.reload({ only: ['publications'] })">Обновить статусы</Button>
            </section>
        </section>
        <details v-if="section === 'recipients'" class="mt-4">
            <summary>Сведения о сборке</summary>
            <details v-if="section === 'recipients' && assembly.status === 'accepted'" class="space-y-2 border-t pt-4">
                <summary class="cursor-pointer text-sm font-semibold">События принятой сборки</summary>
                <p class="text-sm whitespace-pre-wrap">{{ assembly.continuity_snapshot.confirmed_events }}</p>
                <p class="text-muted-foreground text-sm whitespace-pre-wrap">{{ assembly.continuity_snapshot.character_state }}</p>
                <p class="text-muted-foreground text-xs">
                    {{ assembly.continuity_snapshot.scene_closed ? 'Последняя сцена завершена.' : 'Сцена открыта для продолжения.' }}
                </p>
            </details>
            <details v-if="assembly.media.some((item) => item.collection === 'episode-video')">
                <summary class="cursor-pointer">Посмотреть видео</summary>
                <video
                    v-for="media in assembly.media.filter((item) => item.collection === 'episode-video')"
                    :key="media.id"
                    :src="media.url"
                    aria-label="Видео сборки"
                    controls
                    preload="metadata"
                    class="mt-3 max-h-96 w-full rounded-lg bg-black"
                />
            </details>
        </details>
    </div>
</template>

<style scoped>
.receipt-status {
    padding: 12px 16px;
    border-left: 3px solid var(--primary);
    border-radius: 5px;
    background: var(--muted);
}
.receipt-status.submission_unknown,
.receipt-status.failed {
    border-left-color: var(--warning);
}
.receipt-status p {
    margin-top: 4px;
    color: var(--muted-foreground);
}

.preview {
    min-height: 468px;
}
.assembly-card h3 {
    font-size: 20px;
}
dd {
    padding: 12px 14px;
    border: 1px solid var(--input);
    border-radius: 8px;
    background: var(--background);
}
.assembly-card dd {
    min-height: 48px;
}

dt {
    margin-bottom: 4px;
    color: var(--muted-foreground);
    font-size: 14px;
}
</style>
