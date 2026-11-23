import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { type InjectionKey, type Ref, onMounted, onUnmounted, provide, ref } from 'vue';
import { useRoute } from 'ziggy-js';

export const workspaceSubscription: InjectionKey<Readonly<Ref<boolean>>> = Symbol('workspaceSubscription');

const liveProps: Record<string, string[]> = {
    PublicationAnalytics: ['analytics'],
    Dashboard: ['analytics', 'stats', 'recentPrompts', 'recentPosts'],
    'Projects/Index': ['projects'],
    'Projects/Show': ['project', 'prompts', 'series', 'characters', 'episodes', 'opusClips'],
    'Series/Index': ['series'],
    'Series/Show': ['series', 'characters', 'episodes'],
    'Characters/Show': ['character', 'revisions', 'series', 'episodesCount'],
    'SeriesAssemblies/Create': ['series', 'parameters'],
    'SeriesAssemblies/Show': ['assembly', 'series', 'release_started', 'parameters'],
    'Episodes/Show': ['episode', 'assemblies', 'publications', 'characters', 'screenplay', 'sourceAssembly', 'series'],
    Prompts: ['prompts'],
    Posts: ['posts', 'requests', 'destinations'],
    'Channels/Index': ['channels'],
};

const affectedProps: Record<string, string[]> = {
    Project: ['parameters', 'projects', 'project', 'prompts', 'series', 'channels', 'destinations'],
    OpusClipMetric: ['analytics', 'opusClips'],
    PublicationObservation: ['analytics'],
    Channel: ['analytics', 'channels', 'projects', 'project', 'destinations', 'requests', 'posts', 'recentPosts'],
    Prompt: ['prompts', 'projects', 'project', 'stats', 'recentPrompts', 'episode', 'assemblies'],
    Post: ['posts', 'recentPosts', 'stats', 'requests', 'project', 'publications'],
    Series: ['parameters', 'series', 'project', 'projects', 'episodes', 'release_started'],
    Character: ['characters', 'character', 'series'],
    CharacterRevision: ['revisions', 'characters', 'character', 'series'],
    Episode: ['parameters', 'episode', 'episodes', 'series', 'sourceAssembly', 'assemblies'],
    EpisodeRevision: ['episode', 'episodes', 'screenplay', 'assemblies', 'series', 'episodesCount'],
    Shot: ['episode', 'assemblies'],
    Scene: ['episode', 'screenplay'],
    GenerationOperation: ['episode', 'prompts', 'recentPrompts'],
    EpisodeAssembly: ['parameters', 'assemblies', 'episode', 'episodes', 'sourceAssembly', 'publications'],
    SeriesAssembly: ['assembly', 'series', 'assemblies', 'project'],
    PublicationRequest: ['publications', 'requests', 'series', 'episode', 'project', 'release_started'],
    ProductionRun: ['projects', 'project', 'series', 'episode', 'episodes'],
};

export function useWorkspaceRealtime() {
    const page = usePage<SharedData>();
    const route = useRoute();
    const connected = ref(false);
    const enabled = page.props.realtime.enabled;
    if (enabled && page.props.realtime.key) provide(workspaceSubscription, connected);
    let echo: Echo<'reverb'> | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let fallback: ReturnType<typeof setInterval> | undefined;
    let busy = false;
    const pending = new Set<string>();
    let disposed = false;
    const unsubscribe: (() => void)[] = [];

    function queueRefresh(props: string[]) {
        if (disposed || !liveProps[page.component]) return;
        for (const prop of props) pending.add(prop);
        if (!pending.size) return;
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (busy || document.hidden || disposed) return;
            const only = (liveProps[page.component] ?? []).filter((prop) => pending.has(prop));
            pending.clear();
            if (only.length) {
                router.reload({
                    only,
                    preserveErrors: true,
                    replace: true,
                    onBeforeUpdate(response) {
                        const current = window.location;
                        const incoming = new URL(response.url, current.href);
                        if (incoming.pathname === current.pathname && incoming.search === current.search) {
                            response.url = current.pathname + current.search + current.hash;
                        }
                    },
                });
            }
        }, 400);
    }

    function refresh() {
        queueRefresh(liveProps[page.component] ?? []);
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
                if (pending.size) queueRefresh([]);
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
        const versions = new Map<string, number>();
        client.connection.bind('state_change', ({ current }: { current: string }) => {
            if (current !== 'connected') connected.value = false;
        });

        echo.private(`workspace.${page.props.auth.user.id}`)
            .listen('.workspace.updated', (event: { resource: string; id: number; version: number }) => {
                const key = `${event.resource}.${event.id}`;
                if (event.version <= (versions.get(key) ?? 0)) return;
                versions.set(key, event.version);
                queueRefresh((liveProps[page.component] ?? []).filter((prop) => affectedProps[event.resource]?.includes(prop)));
            })
            .subscribed(() => {
                connected.value = true;
                versions.clear();
                refresh();
            })
            .error(() => {
                connected.value = false;
            });
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
