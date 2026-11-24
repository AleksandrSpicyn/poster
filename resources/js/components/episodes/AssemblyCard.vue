<script setup lang="ts">
import ChannelIcon from '@/components/ChannelIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useEpisodePublicationForm } from '@/composables/useEpisodePublicationForm';
import { useFormApi } from '@/lib/api/useFormApi';
import type { EpisodeAssemblySummary, EpisodeWorkspaceProps, EpisodeRouteParams } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ assembly: EpisodeAssemblySummary; workspace: EpisodeWorkspaceProps; params: EpisodeRouteParams }>();
const submitForm = useFormApi();
const params = computed(() => ({ ...props.params, revision: props.assembly.episode_revision_id, assembly: props.assembly.id }));
const video = computed(() => props.assembly.media.find((item) => item.collection === 'episode-video'));
const unavailable = computed(() => {
    const { project, series, episode } = props.workspace;
    if (series.archived_at) return 'Сериал в архиве.';
    if (!project.is_active) return 'Проект выключен.';
    if (props.assembly.status === 'ready') {
        if (
            !video.value ||
            series.cycle_paused ||
            episode.current_revision?.status !== 'approved' ||
            episode.current_revision_id !== props.assembly.episode_revision_id ||
            episode.current_revision.base_continuity_version !== series.continuity_version ||
            episode.current_revision.context.specification_version !== series.specification_version ||
            episode.current_revision.source_assembly_id !== (props.workspace.sourceAssembly?.id ?? null)
        )
            return 'Приёмка этой версии недоступна.';
        return '';
    }
    if (props.assembly.status !== 'accepted') return 'Для отправки нужна принятая сборка.';
    if (series.output_mode === 'single_video') return 'В этом сериале публикуется итоговый ролик цикла.';
    if (episode.accepted_assembly_id !== props.assembly.id || episode.validity !== 'current')
        return 'Для отправки выберите актуальную принятую версию.';
    if (!props.workspace.channels.length) return 'У проекта пока нет доступных каналов публикации.';
    return '';
});
const acceptForm = useForm({
    lock_version: props.assembly.lock_version,
    confirmed_events: '',
    character_state: '',
    open_threads: '',
    scene_closed: false,
});
const reviewedVersion = ref(false);
watch(
    () => props.assembly.lock_version,
    (version) => {
        reviewedVersion.value = false;
        acceptForm.lock_version = version;
    },
);
const acceptErrors = computed(() => Object.values(acceptForm.errors).join(' '));
function accept() {
    if (!reviewedVersion.value || unavailable.value || props.assembly.status !== 'ready') return;
    submitForm(acceptForm, ['episodes.assemblies.accept', params.value], {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => window.location.replace('#release'),
    });
}
const { publishForm, confirmed, selected, canSend, sendLabel } = useEpisodePublicationForm(props, () => Boolean(unavailable.value));
const publishErrors = computed(() => Object.values(publishForm.errors).join(' '));
function publish() {
    if (!canSend.value) return;
    submitForm(publishForm, ['episodes.assemblies.publish', params.value], {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirmed.value = false;
            window.location.replace('#release');
        },
    });
}
</script>
<template>
    <div class="compose grid min-w-0 gap-4 py-4">
        <div class="bg-muted grid min-w-0 place-items-center overflow-hidden rounded-lg">
            <video
                v-if="video"
                :src="video.url"
                controls
                playsinline
                preload="metadata"
                aria-label="Видео выбранной сборки"
                class="h-full max-h-[26rem] min-h-44 w-full bg-black object-contain max-[699px]:h-48"
            />
            <span v-else class="text-muted-foreground p-4 text-sm">Видео пока недоступно</span>
        </div>
        <div v-if="unavailable" class="grid content-start justify-items-start gap-2">
            <p>{{ unavailable }}</p>
            <details v-if="assembly.error_message">
                <summary>Подробности ошибки</summary>
                <p class="wrap-anywhere">{{ assembly.error_message }}</p>
            </details>
            <a href="#montage" class="inline-flex min-h-11 items-center underline">Перейти к монтажу</a>
        </div>
        <form v-else-if="assembly.status === 'ready'" class="grid min-w-0 gap-4" @submit.prevent="accept">
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
            <label class="flex min-h-11 items-center gap-2"><input v-model="reviewedVersion" type="checkbox" required />Проверена эта версия</label>
            <InputError :message="acceptErrors" />
            <div>
                <Button type="submit" class="h-11" :disabled="!reviewedVersion || acceptForm.processing"
                    >Принять версию {{ String(assembly.number).padStart(2, '0') }}</Button
                >
            </div>
        </form>
        <form v-else class="grid min-w-0 content-start gap-3" @submit.prevent="publish">
            <fieldset class="min-w-0" :disabled="publishForm.processing">
                <legend class="mb-1 font-semibold">Куда отправить</legend>
                <label v-for="channel in workspace.channels" :key="channel.id" class="flex min-h-11 items-center gap-2 border-b py-1 text-sm">
                    <input
                        v-model="publishForm.channel_ids"
                        type="checkbox"
                        :value="channel.id"
                        class="accent-primary size-4 shrink-0"
                        :disabled="selected.length >= 20 && !publishForm.channel_ids.includes(channel.id)"
                    />
                    <ChannelIcon :channel="channel.type" /><strong class="min-w-0 wrap-anywhere">{{ channel.name }}</strong>
                    <span class="text-muted-foreground ml-auto pl-1 text-xs">{{ channel.type_label }}</span>
                </label>
            </fieldset>
            <p v-if="selected.some((channel) => channel.type === 'tiktok')" class="text-muted-foreground text-sm">
                Видео поступит во входящие TikTok. Завершите публикацию в приложении.
            </p>
            <label class="grid gap-1 text-sm"
                >Подпись<textarea
                    v-model="publishForm.caption"
                    :disabled="publishForm.processing"
                    rows="1"
                    maxlength="1000"
                    class="studio-field"
                    style="--studio-field-min-height: 48px"
                />
            </label>
            <label class="flex min-h-11 items-center gap-2 text-sm"
                ><input
                    v-model="confirmed"
                    type="checkbox"
                    :disabled="!selected.length || publishForm.processing"
                    class="accent-primary size-4 shrink-0"
                />Подтверждаю отправку в выбранные каналы</label
            >
            <InputError :message="publishErrors" />
            <Button class="min-[700px]:justify-self-end" type="submit" :disabled="!canSend"
                ><Send class="size-4" aria-hidden="true" />{{ publishForm.processing ? 'Отправка…' : sendLabel }}</Button
            >
        </form>
    </div>
</template>
<style scoped>
@media (min-width: 700px) {
    .compose {
        grid-template-columns: minmax(0, 0.76fr) minmax(0, 1.5fr);
        column-gap: clamp(18px, 2vw, 32px);
    }
}
@media (min-width: 1920px) {
    .compose {
        grid-template-columns: minmax(0, 1fr) minmax(0, 820px);
        column-gap: 36px;
    }
}
</style>
