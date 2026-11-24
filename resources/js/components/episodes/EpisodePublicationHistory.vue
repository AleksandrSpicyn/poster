<script setup lang="ts">
import ChannelIcon from '@/components/ChannelIcon.vue';
import PublicationRecoveryForm from '@/components/episodes/PublicationRecoveryForm.vue';
import { Button } from '@/components/ui/button';
import { useRoute } from '@/composables/useRoute';
import { platformNames } from '@/lib/analytics';
import { publicationStates } from '@/lib/publicationStatus';
import type { EpisodeAssemblySummary, EpisodeRouteParams, PublicationSummary } from '@/types/episodes';
import { Link, router } from '@inertiajs/vue3';
import { Check, ChevronDown, CircleAlert, Clock, ExternalLink, Inbox, RefreshCw, Search } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{ publications: PublicationSummary[]; assemblies: EpisodeAssemblySummary[]; params: EpisodeRouteParams }>();
const route = useRoute();
const expanded = ref<number | null>(null);
const formatter = new Intl.DateTimeFormat('ru', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'UTC',
});
const versions = computed(() => new Map(props.assemblies.map((a) => [a.id, a])));
const rows = computed(() =>
    props.publications.map((publication) => ({ publication, assembly: versions.value.get(publication.episode_assembly_id) })),
);
const statusIcons: Record<string, typeof Clock> = { published: Check, inbox: Inbox, failed: CircleAlert, submission_unknown: Search };
</script>
<template>
    <section class="min-w-0 border-t py-4" aria-labelledby="episode-publication-history">
        <header class="flex flex-wrap items-center justify-between gap-2">
            <h3 id="episode-publication-history" class="font-semibold">История публикаций</h3>
            <div class="text-muted-foreground flex items-center gap-2 font-mono text-xs">
                UTC
                <Button
                    v-if="rows.length"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-11"
                    aria-label="Обновить статусы"
                    title="Обновить статусы"
                    @click="router.reload({ only: ['publications'] })"
                    ><RefreshCw class="size-4" aria-hidden="true"
                /></Button>
            </div>
        </header>
        <p v-if="!rows.length" class="text-muted-foreground py-3 text-sm">Публикаций пока нет</p>
        <template v-else>
            <div aria-hidden="true" class="record headings text-muted-foreground text-xs">
                <span>Дата заявки</span><span>Канал</span><span class="text-right">Версия</span><span>Результат</span>
            </div>
            <ul>
                <li v-for="{ publication, assembly } in rows" :key="publication.id" class="border-t py-2 text-sm">
                    <div class="record">
                        <span class="date font-mono text-xs"
                            ><time v-if="publication.created_at" :datetime="publication.created_at">{{
                                formatter.format(new Date(publication.created_at))
                            }}</time
                            ><template v-else>Дата неизвестна</template></span
                        >
                        <div class="account flex min-w-0 items-center gap-2">
                            <ChannelIcon :channel="publication.platform ?? null" />
                            <div class="min-w-0 wrap-anywhere">
                                <strong>{{ publication.channel_name ?? 'Аккаунт неизвестен' }}</strong>
                                <span v-if="publication.platform" class="text-muted-foreground block text-xs">{{
                                    platformNames[publication.platform]
                                }}</span>
                            </div>
                        </div>
                        <span class="version font-mono text-xs"><span class="min-[700px]:sr-only">Версия </span>{{ assembly?.number ?? '—' }}</span>
                        <div class="status min-w-0">
                            <span
                                class="inline-flex items-center gap-1.5"
                                :class="{
                                    'text-destructive': publication.status === 'failed',
                                    'text-(--warning)': publication.status === 'submission_unknown',
                                    'text-ring': publication.status === 'published',
                                    'text-(--progress)': ['submitting', 'processing', 'publishing_container', 'inbox'].includes(publication.status),
                                }"
                            >
                                <component :is="statusIcons[publication.status] ?? Clock" class="size-4 shrink-0" aria-hidden="true" />{{
                                    publicationStates[publication.status] ?? publication.status
                                }}
                            </span>
                            <small v-if="publication.scheduled_at" class="text-muted-foreground block"
                                >По расписанию ·
                                <time class="font-mono" :datetime="publication.scheduled_at">{{
                                    formatter.format(new Date(publication.scheduled_at))
                                }}</time></small
                            >
                            <small v-if="publication.published_at" class="text-muted-foreground block"
                                >Опубликовано ·
                                <time class="font-mono" :datetime="publication.published_at">{{
                                    formatter.format(new Date(publication.published_at))
                                }}</time></small
                            >
                        </div>
                        <div class="action">
                            <a
                                v-if="publication.status === 'published' && publication.url"
                                :href="publication.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-grid size-11 place-items-center rounded-lg"
                                :aria-label="'Открыть публикацию · ' + (publication.channel_name ?? 'аккаунт')"
                                title="Открыть публикацию"
                                ><ExternalLink class="size-4"
                            /></a>
                            <Button
                                v-else-if="
                                    publication.error_message ||
                                    ['inbox', 'failed'].includes(publication.status) ||
                                    (publication.status === 'submission_unknown' && publication.can_verify && assembly)
                                "
                                variant="ghost"
                                size="icon"
                                class="size-11"
                                :aria-expanded="expanded === publication.id"
                                :aria-controls="'publication-details-' + publication.id"
                                :aria-label="publication.status === 'submission_unknown' ? 'Сверить результат отправки' : 'Подробности заявки'"
                                @click="expanded = expanded === publication.id ? null : publication.id"
                                ><ChevronDown class="size-4" :class="{ 'rotate-180': expanded === publication.id }"
                            /></Button>
                        </div>
                    </div>
                    <div v-if="expanded === publication.id" :id="'publication-details-' + publication.id" class="bg-muted mt-2 rounded-lg p-3">
                        <p v-if="publication.error_message" class="wrap-anywhere">{{ publication.error_message }}</p>
                        <p v-else-if="publication.status === 'failed'">Площадка не сообщила подробности ошибки.</p>
                        <p v-if="publication.status === 'inbox'">Завершите публикацию через входящие в приложении TikTok.</p>
                        <Link
                            v-if="publication.status === 'failed' && publication.channel_id"
                            :href="route('channels.edit', publication.channel_id) + '#connection-heading'"
                            class="inline-flex min-h-11 items-center underline"
                            >Проверить подключение канала</Link
                        >
                        <PublicationRecoveryForm
                            v-if="publication.status === 'submission_unknown' && publication.can_verify && assembly"
                            :publication="publication"
                            :params="{ ...params, revision: assembly.episode_revision_id, assembly: assembly.id }"
                            expanded
                        />
                    </div>
                </li>
            </ul>
        </template>
    </section>
</template>
<style scoped>
.record {
    display: grid;
    align-items: center;
    grid-template-columns: minmax(0, 1fr) auto 44px;
    grid-template-areas: 'account account action' 'date version action' 'status status action';
    gap: 4px 8px;
}
.headings {
    display: none;
}
.account {
    grid-area: account;
}
.version {
    grid-area: version;
    text-align: right;
}
.status {
    grid-area: status;
}
.date {
    grid-area: date;
}
.action {
    grid-area: action;
}
@media (min-width: 700px) {
    .record {
        grid-template-areas: 'date account version status action';
        grid-template-columns: minmax(92px, 0.8fr) minmax(0, 1.4fr) 52px minmax(0, 1.2fr) 44px;
        column-gap: clamp(8px, 1.5vw, 24px);
    }
    .headings {
        display: grid;
        padding-block: 8px;
    }
}
</style>
