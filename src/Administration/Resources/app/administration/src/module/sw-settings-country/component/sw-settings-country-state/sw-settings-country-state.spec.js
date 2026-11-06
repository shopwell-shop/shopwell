/**
 * @sw-package fundamentals@discovery
 */
import { mount, flushPromises } from '@vue/test-utils';

const COUNTRY_ID = 'e5f64789a1b2c3d4e5f64789a1b2c3d4';

function createRegion(overrides = {}) {
    return {
        id: Shopwell.Utils.createId(),
        active: true,
        position: 1,
        shortCode: 'CN-13',
        code: '1301',
        translated: { name: 'Region' },
        ...overrides,
    };
}

function createCollection(items) {
    const collection = [...items];
    collection.total = items.length;

    return collection;
}

function createRepository(items) {
    return {
        search: jest.fn(() => Promise.resolve(createCollection(items))),
        create: jest.fn((context, id) => ({
            id: id ?? Shopwell.Utils.createId(),
            isNew: () => !id,
        })),
        save: jest.fn(() => Promise.resolve()),
        delete: jest.fn(() => Promise.resolve()),
    };
}

/**
 * A repository is used for loading a level and for counting it, so the criteria of a certain
 * filter has to be picked from all search calls.
 */
function findFilter(repository, field) {
    const call = repository.search.mock.calls.find(([criteria]) => {
        return criteria.filters.some((filter) => filter.field === field);
    });

    if (!call) {
        return null;
    }

    return call[0].filters.find((filter) => filter.field === field);
}

async function createWrapper({ privileges = [], isNew = false, states = [], cities = [], districts = [] } = {}) {
    const repositories = {
        country_state: createRepository(states),
        country_city: createRepository(cities),
        country_district: createRepository(districts),
    };

    const wrapper = mount(
        await wrapTestComponent('sw-settings-country-state', {
            sync: true,
        }),
        {
            props: {
                isLoading: false,
                country: {
                    id: COUNTRY_ID,
                    name: 'China',
                    isNew: () => isNew,
                },
            },

            global: {
                renderStubDefaultSlot: true,

                mocks: {
                    $t: (key, params) => (params ? `${key} ${JSON.stringify(params)}` : key),
                    $route: {
                        params: { id: COUNTRY_ID },
                        meta: {
                            $module: {
                                icon: 'regular-map',
                            },
                        },
                    },
                },

                stubs: {
                    'sw-simple-search-field': {
                        props: ['value', 'size'],
                        emits: ['update:value'],
                        template: '<input :value="value" @input="$emit(\'update:value\', $event.target.value)">',
                    },
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-country-state-detail': await wrapTestComponent('sw-country-state-detail', { sync: true }),
                    'sw-container': true,
                    'sw-modal': {
                        props: ['title'],
                        template: '<div class="sw-modal"><h2>{{ title }}</h2><slot /><slot name="modal-footer" /></div>',
                    },
                },

                provide: {
                    repositoryFactory: {
                        create: (entityName) => repositories[entityName],
                    },
                    acl: {
                        can: (identifier) => privileges.includes(identifier),
                    },
                },
            },
        },
    );

    await flushPromises();

    return { wrapper, repositories };
}

