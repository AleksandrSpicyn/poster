interface Usage {
    available: number;
    estimate: number | null;
    sources: { order_id: number; product_label: string; held: number; consumed: number }[];
}
export async function fetchBillingUsage(url: string, signal: AbortSignal): Promise<Usage> {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, signal });
    if (!response.ok) throw new Error('Не удалось загрузить данные о секундах.');
    return response.json();
}
