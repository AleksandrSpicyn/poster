import type { EpisodeAssemblySummary, EpisodeWorkspaceProps } from '@/types/episodes';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

export function useEpisodePublicationForm(props: { workspace: EpisodeWorkspaceProps; assembly: EpisodeAssemblySummary }, blocked: () => boolean) {
    const publishForm = useForm({ channel_ids: [] as number[], caption: props.workspace.screenplay?.public_caption ?? '' });
    const confirmed = ref(false);
    const selected = computed(() => props.workspace.channels.filter((channel) => publishForm.channel_ids.includes(channel.id)));
    watch(
        [() => publishForm.channel_ids.slice(), () => publishForm.caption, () => props.assembly.id, () => props.assembly.lock_version, blocked],
        () => {
            confirmed.value = false;
        },
    );
    watch(
        () => props.workspace.channels,
        (channels) => {
            const ids = publishForm.channel_ids.filter((id) => channels.some((channel) => channel.id === id));
            if (ids.length !== publishForm.channel_ids.length) publishForm.channel_ids = ids;
        },
    );
    const canSend = computed(
        () =>
            props.assembly.status === 'accepted' &&
            !blocked() &&
            selected.value.length > 0 &&
            selected.value.length <= 20 &&
            confirmed.value &&
            !publishForm.processing,
    );
    const plurals = new Intl.PluralRules('ru');
    const sendLabel = computed(() => {
        const n = selected.value.length;
        if (!n) return 'Отправить';
        const form = plurals.select(n);
        return 'Отправить в ' + n + ' ' + (form === 'one' ? 'канал' : form === 'few' ? 'канала' : 'каналов');
    });
    return { publishForm, confirmed, selected, canSend, sendLabel };
}
