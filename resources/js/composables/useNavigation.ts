import type { NavItem, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Clapperboard, FolderOpen, GlobeLock, Handshake, LayoutGrid, ListOrdered, Logs, Send } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

export function useNavigation() {
    const route = useRoute();
    const page = usePage<SharedData>();
    const isSeriesPage = () => {
        const path = page.url.split('?')[0];
        return path === '/series' || path.startsWith('/series/') || /^\/projects\/\d+\/series(?:\/|$)/.test(path);
    };
    const main = computed<NavItem[]>(() => [
        { title: 'Dashboard', href: route('dashboard', undefined, false), icon: LayoutGrid },
        {
            title: 'Projects',
            href: route('projects.index', undefined, false),
            icon: FolderOpen,
            get isActive() {
                return page.url.split('?')[0].startsWith('/projects') && !isSeriesPage();
            },
        },
        ...(page.props.auth.can_manage_series
            ? [
                  {
                      title: 'Series',
                      href: route('series.index', undefined, false),
                      icon: ListOrdered,
                      get isActive() {
                          return isSeriesPage();
                      },
                  },
              ]
            : []),
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