describe('module/sw-settings-country/component/sw-settings-country-state', () => {
    it('asks to save the country first when it is not persisted yet', async () => {
        const { wrapper } = await createWrapper({ isNew: true });

        expect(wrapper.find('.mt-empty-state').exists()).toBeTruthy();
        expect(wrapper.find('.mt-empty-state__headline').text()).toBe(
            'sw-settings-country.detail.region.hintNewCountryTitle',
        );
        expect(wrapper.find('.sw-settings-country-state__columns').exists()).toBeFalsy();
    });

    it('renders the three region levels of a saved country', async () => {
        const { wrapper } = await createWrapper({
            states: [
                createRegion({ translated: { name: '河北省' } }),
                createRegion({ translated: { name: '山西省' } }),
            ],
        });

        expect(wrapper.findAll('.sw-settings-country-state__column')).toHaveLength(3);
        expect(wrapper.findAll('.sw-settings-country-state__item')).toHaveLength(2);
        expect(wrapper.find('.sw-settings-country-state__item-name').text()).toBe('河北省');
    });

    it('loads the states of the given country', async () => {
        const { repositories } = await createWrapper();

        const criteria = repositories.country_state.search.mock.calls[0][0];

        expect(criteria.filters).toHaveLength(1);
        expect(criteria.filters[0].field).toBe('countryId');
        expect(criteria.filters[0].value).toBe(COUNTRY_ID);
    });

    it('loads the cities of the state that was selected', async () => {
        const state = createRegion({ translated: { name: '河北省' } });
        const city = createRegion({ translated: { name: '石家庄市' } });

        const { wrapper, repositories } = await createWrapper({
            privileges: ['country.editor'],
            states: [state],
            cities: [city],
        });

        await wrapper.find('.sw-settings-country-state__item').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.sw-settings-country-state__item')[0].classes()).toContain('is--selected');
        expect(wrapper.findAll('.sw-settings-country-state__item')).toHaveLength(2);
        expect(repositories.country_city.search).toHaveBeenCalled();

        const filter = findFilter(repositories.country_city, 'countryStateId');

        expect(filter).not.toBeNull();
        expect(filter.value).toBe(state.id);
    });

    it('loads the districts of the city that was selected', async () => {
        const state = createRegion({ translated: { name: '河北省' } });
        const city = createRegion({ translated: { name: '石家庄市' } });
        const district = createRegion({ translated: { name: '长安区' } });

        const { wrapper, repositories } = await createWrapper({
            privileges: ['country.editor'],
            states: [state],
            cities: [city],
            districts: [district],
        });

        await wrapper.findAll('.sw-settings-country-state__item')[0].trigger('click');
        await flushPromises();
        await wrapper.findAll('.sw-settings-country-state__item')[1].trigger('click');
        await flushPromises();

        expect(
            wrapper.findAll('.sw-settings-country-state__column')[1].find('.sw-settings-country-state__item').classes(),
        ).toContain('is--selected');

        const filter = findFilter(repositories.country_district, 'countryCityId');

        expect(filter).not.toBeNull();
        expect(filter.value).toBe(city.id);
    });

    it('scrolls a revealed level into view when the viewport is narrow', async () => {
        const { wrapper } = await createWrapper({
            privileges: ['country.editor'],
            states: [createRegion()],
        });

        const scrollIntoView = jest.fn();
        const columns = wrapper.findAll('.sw-settings-country-state__column').map((column) => column.element);
        columns.forEach((column) => {
            column.scrollIntoView = scrollIntoView;
        });

        // jsdom reports a 1024px wide window, which is inside the stacked layout.
        const originalQuery = document.querySelectorAll.bind(document);
        const querySelectorAll = jest.spyOn(document, 'querySelectorAll').mockImplementation((selector) => {
            return selector === '.sw-settings-country-state__column' ? columns : originalQuery(selector);
        });

        await wrapper.get('.sw-settings-country-state__item').trigger('click');
        await flushPromises();

        expect(scrollIntoView).toHaveBeenCalledTimes(1);
        expect(scrollIntoView).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });

        querySelectorAll.mockRestore();
    });

    it('filters a column by name and by code', async () => {
        const { wrapper } = await createWrapper({
            states: [
                createRegion({ translated: { name: '河北省' }, shortCode: 'CN-13' }),
                createRegion({ translated: { name: '山西省' }, shortCode: 'CN-14' }),
            ],
        });

        await wrapper.findAll('.sw-settings-country-state__search input')[0].setValue('山');
        await flushPromises();

        expect(wrapper.findAll('.sw-settings-country-state__item')).toHaveLength(1);
        expect(wrapper.find('.sw-settings-country-state__item-name').text()).toBe('山西省');

        await wrapper.findAll('.sw-settings-country-state__search input')[0].setValue('cn-13');
        await flushPromises();

        expect(wrapper.findAll('.sw-settings-country-state__item')).toHaveLength(1);
        expect(wrapper.find('.sw-settings-country-state__item-name').text()).toBe('河北省');

        await wrapper.findAll('.sw-settings-country-state__search input')[0].setValue('missing');
        await flushPromises();

        expect(wrapper.findAll('.sw-settings-country-state__item')).toHaveLength(0);
        expect(wrapper.find('.sw-settings-country-state__hint').exists()).toBeTruthy();
    });

    it('requires a selected parent before a city or district can be added', async () => {
        const { wrapper } = await createWrapper({
            privileges: ['country.editor'],
            states: [createRegion()],
            cities: [createRegion()],
        });
        const disabledLevels = () =>
            wrapper.findAll('.sw-settings-country-state__add-button').map((button) => button.element.disabled);
        expect(disabledLevels()).toEqual([false, true, true]);
        await wrapper
            .findAll('.sw-settings-country-state__column')[0]
            .get('.sw-settings-country-state__item')
            .trigger('click');
        await flushPromises();
        expect(disabledLevels()).toEqual([false, false, true]);
        await wrapper
            .findAll('.sw-settings-country-state__column')[1]
            .get('.sw-settings-country-state__item')
            .trigger('click');
        await flushPromises();
        expect(disabledLevels()).toEqual([false, false, false]);
    });

    it('disables every level without the country.editor privilege', async () => {
        const { wrapper } = await createWrapper({ states: [createRegion()] });
        expect(wrapper.findAll('.sw-settings-country-state__add-button').map((button) => button.element.disabled)).toEqual([
            true,
            true,
            true,
        ]);
    });

    it('opens the editor for a new state with the country as parent', async () => {
        const { wrapper, repositories } = await createWrapper({
            privileges: ['country.editor'],
            states: [createRegion()],
        });

        await wrapper.find('.sw-settings-country-state__add-button').trigger('click');
        await flushPromises();

        expect(wrapper.get('.sw-country-state-detail h2').text()).toBe('sw-country-state-detail.titleNewState');
        expect(wrapper.get('[name="sw-field--countryState-active"]').element.checked).toBe(true);
        await wrapper.get('[aria-label="sw-country-state-detail.labelName"]').setValue('New province');
        await wrapper.get('.sw-country-state-detail__save-button').trigger('click');
        await flushPromises();
        expect(repositories.country_state.save).toHaveBeenCalledWith(
            expect.objectContaining({
                countryId: COUNTRY_ID,
                active: true,
                name: 'New province',
            }),
            Shopwell.Context.api,
        );
    });

    it('opens the editor for an existing state when its row is clicked twice', async () => {
        const state = createRegion({ translated: { name: '河北省' } });

        const { wrapper } = await createWrapper({
            privileges: ['country.editor'],
            states: [state],
        });

        await wrapper.find('.sw-settings-country-state__item').trigger('click');
        await flushPromises();
        await wrapper.find('.sw-settings-country-state__item').trigger('click');
        await flushPromises();

        expect(wrapper.get('.sw-country-state-detail h2').text()).toBe('sw-country-state-detail.titleEditState');
        expect(wrapper.get('[aria-label="sw-country-state-detail.labelShortCode"]').element.value).toBe(state.shortCode);
    });

    it('does not open the editor without the country.editor privilege', async () => {
        const state = createRegion({ translated: { name: '河北省' } });

        const { wrapper } = await createWrapper({ states: [state] });

        await wrapper.find('.sw-settings-country-state__item').trigger('click');
        await flushPromises();

        // Browsing the tree stays possible, editing does not.
        expect(wrapper.find('.sw-country-state-detail').exists()).toBe(false);
        expect(wrapper.findAll('.sw-settings-country-state__item')[0].classes()).toContain('is--selected');
    });

    it('saves a region through its own repository', async () => {
        const { wrapper, repositories } = await createWrapper({
            privileges: ['country.editor'],
            states: [createRegion({ translated: { name: '河北省' } })],
        });

        await wrapper.get('.sw-settings-country-state__edit-action').trigger('click');
        await flushPromises();
        await wrapper.get('[aria-label="sw-country-state-detail.labelName"]').setValue('Updated province');
        await wrapper.get('.sw-country-state-detail__save-button').trigger('click');
        await flushPromises();
        expect(repositories.country_state.save).toHaveBeenCalledWith(
            expect.objectContaining({ name: 'Updated province' }),
            Shopwell.Context.api,
        );
        expect(wrapper.find('.sw-country-state-detail').exists()).toBe(false);
    });

    it('counts the children before a state is deleted', async () => {
        const state = createRegion({ translated: { name: '河北省' } });

        const { wrapper, repositories } = await createWrapper({
            privileges: ['country.editor'],
            states: [state],
            cities: [createRegion(), createRegion()],
            districts: [createRegion(), createRegion(), createRegion()],
        });

        await wrapper.get('.sw-settings-country-state__delete-action').trigger('click');
        await flushPromises();

        const message = wrapper.get('.sw-settings-country-state__delete-content').text();
        expect(message).toContain('sw-settings-country.detail.region.textDeleteStateConfirm');
        expect(message).toContain('"city":2');
        expect(message).toContain('"district":3');
        expect(repositories.country_city.search).toHaveBeenCalled();

        const filter = findFilter(repositories.country_city, 'countryStateId');

        expect(filter).not.toBeNull();
        expect(filter.value).toBe(state.id);
    });

    it('deletes a region and clears the selection it belonged to', async () => {
        const state = createRegion({ translated: { name: '河北省' } });

        const { wrapper, repositories } = await createWrapper({
            privileges: ['country.editor'],
            states: [state],
        });

        await wrapper.get('.sw-settings-country-state__item').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-country-state__delete-action').trigger('click');
        await flushPromises();
        await wrapper.get('.sw-settings-country-state__confirm-delete-button').trigger('click');
        await flushPromises();

        expect(repositories.country_state.delete).toHaveBeenCalledWith(state.id, Shopwell.Context.api);
        expect(wrapper.find('.sw-settings-country-state__item.is--selected').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-country-state__delete-content').exists()).toBe(false);
    });
});
