import type { ProjectsPageProps } from '@/types/projects';
import type { Page } from '@inertiajs/core';

export interface ProjectSearchPreview {
    total: number;
    name?: string;
}

export async function fetchProjectSearchPreview(
    url: string,
    page: Pick<Page, 'component' | 'version'>,
    signal: AbortSignal,
): Promise<ProjectSearchPreview | undefined> {
    const response = await fetch(url, {
        signal,
        headers: {
            Accept: 'application/json',
            'X-Inertia': 'true',
            'X-Inertia-Partial-Component': page.component,
            'X-Inertia-Partial-Data': 'projects',
            ...(page.version ? { 'X-Inertia-Version': page.version } : {}),
        },
    });
    if (!response.ok || !response.headers.has('X-Inertia')) return;

    const { props }: { props: Partial<Pick<ProjectsPageProps, 'projects'>> } = await response.json();
    if (props.projects) return { total: props.projects.meta.total, name: props.projects.data[0]?.name };
}
