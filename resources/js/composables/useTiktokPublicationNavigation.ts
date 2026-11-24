import { useHashNavigation } from '@/composables/useHashNavigation';
import type { TiktokPublicationProps } from '@/types/tiktokPublication';
import { computed, nextTick, ref, type Ref } from 'vue';

export const tiktokPublicationTabs = ['Предпросмотр', 'Параметры', 'Статус'] as const;
type PublicationVideo = TiktokPublicationProps['videos']['data'][number];

function deliveredReceipt(video?: PublicationVideo) {
    const receipt = video?.receipt;
    return receipt?.status === 'inbox' || receipt?.status === 'published' ? receipt : null;
}

export function useTiktokPublicationNavigation(videos: Readonly<Ref<TiktokPublicationProps['videos']['data']>>, mediaId: Ref<number | null>) {
    const directDetails = ref(false);
    const tab = ref<(typeof tiktokPublicationTabs)[number]>('Параметры');
    const video = computed(() => videos.value.find((item) => item.id === mediaId.value));
    const finalReceipt = computed(() => (tab.value === 'Статус' ? deliveredReceipt(video.value) : null));
    function selectTab(next: (typeof tiktokPublicationTabs)[number]) {
        if (next === 'Статус' && video.value && deliveredReceipt(video.value)) {
            window.history.replaceState(
                window.history.state,
                '',
                '#video-' + video.value.id + (tab.value === 'Предпросмотр' ? '-preview' : '-settings'),
            );
            window.location.hash = 'receipt-' + video.value.id;
            return;
        }
        tab.value = next;
    }
    function restoreReceipt() {
        const result = /^#receipt-(\d+)$/.exec(window.location.hash);
        const selection = /^#video-(\d+)-(preview|settings)$/.exec(window.location.hash);
        const id = result?.[1] ?? selection?.[1];
        const item = videos.value.find((entry) => entry.id === Number(id));
        if (!item) return;
        mediaId.value = item.id;
        directDetails.value = false;
        if (result && deliveredReceipt(item)) {
            tab.value = 'Статус';
        } else if (selection) {
            tab.value = selection[2] === 'preview' ? 'Предпросмотр' : 'Параметры';
            void nextTick(() => document.getElementById('publication-status')?.focus());
        }
    }
    useHashNavigation(restoreReceipt);

    return { tab, video, finalReceipt, directDetails, selectTab };
}
