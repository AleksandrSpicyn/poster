const formatter = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium' });

export function formatProjectDate(value: string | null): string {
    return value ? formatter.format(new Date(value)) : '—';
}
