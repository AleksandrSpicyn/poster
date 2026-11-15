import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const liveProps: Record<string, string[]> = {
    Dashboard: ['stats', 'recentPrompts', 'recentPosts'],
    'Projects/Index': ['projects'],
    'Projects/Show': ['project', 'prompts', 'series', 'characters', 'episodes'],
    'Series/Index': ['series'],
    'Series/Show': ['series', 'characters', 'episodes'],
    'Characters/Show': ['character', 'revisions', 'series'],
    'Episodes/Show': ['episode', 'assemblies', 'publications', 'characters', 'screenplay', 'sourceAssembly', 'series'],
    Prompts: ['prompts'],
    Posts: ['posts'],
};

export function useWorkspaceRealtime() {
    const page = usePage<SharedData>();
    const route = useRoute();
    const connected = ref(false);
    const enabled = page.props.realtime.enabled;
    let echo: Echo<'reverb'> | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let fallback: ReturnType<typeof setInterval> | undefined;
    let busy = false;
    let pending = false;
    let disposed = false;
    const unsubscribe: (() => void)[] = [];

    function refresh() {
        if (disposed || !liveProps[page.component]) return;
        pending = true;
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (busy || document.hidden || disposed) return;
            pending = false;
            router.reload({ only: liveProps[page.component], preserveErrors: true });
        }, 400);
    }

    function visible() {
        if (!document.hidden) refresh();
    }

    onMounted(() => {
        unsubscribe.push(
            router.on('start', () => {
                busy = true;
            }),
            router.on('finish', () => {
                busy = false;
                if (pending) refresh();
            }),
        );
        document.addEventListener('visibilitychange', visible);
        fallback = setInterval(() => {
            if (!connected.value) refresh();
        }, 30000);

        if (!enabled || !page.props.realtime.key) return;

        const client = new Pusher(page.props.realtime.key, {
            cluster: '',
            wsHost: window.location.hostname,
            wsPort: Number(window.location.port) || 80,
            wssPort: Number(window.location.port) || 443,
            forceTLS: window.location.protocol === 'https:',
            enabledTransports: ['ws', 'wss'],
            channelAuthorization: {
                endpoint: route('broadcasting.auth'),
                transport: 'ajax',
                headersProvider: () => ({
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                            ?.slice(11) ?? '',
                    ),
                }),
            },
        });
        echo = new Echo({ broadcaster: 'reverb', client });
        const channels = ['workspace', ...(page.props.auth.can_manage_series ? ['workspace.series'] : [])];
        const subscriptions = new Set<string>();
        client.connection.bind('state_change', ({ current }: { current: string }) => {
            if (current !== 'connected') {
                connected.value = false;
                subscriptions.clear();
            }
        });

        for (const channel of channels) {
            echo.private(channel)
                .listen('.workspace.updated', refresh)
                .subscribed(() => {
                    subscriptions.add(channel);
                    connected.value = subscriptions.size === channels.length;
                    refresh();
                })
                .error(() => {
                    subscriptions.delete(channel);
                    connected.value = false;
                });
        }
    });

    onUnmounted(() => {
        disposed = true;
        clearTimeout(timer);
        clearInterval(fallback);
        document.removeEventListener('visibilitychange', visible);
        unsubscribe.forEach((stop) => stop());
        echo?.disconnect();
    });

    return { connected, enabled, refresh };
}
