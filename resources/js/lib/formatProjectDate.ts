const formatter = new Intl.DateTimeFormat('en', { dateStyle: 'medium' });

export function formatProjectDate(value: string | null): string {
    return value ? formatter.format(new Date(value)) : '—';
}
