/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type Criteria from 'src/core/data/criteria.data';
import tagSelectComponent from './index';

describe('sw-entity-tag-select search result cleanup', () => {
    it('keeps the unloaded collection intact before the first search or after a reset', () => {
        const vm = { resultCollection: null };

        expect(() => tagSelectComponent.methods.filterSearchGeneratedTags.call(vm)).not.toThrow();
        expect(vm.resultCollection).toBeNull();
    });

    it('removes the previous create-tag option while keeping real tag results', () => {
        const vm = {
            resultCollection: [
                { id: -1, name: 'Create old tag' },
                { id: 'tag-1', name: '明星' },
                { id: 'tag-2', name: '新品' },
            ],
        };

        tagSelectComponent.methods.filterSearchGeneratedTags.call(vm);

        expect(vm.resultCollection).toEqual([
            { id: 'tag-1', name: '明星' },
            { id: 'tag-2', name: '新品' },
        ]);
    });
});

describe('sw-entity-tag-select first search', () => {
    let wrapper: Awaited<ReturnType<typeof createWrapper>>;

    async function createWrapper() {
        const repository = {
            searchIds: jest.fn(() => Promise.resolve({ total: 0, data: [] })),
            search: jest
                .fn()
                .mockImplementation(() =>
                    Promise.resolve(
                        new Shopwell.Data.EntityCollection(
                            '/tag',
                            'tag',
                            Shopwell.Context.api,
                            new Shopwell.Data.Criteria(),
                        ),
                    ),
                ),
            create: jest.fn((_context: unknown, id: string | number) => ({ id, name: '' })),
        };

        return mount(await wrapTestComponent('sw-entity-tag-select', { sync: true }), {
            props: {
                entityCollection: new Shopwell.Data.EntityCollection(
                    '/tag',
                    'tag',
                    Shopwell.Context.api,
                    new Shopwell.Data.Criteria(),
                ),
            },
            global: {
                provide: {
                    repositoryFactory: { create: () => repository },
                },
                stubs: {
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                    'sw-popover': { template: '<div><slot /></div>' },
                    'sw-label': true,
                    'sw-field-error': true,
                    'sw-loader': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
            },
        });
    }

    afterEach(() => {
        wrapper?.unmount();
        jest.useRealTimers();
    });

    it('finishes typing a new tag before opening the menu and focuses its create option on subsequent searches', async () => {
        wrapper = await createWrapper();
        await flushPromises();
        jest.useFakeTimers();

        expect(wrapper.find('.sw-select-result-list').exists()).toBe(false);

        await wrapper.get('input').setValue('新品');
        jest.advanceTimersByTime(400);

        const searchPromise = wrapper.emitted('search')?.[0]?.[0];
        await expect(searchPromise).resolves.toHaveProperty('0.id', -1);
        await flushPromises();

        expect(wrapper.find('.sw-select-result-list').exists()).toBe(false);
        expect(wrapper.getComponent({ ref: 'selectBase' }).props('isLoading')).toBe(false);

        await wrapper.get('.sw-select__selection').trigger('click');
        await flushPromises();
        await wrapper.get('input').setValue('明星');
        jest.advanceTimersByTime(400);
        await flushPromises();

        const createOption = wrapper.get('.sw-select-result');
        expect(createOption.text()).toContain('global.sw-tag-field.listItemAdd');
        expect(createOption.classes()).toContain('is--active');
    });
});

type CheckTagExistsContext = {
    tagExists: boolean;
    context: object;
    repository: { searchIds: jest.Mock };
};

function createContext(ids: string[]): CheckTagExistsContext {
    return {
        tagExists: true,
        context: {},
        repository: {
            searchIds: jest.fn(() => Promise.resolve({ total: 0, data: ids })),
        },
    };
}

describe('src/app/component/form/select/entity/sw-entity-tag-select', () => {
    const { checkTagExists } = tagSelectComponent.methods as {
        checkTagExists: (this: CheckTagExistsContext, term: string) => Promise<void>;
    };

    it('should mark the tag as existing when an id is found', async () => {
        const context = createContext(['tag-id']);

        await checkTagExists.call(context, 'summer');

        const [criteria] = context.repository.searchIds.mock.calls[0] as [Criteria];
        expect(criteria.getLimit()).toBe(1);
        expect(criteria.totalCountMode).toBe(0);
        expect(context.tagExists).toBe(true);
    });

    it('should mark the tag as missing when no id is found', async () => {
        const context = createContext([]);

        await checkTagExists.call(context, 'summer');

        expect(context.tagExists).toBe(false);
    });

    it('should not search for an empty term', async () => {
        const context = createContext([]);

        await checkTagExists.call(context, '   ');

        expect(context.repository.searchIds).not.toHaveBeenCalled();
        expect(context.tagExists).toBe(true);
    });
});
