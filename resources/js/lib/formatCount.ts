const plurals = new Intl.PluralRules('ru');

export function formatCount(count: number, one: string, few: string, many: string): string {
    const ending = plurals.select(count);
    const word = ending === 'one' ? one : ending === 'few' ? few : many;

    return count + ' ' + word;
}
