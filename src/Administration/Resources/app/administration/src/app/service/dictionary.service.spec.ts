/**
 * @sw-package framework
 */
import DictionaryService from './dictionary.service';
import Repository from 'src/core/data/repository.data';

const { Criteria, EntityCollection } = Shopwell.Data;

describe('DictionaryService', () => {
    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('limits gender choices to active entries and sorts them by position', () => {
        const criteria = new DictionaryService().genderCriteria();

        expect(criteria.filters).toEqual([
            Criteria.equals('dictionaryType.technicalName', 'gender'),
            Criteria.equals('active', true),
        ]);
        expect(criteria.sortings).toEqual([Criteria.sort('position', 'ASC')]);
    });

    it('can hide the internal default choice', () => {
        const criteria = new DictionaryService().genderCriteria(true, true);

        expect(criteria.filters).toContainEqual(Criteria.not('or', [Criteria.equals('technicalName', 'not_specified')]));
    });

    it('reloads a dictionary after invalidating its cached entries', async () => {
        const factory = Shopwell.Service('repositoryFactory');
        const repository = factory.create('dictionary_entry');
        const entry = repository.create();
        entry.technicalName = 'male';
        entry.position = 1;
        entry.active = true;
        entry.translated = { label: 'Man' };
        const collection = new EntityCollection(
            '/dictionary-entry',
            'dictionary_entry',
            Shopwell.Context.api,
            new Criteria(),
            [entry],
        );
        const search = jest.spyOn(Repository.prototype, 'search').mockResolvedValue(collection);
        const service = new DictionaryService();

        expect((await service.getGenders())[0]).toMatchObject({
            technicalName: 'male',
            translated: { label: 'Man' },
        });
        entry.translated = { label: 'Updated' };
        expect((await service.getGenders())[0].translated.label).toBe('Man');
        service.invalidateCaches('gender');
        expect((await service.getGenders())[0].translated.label).toBe('Updated');
        expect(search).toHaveBeenCalledTimes(2);
    });
});
