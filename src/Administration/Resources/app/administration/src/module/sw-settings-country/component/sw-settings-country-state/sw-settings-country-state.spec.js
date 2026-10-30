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
                    'sw-simple-search-field': true,
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-country-state-detail': {
                        name: 'sw-country-state-detail',
                        props: ['countryState', 'level'],
                        template: '<div class="sw-country-state-detail-stub"></div>',
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

        expect(wrapper.vm.selected.state).toBe(state.id);
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

        expect(wrapper.vm.selected.city).toBe(city.id);

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
        const querySelectorAll = jest.spyOn(document, 'querySelectorAll').mockReturnValue(columns);

        wrapper.vm.scrollLevelIntoView('city');

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

        wrapper.vm.terms.state = '山';
        await flushPromises();

        expect(wrapper.findAll('.sw-settings-country-state__item')).toHaveLength(1);
        expect(wrapper.find('.sw-settings-country-state__item-name').text()).toBe('山西省');

        wrapper.vm.terms.state = 'cn-13';
        await flushPromises();

        expect(wrapper.findAll('.sw-settings-country-state__item')).toHaveLength(1);
        expect(wrapper.find('.sw-settings-country-state__item-name').text()).toBe('河北省');

        wrapper.vm.terms.state = 'missing';
        await flushPromises();

        expect(wrapper.findAll('.sw-settings-country-state__item')).toHaveLength(0);
        expect(wrapper.find('.sw-settings-country-state__hint').exists()).toBeTruthy();
    });

    it('requires a selected parent before a city or district can be added', async () => {
        const { wrapper } = await createWrapper({
            privileges: ['country.editor'],
            states: [createRegion()],
        });

        expect(wrapper.vm.isLevelDisabled('state')).toBe(false);
        expect(wrapper.vm.isLevelDisabled('city')).toBe(true);
        expect(wrapper.vm.isLevelDisabled('district')).toBe(true);

        wrapper.vm.selected.state = wrapper.vm.regions.state[0].id;
        wrapper.vm.selected.city = 'city-id';

        expect(wrapper.vm.isLevelDisabled('city')).toBe(false);
        expect(wrapper.vm.isLevelDisabled('district')).toBe(false);
    });

    it('disables every level without the country.editor privilege', async () => {
        const { wrapper } = await createWrapper({ states: [createRegion()] });

        expect(wrapper.vm.isLevelDisabled('state')).toBe(true);
        expect(wrapper.vm.isLevelDisabled('city')).toBe(true);
        expect(wrapper.vm.isLevelDisabled('district')).toBe(true);
    });

    it('opens the editor for a new state with the country as parent', async () => {
        const { wrapper } = await createWrapper({
            privileges: ['country.editor'],
            states: [createRegion()],
        });

        await wrapper.find('.sw-settings-country-state__add-button').trigger('click');
        await flushPromises();

        expect(wrapper.vm.currentRegion).not.toBeNull();
        expect(wrapper.vm.currentRegionLevel).toBe('state');
        expect(wrapper.vm.currentRegion.countryId).toBe(COUNTRY_ID);
        expect(wrapper.vm.currentRegion.active).toBe(true);
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

        expect(wrapper.vm.currentRegion.id).toBe(state.id);
        expect(wrapper.vm.currentRegionLevel).toBe('state');
    });

    it('does not open the editor without the country.editor privilege', async () => {
        const state = createRegion({ translated: { name: '河北省' } });

        const { wrapper } = await createWrapper({ states: [state] });

        await wrapper.find('.sw-settings-country-state__item').trigger('click');
        await flushPromises();

        // Browsing the tree stays possible, editing does not.
        expect(wrapper.vm.currentRegion).toBeNull();
        expect(wrapper.vm.selected.state).toBe(state.id);
    });

    it('saves a region through its own repository', async () => {
        const { wrapper, repositories } = await createWrapper({
            privileges: ['country.editor'],
            states: [createRegion({ translated: { name: '河北省' } })],
        });

        const region = wrapper.vm.regions.state[0];
        await wrapper.vm.onSaveRegion(region);
        await flushPromises();

        expect(repositories.country_state.save).toHaveBeenCalledWith(region, Shopwell.Context.api);
        expect(wrapper.vm.currentRegion).toBeNull();
    });

    it('counts the children before a state is deleted', async () => {
        const state = createRegion({ translated: { name: '河北省' } });

        const { wrapper, repositories } = await createWrapper({
            privileges: ['country.editor'],
            states: [state],
            cities: [createRegion(), createRegion()],
            districts: [createRegion(), createRegion(), createRegion()],
        });

        wrapper.vm.openDeleteModal('state', state);
        await flushPromises();

        expect(wrapper.vm.deleteTarget.level).toBe('state');
        expect(wrapper.vm.childCounts.city).toBe(2);
        expect(wrapper.vm.childCounts.district).toBe(3);
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

        wrapper.vm.selected.state = state.id;
        wrapper.vm.openDeleteModal('state', state);
        await flushPromises();
        await wrapper.vm.onConfirmDelete();
        await flushPromises();

        expect(repositories.country_state.delete).toHaveBeenCalledWith(state.id, Shopwell.Context.api);
        expect(wrapper.vm.selected.state).toBeNull();
        expect(wrapper.vm.deleteTarget).toBeNull();
    });
});
