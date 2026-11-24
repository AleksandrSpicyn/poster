import { useHashNavigation } from '@/composables/useHashNavigation';
import type { TiktokPublicationProps } from '@/types/tiktokPublication';
import { computed, nextTick, ref, type Ref } from 'vue';

export const tiktokPublicationTabs = ['Предпросмотр', 'Параметры', 'Статус'] as const;
export function useTiktokPublicationNavigation(videos: Readonly<Ref<TiktokPublicationProps['videos']['data']>>, mediaId: Ref<number | null>) {
    const directDetails = ref(false);
    const tab = ref<(typeof tiktokPublicationTabs)[number]>('Параметры');
    const video = computed(() => videos.value.find((item) => item.id === mediaId.value));
    const finalReceipt = computed(() => {
        const receipt = video.value?.receipt;
        return tab.value === 'Статус' && (receipt?.status === 'inbox' || receipt?.status === 'published') ? receipt : null;
    });
    function selectTab(next: (typeof tiktokPublicationTabs)[number]) {
        if (next === 'Статус' && video.value && ['inbox', 'published'].includes(video.value.receipt?.status ?? '')) {
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
        if (result && item.receipt && ['inbox', 'published'].includes(item.receipt.status)) {
            tab.value = 'Статус';
        } else if (selection) {
            tab.value = selection[2] === 'preview' ? 'Предпросмотр' : 'Параметры';
            void nextTick(() => document.getElementById('publication-status')?.focus());
        }
    }
    useHashNavigation(restoreReceipt);

    return { tab, video, finalReceipt, directDetails, selectTab };
}
