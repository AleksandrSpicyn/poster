import { onMounted, onUnmounted } from 'vue';

/** Restore the current tab or selection from its deep link, including browser back/forward. */
export function useHashNavigation(restore: () => void) {
    onMounted(() => {
        restore();
        window.addEventListener('hashchange', restore);
    });
    onUnmounted(() => window.removeEventListener('hashchange', restore));
}
