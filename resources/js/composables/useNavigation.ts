import { useRoute } from '@/composables/useRoute';
import type { NavItem, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Clapperboard, createLucideIcon, Film, GlobeLock, Handshake, ListOrdered, Logs, Radio, Scissors, Send } from '@lucide/vue';
import { computed } from 'vue';

const StudioOverviewIcon = createLucideIcon({
    name: 'studio-overview',
    size: 24,
    node: [['path', { d: 'M2 19v-8h5v8m2 0V3h6v16m2 0V7h5v12', 'stroke-linecap': 'butt', 'stroke-linejoin': 'miter' }]],
});

const StudioFolderIcon = createLucideIcon({
    name: 'studio-folder',
    size: 24,
    node: [['path', { d: 'M2 6h8l2 3h10v10H2z', 'stroke-linejoin': 'miter' }]],
});

export function useNavigation() {
    const route = useRoute();
    const page = usePage<SharedData & { project?: { content_mode: string } | null }>();
    const main = computed<NavItem[]>(() => {
        const items: NavItem[] = [
            { title: 'Обзор', href: route('dashboard', undefined, false), icon: StudioOverviewIcon },
            { title: 'Проекты', href: route('projects.index', undefined, false), icon: StudioFolderIcon },
            ...(page.props.auth.can_manage_series ? [{ title: 'Сериалы', href: route('series.index', undefined, false), icon: Film }] : []),
            { title: 'Генерации', href: route('prompts', undefined, false), icon: Clapperboard },
            { title: 'Публикации', href: route('posts', undefined, false), icon: Send },
            ...(page.props.auth.can_manage_channels ? [{ title: 'Каналы', href: route('channels.index', undefined, false), icon: Radio }] : []),
            { title: 'Источники', href: route('clipping.index', undefined, false), icon: Scissors },
        ];
        const url = new URL(page.url, 'http://poster.local');
        const path = url.pathname;
        const seriesForm =
            page.component === 'Projects/Form' && (page.props.project?.content_mode === 'series' || url.searchParams.get('format') === 'series');
        const section =
            seriesForm || path.startsWith('/series-assemblies/') || /^\/projects\/\d+\/series(?:\/|$)/.test(path)
                ? route('series.index', undefined, false)
                : path;
        return items.map((item) => ({ ...item, isActive: section === item.href || section.startsWith(item.href + '/') }));
    });
    const adminLinks = computed<NavItem[]>(() =>
        page.props.auth.can_view_logs
            ? [
                  { title: 'Logs', href: route('log-viewer.index'), icon: Logs },
                  ...(page.props.auth.can_view_queues ? [{ title: 'Horizon', href: route('horizon.index'), icon: ListOrdered }] : []),
                  { title: 'Privacy Policy', href: route('policy'), icon: GlobeLock },
                  { title: 'Terms of Service', href: route('terms'), icon: Handshake },
              ]
            : [],
    );
    return { main, adminLinks };
}
