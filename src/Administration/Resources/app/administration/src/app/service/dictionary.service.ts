/**
 * @sw-package framework
 *
 * Shared read access to the generic data dictionary.
 *
 * The dictionary replaces the former `salutation` entity: enums a merchant maintains live in
 * `dictionary_type` + `dictionary_entry`, and every select that previously read `salutation`
 * reads `dictionary_entry` of the matching type through this service instead. The service owns
 * three things so the calling components do not drift apart:
 *
 * - the criteria of "the active entries of one type, sorted by position",
 * - a per-language cache for the entries themselves, because the same gender list is read
 *   by the customer form, the order modals, the address form and the newsletter detail on the
 *   very same screen,
 * - the codes of the gender entries (`male`, `female`, `not_specified`), which customer and
 *   order creation need before the merchant has picked anything. The customer and address
 *   entities store the code of the entry directly in their `gender` field.
 */

import type CriteriaType from 'src/core/data/criteria.data';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export type DictionaryServiceEntityType = {
    id: string;
    technicalName: string;
    position: number;
    active: boolean;
    translated: {
        label: string;
    };
    [key: string]: unknown;
};

/**
 * The technical name of the dictionary type that holds the gender entries.
 *
 * @private
 */
export const GENDER_DICTIONARY_TYPE = 'gender';

/**
 * The technical name of the entry used as the default gender.
 *
 * @private
 */
export const NOT_SPECIFIED_ENTRY = 'not_specified';

/**
 * @private
 */
export default class DictionaryService {
    private cachedEntries: Map<string, DictionaryServiceEntityType[]> = new Map();

    /**
     * The criteria every gender select uses: active entries of the `gender` type,
     * position ascending. Kept in one place so a select never silently shows inactive entries.
     *
     * Form selects that require a real gender pass `excludeDefault` to hide the internal
     * `not_specified` entry.
     */
    public genderCriteria(activeOnly = true, excludeDefault = false): CriteriaType {
        return this.entriesCriteria(GENDER_DICTIONARY_TYPE, activeOnly, excludeDefault);
    }

    /**
     * The criteria of the entries of one dictionary type.
     */
    public entriesCriteria(typeName: string, activeOnly = true, excludeDefault = false): CriteriaType {
        const { Criteria } = Shopwell.Data;

        const criteria = new Criteria(1, 500);

        criteria.addFilter(Criteria.equals('dictionaryType.technicalName', typeName));

        if (activeOnly) {
            criteria.addFilter(Criteria.equals('active', true));
        }

        if (excludeDefault) {
            criteria.addFilter(Criteria.not('or', [Criteria.equals('technicalName', NOT_SPECIFIED_ENTRY)]));
        }

        criteria.addSorting(Criteria.sort('position', 'ASC'));
        criteria.addAssociation('dictionaryType');

        return criteria;
    }

    /**
     * The active entries of one type, cached per type and api language.
     */
    public async getEntries(
        typeName: string,
        activeOnly = true,
        excludeDefault = false,
    ): Promise<DictionaryServiceEntityType[]> {
        const languageId = Shopwell.Context.api.languageId;
        const cacheKey = `${typeName}:${languageId}:${activeOnly ? 'active' : 'all'}`;

        const cached = this.cachedEntries.get(cacheKey);

        if (cached) {
            return cached;
        }

        const repository = Shopwell.Service('repositoryFactory').create('dictionary_entry');
        const criteria = this.entriesCriteria(typeName, activeOnly);

        if (excludeDefault) {
            criteria.addFilter(Criteria.not('or', [Criteria.equals('technicalName', NOT_SPECIFIED_ENTRY)]));
        }

        const result = await repository.search(criteria);

        const entries = result.map((entry) => ({
            id: entry.id,
            technicalName: entry.technicalName,
            position: entry.position ?? 0,
            active: Boolean(entry.active),
            translated: {
                label: entry.translated?.label ?? '',
            },
        }));

        this.cachedEntries.set(cacheKey, entries);

        return entries;
    }

    /**
     * The cached gender entries. Equivalent to `getEntries(GENDER_DICTIONARY_TYPE)`.
     */
    public async getGenders(activeOnly = true, excludeDefault = false): Promise<DictionaryServiceEntityType[]> {
        return this.getEntries(GENDER_DICTIONARY_TYPE, activeOnly, excludeDefault);
    }

    /**
     * Drops the caches. Called when an entry of the type is written, so the next reader
     * on the same screen sees the change instead of the stale list.
     */
    public invalidateCaches(typeName?: string): void {
        if (!typeName) {
            this.cachedEntries.clear();

            return;
        }

        [...this.cachedEntries.keys()].forEach((key) => {
            if (key.startsWith(`${typeName}:`)) {
                this.cachedEntries.delete(key);
            }
        });
    }
}
