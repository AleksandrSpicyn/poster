<script setup lang="ts">
import { publicationStates } from '@/lib/publicationStatus';
import InputError from '@/components/InputError.vue';
import PublicationRecoveryForm from '@/components/episodes/PublicationRecoveryForm.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type { EpisodeAssemblySummary, EpisodeRouteParams, PublicationSummary } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    assembly: EpisodeAssemblySummary;
    section: 'preview' | 'recipients' | 'confirmation';
    params: EpisodeRouteParams;
    channels: { id: number; name: string; type: string; type_label: string }[];
    publications: PublicationSummary[];
    currentAccepted: boolean;
    archived: boolean;
    projectEnabled: boolean;
    canPublish?: boolean;
}>();
const emit = defineEmits<{ navigate: [value: 'preview' | 'recipients' | 'confirmation'] }>();
const assemblyStates: Record<string, string> = {
    draft: 'Черновик',
    queued: 'В очереди',
    building: 'Идёт сборка',
    ready: 'Готова к проверке',
    accepted: 'Принята',
    failed: 'Ошибка сборки',
};
const route = useRoute();
const params = { ...props.params, revision: props.assembly.episode_revision_id, assembly: props.assembly.id };
const acceptForm = useForm({
    lock_version: props.assembly.lock_version,
    confirmed_events: '',
    character_state: '',
    open_threads: '',
    scene_closed: false,
});
const publishForm = useForm({ channel_ids: [] as number[] });
const acceptErrors = computed(() => Object.values(acceptForm.errors).join(' '));
const publishErrors = computed(() => Object.values(publishForm.errors).join(' '));
function accept() {
    acceptForm.post(route('episodes.assemblies.accept', params), {
        preserveScroll: true,
        preserveState: 'errors',
        onSuccess: () => window.location.replace('#release'),
    });
}
const available = computed(() => props.canPublish !== false && props.currentAccepted && !props.archived && props.projectEnabled);
const selected = computed(() => props.channels.filter((channel) => publishForm.channel_ids.includes(channel.id)));
function publish() {
    if (selected.value.length && available.value && !publishForm.processing)
        publishForm.post(route('episodes.assemblies.publish', params), {
            preserveScroll: true,
            preserveState: 'errors',
            onSuccess: () => window.location.replace('#release'),
        });
}
</script>

<template>
    <section class="bg-card space-y-5 rounded-xl border p-5 sm:p-7">
        <template v-if="section === 'preview'">
            <h3 class="font-semibold">Параметры</h3>
            <dl class="grid gap-3">
                <div>
                    <dt>Сборка</dt>
                    <dd>Версия {{ assembly.number }}</dd>
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
                    <dd>{{ assemblyStates[assembly.status] ?? assembly.status }}</dd>
                </div>
            </dl>
            <p v-if="assembly.error_message" role="alert" class="text-destructive text-sm">{{ assembly.error_message }}</p>
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
            <Button v-if="available" variant="outline" @click="emit('navigate', 'recipients')">Выбрать получателей</Button>
        </template>
        <form v-if="section === 'preview' && assembly.status === 'ready' && !archived" class="grid gap-4 border-t pt-5" @submit.prevent="accept">
            <p class="text-muted-foreground text-sm">Просмотрите сборку и зафиксируйте события для следующего эпизода.</p>
            <div class="grid gap-2">
                <Label :for="'events-' + assembly.id">Что произошло</Label
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
            <InputError :message="acceptErrors" />
            <div><Button type="submit" class="h-11" :disabled="acceptForm.processing">Принять сборку</Button></div>
        </form>
        <details v-if="section === 'preview' && assembly.status === 'accepted'" class="space-y-2 border-t pt-4">
            <summary class="cursor-pointer text-sm font-semibold">События принятой сборки</summary>
            <p class="text-sm whitespace-pre-wrap">{{ assembly.continuity_snapshot.confirmed_events }}</p>
            <p class="text-muted-foreground text-sm whitespace-pre-wrap">{{ assembly.continuity_snapshot.character_state }}</p>
            <p class="text-muted-foreground text-xs">
                {{ assembly.continuity_snapshot.scene_closed ? 'Последняя сцена завершена.' : 'Сцена открыта для продолжения.' }}
            </p>
        </details>
        <template v-if="section === 'recipients'">
            <h3 class="font-semibold">Получатели · версия {{ assembly.number }}</h3>
            <template v-if="available">
                <p v-if="!channels.length" class="text-muted-foreground">У проекта пока нет каналов публикации.</p>
                <label v-for="channel in channels" :key="channel.id" class="flex min-h-11 items-center gap-3 rounded-lg border px-3 text-sm">
                    <input v-model="publishForm.channel_ids" type="checkbox" :value="channel.id" class="size-4" />{{ channel.name }} ·
                    {{ channel.type_label }}
                </label>
                <Button :disabled="!selected.length" @click="emit('navigate', 'confirmation')">Перейти к подтверждению</Button>
            </template>
            <p v-else>Отправка этой сборки недоступна.</p>
        </template>
        <form v-if="section === 'confirmation'" class="grid gap-3" @submit.prevent="publish">
            <h3 class="font-semibold">Подтверждение · версия {{ assembly.number }}</h3>
            <p v-if="!available">Отправка этой сборки недоступна.</p>
            <template v-else>
                <p v-if="!selected.length">Выберите каналы для отправки.</p>
                <ul v-else>
                    <li v-for="channel in selected" :key="channel.id">{{ channel.name }} · {{ channel.type_label }}</li>
                </ul>
                <p v-if="selected.some((channel) => channel.type === 'tiktok')">
                    Видео поступит во входящие TikTok. Чтобы опубликовать его, откройте уведомление в приложении.
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button type="button" variant="outline" @click="emit('navigate', 'recipients')">Изменить получателей</Button>
                    <Button type="submit" :disabled="publishForm.processing || !selected.length">Подтвердить отправку</Button>
                </div>
            </template>
            <InputError :message="publishErrors" />
        </form>
        <ul v-if="publications.length" class="space-y-2 border-t pt-4 text-sm">
            <li v-for="publication in publications" :key="publication.id">
                {{ publication.channel_name }} · {{ publicationStates[publication.status] ?? publication.status }}
                <time v-if="publication.scheduled_at" :datetime="publication.scheduled_at">{{
                    new Date(publication.scheduled_at).toLocaleString()
                }}</time>
                <p v-if="publication.error_message" class="text-destructive mt-1 text-xs">{{ publication.error_message }}</p>
                <PublicationRecoveryForm v-if="publication.status === 'submission_unknown'" :publication="publication" :params="params" />
            </li>
        </ul>
    </section>
</template>

<style scoped>
dt {
    margin-bottom: 4px;
    color: var(--muted-foreground);
    font-size: 14px;
}
dd {
    padding: 12px 14px;
    border: 1px solid var(--input);
    border-radius: 8px;
    background: var(--background);
}
</style>
