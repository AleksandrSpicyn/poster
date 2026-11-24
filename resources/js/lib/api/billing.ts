interface Usage {
    enforced: boolean;
    shots?: { shot_id: number; prompt_id: number | null; seconds: number; status: string }[];
    available: number;
    estimate: number | null;
    sources: { order_id: number; product_label: string; held: number; consumed: number }[];
}
export async function fetchBillingUsage(url: string, signal: AbortSignal): Promise<Usage> {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal });
    if (!response.ok) throw new Error('Не удалось загрузить данные о секундах.');
    return response.json();
}

export async function fetchProductionQuote(url: string, project: number, series: number | undefined, signal: AbortSignal): Promise<Usage> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);
    const response = await fetch(url, {
        method: 'POST',
        signal,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(token ?? '') },
        body: JSON.stringify({ project_id: project, series_id: series }),
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.message ?? 'Не удалось рассчитать резерв.');
    return { enforced: result.enforced, estimate: result.required_seconds, available: result.available_seconds, sources: [] };
}
