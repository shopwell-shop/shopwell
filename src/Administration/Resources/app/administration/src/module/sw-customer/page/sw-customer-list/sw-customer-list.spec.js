/**
 * @sw-package checkout
 */
import { config, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import Criteria from 'src/core/data/criteria.data';

const customer = { id: 'customer-1', name: 'Ada', createdById: 'admin-1', group: { translated: { name: 'Retail' } } };

function mountWithRouter(component, options) {
    const mocks = config.global.mocks;
    config.global.mocks = Object.fromEntries(Object.entries(mocks).filter(([key]) => !['$route', '$router'].includes(key)));
    try {
        return mount(component, options);
    } finally {
        config.global.mocks = mocks;
    }
}

async function createWrapper(privileges = [], { term = '', searchFields = { name: 500 }, rows = [customer] } = {}) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            {
                path: '/',
                name: 'sw.customer.index',
                component: { template: '<div/>' },
                meta: { $module: { icon: 'regular-content' } },
            },
            { path: '/profile', name: 'sw.profile.index.searchPreferences', component: { template: '<div/>' } },
        ],
    });
    await router.push({ name: 'sw.customer.index', query: { page: 1, limit: 25, term } });
    const search = jest.fn(() => Promise.resolve(Object.assign([...rows], { total: rows.length })));
    const ranking = {
        getSearchFieldsByEntity: jest.fn(() => Promise.resolve(searchFields)),
        buildSearchQueriesForEntity: jest.fn((_fields, _term, criteria) => criteria),
        isValidTerm: (value) => value && value.trim().length >= 1,
    };
    const wrapper = mountWithRouter(await wrapTestComponent('sw-customer-list', { sync: true }), {
        global: {
            plugins: [router],
            provide: {
                repositoryFactory: { create: () => ({ search }) },
                acl: { can: (identifier) => !identifier || privileges.includes(identifier) },
                filterFactory: { create: (_entity, filters) => Object.values(filters) },
                searchRankingService: ranking,
                dictionaryService: { getGenders: () => Promise.resolve([]) },
            },
            stubs: {
                'sw-page': {
                    template: '<div><slot name="smart-bar-actions"/><slot name="content"/><slot name="sidebar"/></div>',
                },
                'sw-search-bar': true,
                'sw-entity-listing': {
                    name: 'sw-entity-listing',
                    props: ['items', 'dataSource', 'columns'],
                    template: `<div><article v-for="item in (dataSource || items)" :key="item.id">
                        <slot name="column-name" :item="item" :is-inline-edit="false"/>
                        <slot name="actions" :item="item"/>
                    </article></div>`,
                },
                'sw-language-switch': true,
                'sw-context-menu-item': true,
                'router-link': true,
                'sw-avatar': true,
                'sw-text-field': true,
                'sw-label': true,
                'sw-checkbox-field': true,
                'sw-pagination': true,
                'sw-bulk-edit-modal': true,
                'sw-sidebar-item': { template: '<button class="refresh"/>' },
                'sw-sidebar-filter-panel': {
                    props: ['filters'],
                    template: `<button class="apply-filter" @click="$emit('criteria-changed', [{ type: 'equals', field: 'foo', value: 'bar' }])">Filter</button>`,
                },
                'sw-sidebar': { template: '<aside><slot/></aside>' },
                'sw-time-ago': true,
            },
        },
    });
    await flushPromises();
    return { wrapper, search, ranking };
}

Shopwell.Service().register('filterService', () => ({ mergeWithStoredFilters: (_storeKey, criteria) => criteria }));

describe('module/sw-customer/page/sw-customer-list', () => {
    it.each([
        [[], '.sw-customer-list__button-create', true],
        [['customer.creator'], '.sw-customer-list__button-create', false],
        [[], '.sw-customer-list__delete-action', true],
        [['customer.deleter'], '.sw-customer-list__delete-action', false],
        [[], '.sw-customer-list__edit-action', true],
        [['customer.editor'], '.sw-customer-list__edit-action', false],
    ])('should enforce permissions %s for %s', async (privileges, selector, disabled) => {
        const { wrapper } = await createWrapper(privileges);
        expect(wrapper.get(selector).attributes('disabled') !== undefined).toBe(disabled);
    });

    it.each([
        [[], false],
        [['customer.editor'], true],
    ])('should control inline editing by %s', async (privileges, allowed) => {
        const { wrapper } = await createWrapper(privileges);
        expect(Boolean(wrapper.get('.sw-customer-list-grid').attributes('allow-inline-edit'))).toBe(allowed);
    });

    it('should add query score to the criteria', async () => {
        const { ranking } = await createWrapper([], { term: 'foo' });
        expect(ranking.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(ranking.buildSearchQueriesForEntity).toHaveBeenCalledWith({ name: 500 }, 'foo', expect.any(Criteria));
    });

    it('should not get search ranking fields for an empty term', async () => {
        const { ranking } = await createWrapper();
        expect(ranking.getSearchFieldsByEntity).not.toHaveBeenCalled();
        expect(ranking.buildSearchQueriesForEntity).not.toHaveBeenCalled();
    });

    it('should not build query score without configured ranking fields', async () => {
        const { ranking } = await createWrapper([], { term: 'foo', searchFields: {} });
        expect(ranking.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(ranking.buildSearchQueriesForEntity).not.toHaveBeenCalled();
    });

    it('should show the empty state when the searched entity has no searchable fields', async () => {
        const { wrapper, search, ranking } = await createWrapper([], { term: 'foo', searchFields: {} });
        expect(ranking.getSearchFieldsByEntity).toHaveBeenCalledTimes(1);
        expect(search).not.toHaveBeenCalled();
        expect(wrapper.get('.mt-empty-state__headline').text()).toBe('sw-empty-state.messageNoResultTitle');
        expect(wrapper.findComponent({ name: 'sw-entity-listing' }).exists()).toBe(false);
    });

    it('should show the manual customer label', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.get('.sw-customer-list__created-by-admin-label').text()).toBe('sw-customer.list.labelCreatedByAdmin');
    });

    it('should use filters emitted by the sidebar panel', async () => {
        const { wrapper, search } = await createWrapper();
        await wrapper.get('.apply-filter').trigger('click');
        await flushPromises();
        expect(search.mock.calls.at(-1)[0].filters).toContainEqual(Criteria.equals('foo', 'bar'));
    });

    it('should sort the customer group column by its name field', async () => {
        const { wrapper } = await createWrapper();
        const columns = wrapper.getComponent({ name: 'sw-entity-listing' }).props('columns');
        expect(columns.find((column) => column.property === 'group').dataIndex).toBe('group.name');
    });

    it('should hide the address and phone number columns by default', async () => {
        const { wrapper } = await createWrapper();
        const columns = wrapper.getComponent({ name: 'sw-entity-listing' }).props('columns');
        [
            'defaultBillingAddress.street',
            'defaultBillingAddress.zipcode',
            'defaultBillingAddress.city',
            'phoneNumber',
        ].forEach((property) => {
            expect(columns.find((column) => column.property === property)).toEqual(
                expect.objectContaining({ visible: false }),
            );
        });
    });
});
