import type { route as namedRoute } from 'ziggy-js';
import { isRecord } from '../isRecord';
import { csrfHeaders } from './csrf';
import { readJsonObject } from './json';

interface UsageSource {
    order_id: number;
    product_label: string;
    held: number | string;
    consumed: number | string;
}

interface ShotUsage {
    shot_id: number;
    prompt_id: number | null;
    seconds: number;
    status: string;
}

interface Usage {
    enforced: boolean;
    shots?: ShotUsage[];
    available: number;
    estimate: number | null;
    sources: UsageSource[];
}

function isSource(value: unknown): value is UsageSource {
    return (
        isRecord(value) &&
        typeof value.order_id === 'number' &&
        typeof value.product_label === 'string' &&
        (typeof value.held === 'number' || typeof value.held === 'string') &&
        (typeof value.consumed === 'number' || typeof value.consumed === 'string')
    );
}

function isShot(value: unknown): value is ShotUsage {
    return (
        isRecord(value) &&
        typeof value.shot_id === 'number' &&
        (value.prompt_id === null || typeof value.prompt_id === 'number') &&
        typeof value.seconds === 'number' &&
        typeof value.status === 'string'
    );
}

async function readBillingResponse(response: Response, error: string): Promise<Record<string, unknown>> {
    const result = await readJsonObject(response);
    if (!result) throw new Error(error);
    if (!response.ok) throw new Error(typeof result.message === 'string' ? result.message : error);
    return result;
}

export async function fetchBillingUsage(route: typeof namedRoute, kind: 'shot' | 'run', id: number, signal: AbortSignal): Promise<Usage> {
    const error = 'Не удалось загрузить данные о секундах.';
    const url = route(kind === 'shot' ? 'billing.usage.shot' : 'billing.usage.run', id);
    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal });
    if (!response.ok) throw new Error(error);
    const { enforced, available, estimate, sources, shots } = await readBillingResponse(response, error);
    if (
        typeof enforced !== 'boolean' ||
        typeof available !== 'number' ||
        (estimate !== null && typeof estimate !== 'number') ||
        !Array.isArray(sources) ||
        !sources.every(isSource) ||
        (shots !== undefined && !(Array.isArray(shots) && shots.every(isShot)))
    )
        throw new Error(error);
    return { enforced, available, estimate, sources, ...(shots === undefined ? {} : { shots }) };
}

export async function fetchProductionQuote(
    route: typeof namedRoute,
    project: number,
    series: number | undefined,
    signal: AbortSignal,
): Promise<Usage> {
    const error = 'Не удалось рассчитать резерв.';
    const response = await fetch(route('billing.quotes.store'), {
        method: 'POST',
        signal,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...csrfHeaders() },
        body: JSON.stringify({ project_id: project, series_id: series }),
    });
    const { enforced, required_seconds, available_seconds } = await readBillingResponse(response, error);
    if (
        typeof enforced !== 'boolean' ||
        (required_seconds !== null && typeof required_seconds !== 'number') ||
        typeof available_seconds !== 'number'
    ) {
        throw new Error(error);
    }
    return { enforced, estimate: required_seconds, available: available_seconds, sources: [] };
}
