/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import FilterFactory from 'src/core/data/filter-factory.data';

const { Criteria } = Shopwell.Data;
const mergeWithStoredFilters = jest.fn();
Shopwell.Service().register('filterService', () => ({ mergeWithStoredFilters }));

function collection(items) {
    return Object.assign(items, {
        total: items.length,
        getIds: () => items.map((item) => item.id),
        get: (id) => items.find((item) => item.id === id),
    });
}

async function createWrapper({ rows = [], hasSnippet = false, can = () => true, storedFilters = [] } = {}) {
    const repository = {
        search: jest.fn(() => Promise.resolve(collection(rows))),
    };

    mergeWithStoredFilters.mockReset().mockImplementation(async (storeKey, criteria) => {
        storedFilters.forEach((filter) => criteria.addFilter(filter));
        return criteria;
    });

    const wrapper = mount(await wrapTestComponent('sw-settings-sms-delivery-list', { sync: true }), {
        global: {
            mocks: {
                $t: (key) => key,
                $te: () => hasSnippet,
                // the icon of the empty state is the one of the module the route belongs to
                $route: { meta: { $module: { icon: 'regular-envelope' } } },
            },
            provide: {
                repositoryFactory: {
                    create: () => repository,
                },
                filterFactory: new FilterFactory(),
                acl: {
                    can,
                },
            },
            stubs: {
                'sw-settings-sms-page': await wrapTestComponent('sw-settings-sms-page'),
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                            <slot name="sidebar"></slot>
                        </div>`,
                },
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
                'sw-search-bar': {
                    template: `<input class="search-input" @input="$emit('search', $event.target.value)"/>`,
                },
                'sw-card-filter': {
                    template: `<input class="card-search-input" @input="$emit('sw-card-filter-term-change', $event.target.value)"/>`,
                },
                'sw-sidebar': { template: '<aside><slot/></aside>' },
                'sw-sidebar-item': {
                    emits: ['click'],
                    template: `<button class="refresh-list" @click="$emit('click')"/>`,
                },
                'sw-sidebar-filter-panel': {
                    name: 'sw-sidebar-filter-panel',
                    props: [
                        'filters',
                        'activeFilterNumber',
                        'defaults',
                        'storeKey',
                    ],
                    template: '<div class="filter-panel" :data-count="activeFilterNumber"/>',
                },
                'sw-entity-listing': {
                    name: 'sw-entity-listing',
                    props: [
                        'dataSource',
                        'isLoading',
                        'sortBy',
                        'sortDirection',
                    ],
                    template: `<section>
                        <button class="page-two" @click="$emit('page-change', { page: 2, limit: 10 })">Next</button>
                        <article v-for="item in dataSource" :key="item.id" :data-id="item.id">
                            <slot v-for="(_, name) in $slots" :name="name" :item="item"/>
                        </article>
                    </section>`,
                },
                'sw-settings-sms-section-tabs': true,
                'mt-card': { template: '<div><slot/><slot name="toolbar"/><slot name="grid"/></div>' },
                'mt-badge': { props: ['variant'], template: '<span :data-variant="variant"><slot/></span>' },
            },
        },
    });

    await flushPromises();

    return { wrapper, repository };
}

describe('src/module/sw-settings-sms/page/sw-settings-sms-delivery-list', () => {
    let wrapper;
    let repository;

    afterEach(() => {
        jest.restoreAllMocks();
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should load the log newest first', async () => {
        ({ wrapper, repository } = await createWrapper({ rows: [{ id: 'delivery-1', status: 'sent' }] }));
        expect(repository.search.mock.calls[0][0].sortings).toEqual([
            expect.objectContaining({ field: 'createdAt', order: 'DESC' }),
        ]);
        expect(wrapper.find('[data-id="delivery-1"]').exists()).toBe(true);
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(1)');
    });

    it('should apply the sidebar filters from the first page', async () => {
        ({ wrapper, repository } = await createWrapper());
        await wrapper.get('.page-two').trigger('click');
        const filters = [
            Criteria.equalsAny('status', ['failed']),
            Criteria.equalsAny('gateway.id', ['gateway-1']),
            Criteria.range('createdAt', { gte: '2026-11-01T00:00:00+00:00', lte: '2026-11-10T00:00:00+00:00' }),
        ];
        wrapper.getComponent({ name: 'sw-sidebar-filter-panel' }).vm.$emit('criteria-changed', filters);
        await flushPromises();
        const criteria = repository.search.mock.calls.at(-1)[0];
        expect(criteria.page).toBe(1);
        expect(criteria.filters).toEqual(filters);
        expect(wrapper.get('.filter-panel').attributes('data-count')).toBe('3');
    });

    it('should restore saved filters when loading the list', async () => {
        const filters = [Criteria.equalsAny('status', ['failed'])];
        ({ wrapper, repository } = await createWrapper({ storedFilters: filters }));
        expect(repository.search.mock.calls[0][0].filters).toEqual(filters);
        expect(wrapper.get('.filter-panel').attributes('data-count')).toBe('1');
    });

    it('should clear filters without clearing the search', async () => {
        ({ wrapper, repository } = await createWrapper());
        const panel = wrapper.getComponent({ name: 'sw-sidebar-filter-panel' });
        panel.vm.$emit('criteria-changed', [Criteria.equalsAny('status', ['failed'])]);
        await wrapper.get('.card-search-input').setValue('customer.otp');
        await wrapper.get('.page-two').trigger('click');
        panel.vm.$emit('criteria-changed', []);
        await flushPromises();
        const criteria = repository.search.mock.calls.at(-1)[0];
        expect(criteria.filters).toEqual([]);
        expect(criteria.page).toBe(1);
        expect(criteria.term).toBe('customer.otp');
        expect(wrapper.get('.filter-panel').attributes('data-count')).toBe('0');
    });

    it('should declare native status, account and date filters', async () => {
        ({ wrapper } = await createWrapper());
        const panel = wrapper.getComponent({ name: 'sw-sidebar-filter-panel' });
        expect(panel.props('filters')).toEqual([
            expect.objectContaining({ name: 'status-filter', property: 'status', type: 'multi-select-filter' }),
            expect.objectContaining({
                name: 'gateway-filter',
                property: 'gateway',
                schema: expect.objectContaining({ entity: 'sms_gateway' }),
                labelProperty: 'label',
            }),
            expect.objectContaining({
                name: 'created-at-filter',
                property: 'createdAt',
                type: 'date-filter',
                dateType: 'datetime',
                showTimeframe: true,
            }),
        ]);
    });

    it('should hide account filters without account read access', async () => {
        ({ wrapper } = await createWrapper({ can: () => false }));
        const filters = wrapper.getComponent({ name: 'sw-sidebar-filter-panel' }).props('filters');
        expect(filters.map((filter) => filter.name)).toEqual(['status-filter', 'created-at-filter']);
    });

    it('should search within the card and reset pagination', async () => {
        ({ wrapper, repository } = await createWrapper());
        await wrapper.get('.page-two').trigger('click');
        await wrapper.get('.card-search-input').setValue('customer.otp');
        const criteria = repository.search.mock.calls.at(-1)[0];
        expect(criteria.page).toBe(1);
        expect(criteria.term).toBe('customer.otp');
    });

    it('should refresh the list from the sidebar', async () => {
        ({ wrapper, repository } = await createWrapper());
        await wrapper.get('.refresh-list').trigger('click');
        await flushPromises();
        expect(repository.search).toHaveBeenCalledTimes(2);
    });

    it.each([
        ['sent', 'positive'],
        ['failed', 'critical'],
        ['queued', 'attention'],
    ])('should render %s as a %s badge', async (status, variant) => {
        ({ wrapper } = await createWrapper({ hasSnippet: false, rows: [{ id: 'delivery-1', status }] }));
        expect(wrapper.get('[data-variant]').attributes('data-variant')).toBe(variant);
        expect(wrapper.get('[data-variant]').text()).toBe(status);
    });
});
