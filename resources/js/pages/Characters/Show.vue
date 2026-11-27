<script setup lang="ts">
import { useFormApi } from '@/lib/api/useFormApi';
import { formatCount } from '@/lib/formatCount';
import { useHashNavigation } from '@/composables/useHashNavigation';
import ImageFilePicker from '@/components/ImageFilePicker.vue';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
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
import { useRoute } from '@/composables/useRoute';

const props = defineProps<{
    project: { id: number; name: string };
    series: SeriesSummary;
    character: CharacterSummary;
    episodesCount: number;
    selectedEpisode: { id: number; number: number } | null;
    revisions: PaginatedResource<CharacterRevision>;
    referenceViews: Record<string, string>;
    speechVoices: Record<string, string>;
}>();
const submitForm = useFormApi();
const route = useRoute();
const archived = computed(() => !!props.character.archived_at || !!props.series.archived_at);
const sectionHash = ref('');
const newVersion = computed(() => !archived.value && sectionHash.value === '#new-version');
const selectedRevision = computed(() => props.revisions.data.find((revision) => sectionHash.value === '#revision-' + revision.id));
const editableVersions = computed(() => (archived.value ? [] : props.revisions.data.filter((revision) => revision.status === 'draft')));
const latestRevisionId = computed(() => (props.revisions.meta.current_page === 1 ? props.revisions.data[0]?.id : undefined));
const uploadRevision = computed(() => editableVersions.value.find((revision) => sectionHash.value === '#references-' + revision.id));
const referenceCount = computed(() => props.revisions.data.reduce((total, version) => total + (version.references?.length ?? 0), 0));
const referenceCountLabel = computed(() => formatCount(referenceCount.value, 'изображение', 'изображения', 'изображений'));
function readHash() {
    sectionHash.value = window.location.hash;
    if (uploadRevision.value || newVersion.value || selectedRevision.value) nextTick(() => window.scrollTo(0, 0));
}
function showUpload(id: number) {
    window.location.hash = 'references-' + id;
}
useHashNavigation(readHash);
const titleField = ref<HTMLInputElement | null>(null);
const portrait = computed(
    () => props.character.current_revision?.references?.[0] ?? props.revisions.data.flatMap((version) => version.references ?? [])[0],
);
async function openVersion() {
    window.location.hash = 'new-version';
    readHash();
    await nextTick();
    titleField.value?.focus();
}
const params = { project: props.project.id, series: props.series.id, character: props.character.id };
const nameForm = useForm({ name: props.character.name, lock_version: props.character.lock_version });
const revisionForm = useForm({ title: '', description: '', file: null as File | null });
const revisionErrors = computed<Record<string, string | undefined>>(() => revisionForm.errors);
const archiveForm = useForm({ lock_version: props.character.lock_version });
const characterUrl = computed(() => route('characters.show', { ...params, ...(props.selectedEpisode ? { episode: props.selectedEpisode.id } : {}) }));
const breadcrumbs = computed(() => [
    { title: 'Сериалы', href: route('series.index') },
    { title: props.series.name, href: route('series.show', { project: props.project.id, series: props.series.id }) },
    {
        title: props.character.name,
        href: characterUrl.value + '#overview',
    },
    ...(uploadRevision.value || newVersion.value || selectedRevision.value
        ? [
              {
                  title: uploadRevision.value ? 'Референсы' : selectedRevision.value ? 'Версии' : 'Новая версия',
                  href: characterUrl.value + sectionHash.value,
              },
          ]
        : []),
]);
function saveName() {
    submitForm(nameForm, ['characters.update', params], { preserveState: 'errors' });
}
function createRevision() {
    submitForm(revisionForm, ['characters.revisions.store', params], {
        preserveState: 'errors',
        onError: () => {
            window.location.hash = 'new-version';
        },
        onSuccess: () => {
            window.location.hash = 'overview';
        },
    });
}
function archive() {
    submitForm(archiveForm, ['characters.archive', params], { preserveState: 'errors' });
}
function changePage(page: number) {
    router.get(characterUrl.value, { page }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="character.name" />
        <div
            :id="selectedRevision ? 'revision-' + selectedRevision.id : undefined"
            class="workspace-page"
            :class="{
                'character-overview': !uploadRevision && !newVersion && !selectedRevision,
                'selected-version': selectedRevision,
                'reference-upload': uploadRevision,
            }"
        >
            <div class="character-header">
                <Heading
                    :title="uploadRevision || newVersion || selectedRevision ? character.name : 'Персонаж'"
                    :description="
                        uploadRevision ? 'Референсы' : newVersion || selectedRevision ? 'Версии' : series.name + ' / Персонажи / ' + character.name
                    "
                />
                <div v-if="!uploadRevision && !newVersion && !selectedRevision" class="flex flex-wrap gap-2">
                    <Button as-child variant="outline" class="h-11">
                        <Link :href="route('series.show', { project: project.id, series: series.id })">К сериалу</Link>
                    </Button>
                    <Button v-if="!archived" class="h-11" @click="openVersion">Новая версия</Button>
                </div>
            </div>
            <div
                v-if="uploadRevision || newVersion"
                class="grid w-full max-w-[1240px] items-start gap-5 min-[1100px]:grid-cols-[minmax(0,2.37fr)_minmax(0,1fr)]"
            >
                <CharacterRevisionCard
                    v-if="uploadRevision"
                    :key="uploadRevision.id + ':' + uploadRevision.lock_version"
                    :revision="uploadRevision"
                    :latest-revision="uploadRevision.id === latestRevisionId"
                    :params="params"
                    :speech-voices="speechVoices"
                    :reference-views="referenceViews"
                    :archived="archived"
                    upload-only
                >
                    <template #version>
                        <label class="grid min-w-0 gap-2 text-sm"
                            >Версия персонажа
                            <select
                                :value="uploadRevision.id"
                                class="list-select w-full"
                                @change="showUpload(Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option v-for="revision in editableVersions" :key="revision.id" :value="revision.id">
                                    {{ String(revision.number).padStart(2, '0') }} · черновик
                                </option>
                            </select>
                        </label>
                    </template>
                </CharacterRevisionCard>
                <form v-else-if="!archived" class="studio-panel grid min-w-0 gap-3" @submit.prevent="createRevision">
                    <h2 class="text-xl font-semibold">Новая версия</h2>
                    <div v-if="character.current_revision" class="flex items-center gap-3">
                        <img v-if="portrait" :src="portrait.url" :alt="character.name" class="h-14 w-20 rounded-lg object-cover" />
                        <strong>Текущий образ · версия {{ String(character.current_revision.number).padStart(2, '0') }}</strong>
                    </div>
                    <label class="grid gap-2 text-sm"
                        >Название версии
                        <input
                            ref="titleField"
                            v-model="revisionForm.title"
                            required
                            maxlength="255"
                            class="border-input bg-background h-11 w-full rounded-md border px-3"
                            :disabled="revisionForm.processing"
                        />
                        <InputError :message="revisionForm.errors.title" />
                    </label>
                    <label class="grid gap-2 text-sm"
                        >Описание внешности
                        <textarea
                            v-model="revisionForm.description"
                            required
                            maxlength="10000"
                            rows="1"
                            class="border-input bg-background min-h-11 w-full resize-y rounded-md border px-3 py-2"
                            :disabled="revisionForm.processing"
                        />
                        <InputError :message="revisionForm.errors.description" />
                    </label>
                    <ImageFilePicker
                        label="Референс новой версии"
                        :file="revisionForm.file"
                        :disabled="revisionForm.processing"
                        @select="
                            revisionForm.file = $event;
                            revisionForm.clearErrors('file');
                        "
                    />
                    <InputError :message="revisionForm.errors.file || revisionErrors.character" />
                    <Button type="submit" class="h-11 justify-self-start" :disabled="revisionForm.processing">Создать версию</Button>
                </form>
                <aside class="studio-panel hidden min-w-0 space-y-3 min-[1100px]:block">
                    <img v-if="portrait" :src="portrait.url" :alt="character.name" class="aspect-[2.8] w-full rounded-lg object-cover" />
                    <strong>{{ series.name }}</strong>
                    <div>
                        <p class="text-muted-foreground text-sm">Сериал</p>
                        <Link :href="route('series.show', { project: project.id, series: series.id })">{{ series.name }}</Link>
                    </div>
                    <div v-if="selectedEpisode">
                        <p class="text-muted-foreground text-sm">Эпизод</p>
                        <Link :href="route('episodes.show', { series: series.id, episode: selectedEpisode.id })"
                            >Серия {{ String(selectedEpisode.number).padStart(2, '0') }}</Link
                        >
                    </div>
                </aside>
            </div>
            <div v-else-if="!selectedRevision" class="grid items-start gap-4 md:grid-cols-[min(34%,400px)_minmax(0,1fr)]">
                <img
                    v-if="portrait"
                    :src="portrait.url"
                    :alt="portrait.label || character.name"
                    class="h-[210px] w-full rounded-xl object-cover max-md:object-[50%_54%] md:h-[393px]"
                />
                <div v-else class="studio-panel">
                    <h2>{{ character.name }}</h2>
                    <p>Здесь появится референс персонажа.</p>
                </div>
                <section class="min-w-0" aria-labelledby="character-versions">
                    <h2 id="character-versions" class="mb-4 text-xl font-semibold">Версии и референсы</h2>
                    <a v-for="revision in revisions.data" :key="revision.id" class="character-version-row" :href="'#revision-' + revision.id">
                        <strong>Версия {{ String(revision.number).padStart(2, '0') }}{{ revision.title ? ' · ' + revision.title : '' }}</strong>
                        <span :class="revision.status === 'approved' ? 'text-primary' : 'text-muted-foreground'">
                            {{ revision.status === 'approved' ? 'Утверждена' : revision.status === 'superseded' ? 'Заменена' : 'Черновик' }}
                        </span>
                    </a>
                    <p v-if="revisions.data.length" class="character-version-row">
                        <strong>Референсы</strong>
                        <span class="text-muted-foreground">{{ referenceCountLabel }}{{ revisions.meta.last_page > 1 ? ' на странице' : '' }}</span>
                    </p>
                    <p v-else class="text-muted-foreground text-sm">Пока нет версий образа.</p>
                </section>
            </div>
            <div v-if="!newVersion && !uploadRevision" :class="{ 'revision-layout': selectedRevision }">
                <div id="character-reference-list" class="grid min-w-0 gap-4">
                    <p v-if="archived" class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm">
                        Персонаж или сериал в архиве. Версии доступны для просмотра.
                    </p>
                    <form
                        v-if="!archived && !selectedRevision"
                        class="bg-card flex flex-wrap items-end gap-3 rounded-xl border p-5"
                        @submit.prevent="saveName"
                    >
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
                    <div v-if="revisions.data.length" class="space-y-6">
                        <CharacterRevisionCard
                            v-for="revision in selectedRevision ? [selectedRevision] : revisions.data"
                            :id="selectedRevision ? undefined : 'revision-' + revision.id"
                            :key="revision.id + ':' + revision.lock_version"
                            :focused="!!selectedRevision"
                            :revision="revision"
                            :latest-revision="revision.id === latestRevisionId"
                            :approved-revision="character.current_revision"
                            :params="params"
                            :speech-voices="speechVoices"
                            :reference-views="referenceViews"
                            :archived="archived"
                            @open-upload="showUpload(revision.id)"
                        />
                    </div>
                    <nav
                        v-if="!selectedRevision && revisions.meta.last_page > 1"
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
                    <div v-if="!archived && !selectedRevision" class="flex justify-end border-t pt-5">
                        <ConfirmationDialog
                            heading="Архивация персонажа"
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
                        </ConfirmationDialog>
                    </div>
                    <p
                        v-if="!archived && series.automation_enabled"
                        class="bg-muted text-muted-foreground rounded-lg border p-4 text-sm leading-relaxed"
                    >
                        Загрузите все ракурсы. Через две минуты без изменений автоматизация утвердит последний образ и продолжит работу над эпизодом.
                    </p>
                    <Button v-if="selectedRevision" as-child variant="outline" class="h-11 self-start"
                        ><Link :href="route('series.show', { project: project.id, series: series.id })">К сериалу</Link></Button
                    >
                </div>
                <aside v-if="selectedRevision" class="studio-panel version-context">
                    <img v-if="portrait" :src="portrait.url" :alt="character.name" class="h-[132px] w-full rounded-lg object-cover" />
                    <h2>{{ series.name }}</h2>
                    <Link v-if="selectedEpisode" :href="route('episodes.show', { series: series.id, episode: selectedEpisode.id })"
                        >Эпизод {{ String(selectedEpisode.number).padStart(2, '0') }}</Link
                    >
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.selected-version {
    gap: 16px;
}
.revision-layout {
    display: grid;
    align-items: start;
    gap: 20px;
    max-width: 1240px;
}
.version-context {
    display: none;
}
@media (min-width: 1100px) {
    .revision-layout {
        grid-template-columns: minmax(0, 2.35fr) minmax(0, 1fr);
    }
    .version-context {
        display: block;
        padding: 15px;
    }
    .version-context h2 {
        margin: 10px 0 4px;
    }
}

.reference-upload {
    padding-top: 14px;
}
.reference-upload aside {
    padding: 16px;
}
.reference-upload aside img {
    aspect-ratio: 2.85;
}
@media (max-width: 767px) {
    .reference-upload {
        padding-top: 12px;
        gap: 20px;
    }
}

.character-overview {
    gap: 23px;
}
@media (min-width: 768px) and (max-width: 1099px) {
    .character-overview {
        gap: 25px;
    }
}
@media (max-width: 767px) {
    .character-overview {
        padding-top: 16px;
        gap: 27px;
    }
}

.character-overview .character-header > button {
    font-weight: 700;
}
@media (min-width: 1100px) {
    .character-overview .character-header > button {
        margin-top: 6px;
    }
}
.character-header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 16px;
}
.character-version-row {
    display: grid;
    grid-template-columns: minmax(0, 46%) minmax(0, 1fr);
    gap: 8px;
    align-items: center;
    min-height: 53px;
    border-bottom: 1px solid var(--border);
    padding: 8px 0;
}
.character-version-row span {
    font-size: 14px;
}
@media (max-width: 1099px) {
    .character-header {
        flex-direction: column;
        gap: 12px;
    }
}
@media (max-width: 767px) {
    .character-overview #character-versions {
        margin-bottom: 21px;
    }
    .character-version-row {
        grid-template-columns: 1fr;
        gap: 2px;
        min-height: 66px;
    }
}
</style>
