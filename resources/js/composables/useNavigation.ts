import type { NavItem, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Clapperboard, Film, FolderOpen, GlobeLock, Handshake, LayoutGrid, ListOrdered, Logs, Radio, Scissors, Send } from '@lucide/vue';
import { computed } from 'vue';
import { useRoute } from 'ziggy-js';

export function useNavigation() {
    const route = useRoute();
    const page = usePage<SharedData>();
    const main = computed<NavItem[]>(() => {
        const items: NavItem[] = [
            { title: 'Обзор', href: route('dashboard', undefined, false), icon: LayoutGrid },
            { title: 'Проекты', href: route('projects.index', undefined, false), icon: FolderOpen },
            ...(page.props.auth.can_manage_series ? [{ title: 'Сериалы', href: route('series.index', undefined, false), icon: Film }] : []),
            { title: 'Генерации', href: route('prompts', undefined, false), icon: Clapperboard },
            { title: 'Клиппинг', href: route('clipping.index', undefined, false), icon: Scissors },
            { title: 'Публикации', href: route('posts', undefined, false), icon: Send },
            ...(page.props.auth.can_manage_channels ? [{ title: 'Каналы', href: route('channels.index', undefined, false), icon: Radio }] : []),
        ];
        const path = page.url.split('?')[0];
        const section = path.match(/^\/projects\/\d+\/series(?:\/|$)/) ? route('series.index', undefined, false) : path;
        return items.map((item) => ({ ...item, isActive: section === item.href || section.startsWith(item.href + '/') }));
    });
    const footer = computed<NavItem[]>(() => [
        ...(page.props.auth.can_view_logs ? [{ title: 'Logs', href: route('log-viewer.index'), icon: Logs }] : []),
        ...(page.props.auth.can_view_queues ? [{ title: 'Horizon', href: route('horizon.index'), icon: ListOrdered }] : []),
        { title: 'Privacy Policy', href: route('policy'), icon: GlobeLock },
        { title: 'Terms of Service', href: route('terms'), icon: Handshake },
    ]);
    return { main, footer };
}
