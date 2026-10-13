/** Text as a search compares it: lowercase, without accents. */
const searchable = (text: string): string =>
    text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase();

/**
 * Whether every word typed appears in at least one of the fields, ignoring
 * case and accents: "sao paulo" finds "São Paulo", and "paulo instagram"
 * finds Paulo's Instagram account. An empty search matches everything.
 */
export const matchesSearch = (
    query: string,
    fields: Array<string | null | undefined>,
): boolean => {
    const words = searchable(query).split(/\s+/).filter(Boolean);
    const values = fields.flatMap((field) => (field ? [searchable(field)] : []));

    return words.every((word) => values.some((value) => value.includes(word)));
};
