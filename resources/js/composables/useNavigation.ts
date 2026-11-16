import type { NavItem, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Clapperboard, FolderOpen, GlobeLock, Handshake, LayoutGrid, ListOrdered, Logs, Radio, Send } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

export function useNavigation() {
    const route = useRoute();
    const page = usePage<SharedData>();
    const main = computed<NavItem[]>(() => [
        { title: 'Dashboard', href: route('dashboard', undefined, false), icon: LayoutGrid },
        {
            title: 'Projects',
            href: route('projects.index', undefined, false),
            icon: FolderOpen,
            get isActive() {
                const path = page.url.split('?')[0];
                return path.startsWith('/projects') || path === '/series' || path.startsWith('/series/');
            },
        },
        ...(page.props.auth.can_manage_channels ? [{ title: 'Channels', href: route('channels.index', undefined, false), icon: Radio }] : []),
        { title: 'Prompts', href: route('prompts', undefined, false), icon: Clapperboard },
        { title: 'Posts', href: route('posts', undefined, false), icon: Send },
    ]);
    const footer: NavItem[] = [
        { title: 'Logs', href: route('log-viewer.index'), icon: Logs },
        { title: 'Queues', href: route('horizon.index'), icon: ListOrdered },
        { title: 'Privacy policy', href: route('policy'), icon: GlobeLock },
        { title: 'Terms of service', href: route('terms'), icon: Handshake },
    ];
    return { main, footer };
}
