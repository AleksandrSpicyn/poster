const plurals = new Intl.PluralRules('ru');

export function formatCount(count: number, one: string, few: string, many: string): string {
    const ending = plurals.select(count);
    const word = ending === 'one' ? one : ending === 'many' ? many : few;

    return count + ' ' + word;
}
