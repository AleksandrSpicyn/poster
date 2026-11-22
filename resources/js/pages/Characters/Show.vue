<script setup lang="ts">
import ArchiveDialog from '@/components/ArchiveDialog.vue';
import CharacterRevisionCard from '@/components/characters/CharacterRevisionCard.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResource } from '@/types/content';
import type { CharacterRevision, CharacterSummary, SeriesSummary } from '@/types/series';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    project: { id: number; name: string };
    series: SeriesSummary;
    character: CharacterSummary;
    episodesCount: number;
    revisions: PaginatedResource<CharacterRevision>;
    referenceViews: Record<string, string>;
}>();
const route = useRoute();
const newVersion = ref(false);
const descriptionField = ref<HTMLTextAreaElement | null>(null);
const portrait = computed(
    () => props.character.current_revision?.references?.[0] ?? props.revisions.data.flatMap((version) => version.references ?? [])[0],
);
async function openVersion() {
    newVersion.value = true;
    await nextTick();
    descriptionField.value?.focus();
}
const params = { project: props.project.id, series: props.series.id, character: props.character.id };
const archived = !!props.character.archived_at || !!props.series.archived_at;
const nameForm = useForm({ name: props.character.name, lock_version: props.character.lock_version });
const revisionForm = useForm({ description: '', appearance: { notes: '' }, voice: { description: '' } });
const revisionErrors = computed<Record<string, string | undefined>>(() => revisionForm.errors);
const archiveForm = useForm({ lock_version: props.character.lock_version });
const breadcrumbs = [
    { title: 'Проекты', href: route('projects.index') },
    { title: props.project.name, href: route('projects.show', props.project.id) },
    { title: props.character.name, href: route('characters.show', params) },
];
function saveName() {
    nameForm.patch(route('characters.update', params), { preserveState: 'errors' });
}
function createRevision() {
    revisionForm.post(route('characters.revisions.store', params), { preserveState: 'errors' });
}
function archive() {
    archiveForm.post(route('characters.archive', params), { preserveState: 'errors' });
}
function changePage(page: number) {
    router.get(route('characters.show', params), { page }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="character.name" />
        <div class="workspace-page max-w-6xl">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading title="Персонаж" :description="series.name + ' / Персонажи / ' + character.name" />
                <Button v-if="!archived" class="h-11" @click="openVersion">Новая версия</Button>
            </div>
            <div class="grid items-start gap-4 md:grid-cols-[min(32%,380px)_minmax(0,1fr)]">
                <img
                    v-if="portrait"
                    :src="portrait.url"
                    :alt="portrait.label || character.name"
                    class="aspect-[3/4] w-full rounded-xl object-cover"
                />
                <div v-else class="studio-panel">
                    <h2>{{ character.name }}</h2>
                    <p>Здесь появится референс персонажа.</p>
                </div>
                <div class="grid min-w-0 gap-4">
                    <p v-if="archived" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
                        Персонаж или сериал в архиве. Версии доступны для просмотра.
                    </p>
                    <form v-if="!archived" class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-5" @submit.prevent="saveName">
                        <div class="grid min-w-48 flex-1 gap-2">
                            <Label for="character-edit-name">Имя персонажа</Label
                            ><Input
                                id="character-edit-name"
                                v-model="nameForm.name"
                                required
                                maxlength="255"
                                :disabled="nameForm.processing"
                            /><InputError :message="nameForm.errors.name" /><InputError :message="nameForm.errors.lock_version" />
                        </div>
                        <Button type="submit" variant="outline" class="h-11" :disabled="nameForm.processing">Сохранить имя</Button>
                    </form>
                    <section v-if="!archived && (newVersion || revisionForm.hasErrors)" class="bg-card space-y-4 rounded-xl border p-5 sm:p-7">
                        <h2 class="font-semibold">Новая версия образа</h2>
                        <p class="text-muted-foreground text-sm">
                            Опишите героя и добавьте ракурсы. Эпизоды сохранят версию образа, использованную при создании.
                        </p>
                        <form class="grid gap-4" @submit.prevent="createRevision">
                            <div class="grid gap-2">
                                <Label for="new-revision-description">Внешность и характер</Label
                                ><textarea
                                    id="new-revision-description"
                                    ref="descriptionField"
                                    v-model="revisionForm.description"
                                    required
                                    maxlength="10000"
                                    rows="4"
                                    :disabled="revisionForm.processing"
                                    class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                                /><InputError :message="revisionForm.errors.description" /><InputError :message="revisionErrors.character" />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="new-revision-appearance">Детали внешности</Label
                                    ><Input
                                        id="new-revision-appearance"
                                        v-model="revisionForm.appearance.notes"
                                        placeholder="Одежда, черты лица, пропорции"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="new-revision-voice">Голос и манера речи</Label
                                    ><Input id="new-revision-voice" v-model="revisionForm.voice.description" placeholder="Тембр, акцент, темп" />
                                </div>
                            </div>
                            <div><Button type="submit" class="h-11" :disabled="revisionForm.processing">Создать черновик</Button></div>
                        </form>
                    </section>
                    <div class="space-y-6">
                        <CharacterRevisionCard
                            v-for="revision in revisions.data"
                            :key="revision.id + ':' + revision.lock_version"
                            :revision="revision"
                            :params="params"
                            :reference-views="referenceViews"
                            :archived="archived"
                        />
                        <p v-if="!revisions.data.length" class="text-muted-foreground p-5 text-sm">Пока нет версий образа.</p>
                    </div>
                    <nav
                        v-if="revisions.meta.last_page > 1"
                        class="flex flex-wrap items-center justify-between gap-3"
                        aria-label="Страницы версий образа"
                    >
                        <Button
                            variant="outline"
                            class="h-11"
                            :disabled="revisions.meta.current_page <= 1"
                            @click="changePage(revisions.meta.current_page - 1)"
                            >Предыдущие версии</Button
                        >
                        <p class="text-muted-foreground text-sm">Страница {{ revisions.meta.current_page }} из {{ revisions.meta.last_page }}</p>
                        <Button
                            variant="outline"
                            class="h-11"
                            :disabled="revisions.meta.current_page >= revisions.meta.last_page"
                            @click="changePage(revisions.meta.current_page + 1)"
                            >Следующие версии</Button
                        >
                    </nav>
                    <div v-if="!archived" class="flex justify-end border-t pt-5">
                        <ArchiveDialog
                            title="Архивировать персонажа?"
                            description="Персонаж исчезнет из выбора для новых сцен. Принятые версии останутся в истории эпизодов."
                            action="Архивировать персонажа"
                            :processing="archiveForm.processing"
                            :error="archiveForm.errors.lock_version"
                            @confirm="archive"
                        >
                            <div class="studio-row">
                                <dt>Версия</dt>
                                <dd>
                                    {{ character.current_revision ? character.current_revision.number + ' · утверждена' : 'Нет утверждённой версии' }}
                                </dd>
                            </div>
                            <div class="studio-row">
                                <dt>Использование</dt>
                                <dd>{{ series.name }} · Эпизодов: {{ episodesCount }}</dd>
                            </div>
                        </ArchiveDialog>
                    </div>
                    <p v-if="series.automation_enabled" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm leading-relaxed">
                        Загрузите все ракурсы. Через две минуты без изменений автоматизация утвердит последний образ и продолжит работу над эпизодом.
                    </p>
                    <Button as-child variant="outline" class="h-11 self-start"
                        ><Link :href="route('series.show', { project: project.id, series: series.id })">К сериалу</Link></Button
                    >
                </div>
            </div>
        </div>
    </AppLayout>
</template>
