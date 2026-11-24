import type { ListFilters } from '@/types/content';
import type { Page } from '@inertiajs/core';
import type { route as namedRoute } from 'ziggy-js';
import { isRecord } from '../isRecord';

export interface ProjectSearchPreview {
    total: number;
    name?: string;
}

export async function fetchProjectSearchPreview(
    route: typeof namedRoute,
    filters: Pick<ListFilters, 'search' | 'active' | 'clipping'>,
    page: Pick<Page, 'component' | 'version'>,
    signal: AbortSignal,
): Promise<ProjectSearchPreview | undefined> {
    const response = await fetch(route('projects.index', { ...filters, perPage: 1 }), {
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

    const payload: unknown = await response.json();
    if (!isRecord(payload) || !isRecord(payload.props) || !isRecord(payload.props.projects)) return;
    const { meta, data } = payload.props.projects;
    if (!isRecord(meta) || typeof meta.total !== 'number' || !Array.isArray(data)) return;
    const first: unknown = data[0];
    return { total: meta.total, name: isRecord(first) && typeof first.name === 'string' ? first.name : undefined };
}
