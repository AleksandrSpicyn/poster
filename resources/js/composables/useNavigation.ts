import type { NavItem } from '@/types';
import { Clapperboard, FolderOpen, GlobeLock, Handshake, LayoutGrid, ListOrdered, Logs, Send } from '@lucide/vue';
import { useRoute } from 'ziggy-js';

export function useNavigation() {
    const route = useRoute();
    const main: NavItem[] = [
        { title: 'Dashboard', href: route('dashboard', undefined, false), icon: LayoutGrid },
        { title: 'Projects', href: route('projects.index', undefined, false), icon: FolderOpen },
        { title: 'Prompts', href: route('prompts', undefined, false), icon: Clapperboard },
        { title: 'Posts', href: route('posts', undefined, false), icon: Send },
    ];
    const footer: NavItem[] = [
        { title: 'Logs', href: route('log-viewer.index'), icon: Logs },
        { title: 'Queues', href: route('horizon.index'), icon: ListOrdered },
        { title: 'Privacy policy', href: route('policy'), icon: GlobeLock },
        { title: 'Terms of service', href: route('terms'), icon: Handshake },
    ];
    return { main, footer };
}
