/**
 * @sw-package framework
 */
import { shallowMount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- Match the asynchronous production filter-service contract.
Shopwell.Service().register('filterService', () => {
    return {
        mergeWithStoredFilters: (_storeKey: string, criteria: unknown) => Promise.resolve(criteria),
    };
});

const transactions = [
    {
        id: 'transaction-1',
        customerWalletId: 'wallet-1',
        type: 'top_up',
        amount: 100.5,
        balanceAfter: 100.5,
        externalReference: 'topup-1',
        createdAt: '2026-01-02T03:04:05.000Z',
        wallet: {
            id: 'wallet-1',
            customerId: 'customer-1',
            customer: { id: 'customer-1', name: 'Zhang San', customerNumber: '10001' },
        },
        currency: { id: 'currency-1', isoCode: 'EUR' },
        order: null,
    },
    {
        id: 'transaction-2',
        customerWalletId: 'wallet-2',
        type: 'consume',
        amount: -12,
        balanceAfter: 88.5,
        externalReference: null,
        createdAt: '2026-01-03T03:04:05.000Z',
        wallet: {
            id: 'wallet-2',
            customerId: 'customer-2',
            customer: null,
        },
        currency: { id: 'currency-1', isoCode: 'CNY' },
        order: { id: 'order-1', orderNumber: '10042' },
    },
];

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(items: unknown[] = transactions) {
    const search = jest.fn<Promise<EntityCollection<'customer_wallet_transaction'>>, [InstanceType<typeof Criteria>]>(() =>
        Promise.resolve(collection<EntityCollection<'customer_wallet_transaction'>>(items)),
    );

    const wrapper = shallowMount(await wrapTestComponent('sw-finance-wallet-transactions-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="content"></slot>
                            <slot name="sidebar"></slot>
                        </div>`,
                },
                'sw-search-bar': { template: '<input class="search" @input="$emit(\'search\', $event.target.value)">' },
                'sw-entity-listing': {
                    props: ['dataSource', 'columns'],
                    template: `<table><thead><tr><th v-for="column in columns" :key="column.property" :data-column="column.property">{{ column.label }}</th></tr></thead>
                    <tbody><tr v-for="item in dataSource" :key="item.id" :data-row="item.id"><td v-for="column in columns" :key="column.property" :data-cell="column.property"><slot :name="'column-' + column.property" :item="item">{{ item[column.property] }}</slot></td></tr></tbody></table>`,
                },
                'sw-data-grid': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-pagination': true,
                'sw-sidebar': { template: '<aside><slot></slot></aside>' },
                'sw-sidebar-item': { template: '<button class="refresh">Refresh</button>' },
                'sw-sidebar-filter-panel': {
                    props: ['filters'],
                    template: `<section class="filters"><label v-for="filter in filters" :key="filter.property" :data-filter="filter.property">{{ filter.label }}
                    <select v-if="filter.options" @change="$emit('criteria-changed', [{ type: 'equalsAny', field: filter.property, value: $event.target.value }])"><option v-for="option in filter.options" :key="option.id" :value="option.id">{{ option.name }}</option></select>
                    <input v-else></label></section>`,
                },
                'mt-button': true,
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="type-badge" :data-variant="variant"><slot></slot></span>',
                },
                'mt-icon': true,
                'mt-link': { template: '<a><slot></slot></a>' },
                'mt-empty-state': {
                    props: ['headline', 'description'],
                    template: '<section class="empty-state"><h3>{{ headline }}</h3><p>{{ description }}</p></section>',
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => {
                        return { search };
                    },
                },
                filterFactory: {
                    create: (_entity: string, filters: Record<string, unknown>) => Object.values(filters),
                },
                acl: {
                    can: () => true,
                },
                searchRankingService: {
                    getSearchFieldsByEntity: () => Promise.resolve({}),
                    buildSearchQueriesForEntity: (_searchFields: unknown, _term: string, criteria: unknown) => criteria,
                    isValidTerm: (term: string) => Boolean(term) && term.trim().length >= 1,
                },
            },
            mocks: {
                $route: {
                    name: 'sw.finance.walletTransactions',
                    query: {},
                    meta: { $module: { icon: 'regular-money-bill' } },
                },
                $router: {
                    push: jest.fn(),
                    replace: jest.fn(),
                },
            },
        },
    });

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, search };
}

function cell(wrapper: VueWrapper, column: string, row = 0) {
    return wrapper.findAll(`[data-cell="${column}"]`)[row];
}

describe('module/sw-finance/page/sw-finance-wallet-transactions-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should load and display the ledger with wallet/customer/order associations and newest first', async () => {
        const { wrapper, search } = await createWrapper();
        expect(wrapper.get('.sw-page__smart-bar-amount').text()).toBe('(2)');
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        const criteria = search.mock.calls[0][0];
        expect(criteria.associations.map((association) => association.association)).toEqual(['wallet', 'currency', 'order']);
        expect(criteria.getAssociation('wallet').associations.map((association) => association.association)).toContain(
            'customer',
        );
        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);
    });

    it('should search the actual customer name, customer number and ledger reference', async () => {
        const { wrapper, search } = await createWrapper();
        search.mockClear();
        await wrapper.get('.search').setValue('10001');
        await wrapper.get('.refresh').trigger('click');
        await flushPromises();
        expect(search.mock.calls[0][0].filters).toEqual([
            Criteria.multi('OR', [
                Criteria.contains('wallet.customer.name', '10001'),
                Criteria.contains('wallet.customer.customerNumber', '10001'),
                Criteria.contains('externalReference', '10001'),
            ]),
        ]);
    });

    it('should render the columns in ledger reading order', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.findAll('th').map((header) => header.attributes('data-column'))).toEqual([
            'customer',
            'type',
            'amount',
            'balanceAfter',
            'order',
            'createdAt',
        ]);
    });

    it('should offer type and date filters with translated type options', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.findAll('[data-filter]').map((filter) => filter.attributes('data-filter'))).toEqual([
            'type',
            'createdAt',
        ]);
        expect(
            wrapper.findAll('[data-filter="type"] option').map((option) => [option.attributes('value'), option.text()]),
        ).toEqual([
            ['top_up', 'sw-finance.walletTransactions.type.top_up'],
            ['refund', 'sw-finance.walletTransactions.type.refund'],
            ['consume', 'sw-finance.walletTransactions.type.consume'],
            ['adjust', 'sw-finance.walletTransactions.type.adjust'],
        ]);
    });

    it.each([
        ['top_up', 'positive'],
        ['refund', 'positive'],
        ['consume', 'attention'],
        ['adjust', 'neutral'],
        ['a-type-of-the-future', 'neutral'],
    ])('should render type %s with a supported badge variant', async (type, variant) => {
        const { wrapper } = await createWrapper([{ ...transactions[0], type }]);
        expect(cell(wrapper, 'type').get('.type-badge').attributes('data-variant')).toBe(variant);
        expect(cell(wrapper, 'type').text()).toBe(`sw-finance.walletTransactions.type.${type}`);
    });

    it('should omit a badge when the ledger entry has no type', async () => {
        const { wrapper } = await createWrapper([{ ...transactions[0], type: null }]);
        expect(cell(wrapper, 'type').find('.type-badge').exists()).toBe(false);
    });

    it('should retain main-unit amounts and show their direction', async () => {
        const { wrapper } = await createWrapper();
        const format = Shopwell.Filter.getByName('currency') as (value: number, currency: string) => string;
        expect(cell(wrapper, 'amount', 0).text()).toBe(`+${format(100.5, 'EUR')}`);
        expect(cell(wrapper, 'amount', 1).text()).toBe(format(-12, 'CNY'));
    });

    it('should format each post-entry balance with its own currency', async () => {
        const { wrapper } = await createWrapper();
        const format = Shopwell.Filter.getByName('currency') as (value: number, currency: string) => string;
        expect(cell(wrapper, 'balanceAfter', 0).text()).toBe(format(100.5, 'EUR'));
        expect(cell(wrapper, 'balanceAfter', 1).text()).toBe(format(88.5, 'CNY'));
    });

    it('should render missing amounts and balances as dashes', async () => {
        const { wrapper } = await createWrapper([{ ...transactions[0], amount: null, balanceAfter: null, currency: null }]);
        expect(cell(wrapper, 'amount').text()).toBe('–');
        expect(cell(wrapper, 'balanceAfter').text()).toBe('–');
    });

    it('should show the linked order number or a dash', async () => {
        const { wrapper } = await createWrapper();
        expect(cell(wrapper, 'order', 0).text()).toBe('–');
        expect(cell(wrapper, 'order', 1).text()).toBe('10042');
    });

    it('should show the customer actual name and customer number, or the retained customer id', async () => {
        const { wrapper } = await createWrapper();
        expect(cell(wrapper, 'customer', 0).text()).toBe('Zhang San (10001)');
        expect(cell(wrapper, 'customer', 0).find('a').exists()).toBe(true);
        expect(cell(wrapper, 'customer', 1).text()).toBe('customer-2');
        expect(cell(wrapper, 'customer', 1).find('a').exists()).toBe(false);
    });

    it('should render a dash when the wallet is absent', async () => {
        const { wrapper } = await createWrapper([{ ...transactions[0], wallet: null }]);
        expect(cell(wrapper, 'customer').text()).toBe('–');
    });

    it.each([null, undefined, '2026-01-02T03:04:05.000Z'])('should display timestamp %s', async (createdAt) => {
        const { wrapper } = await createWrapper([{ ...transactions[0], createdAt }]);
        const format = Shopwell.Filter.getByName('date') as (value: string) => string;
        expect(cell(wrapper, 'createdAt').text()).toBe(createdAt ? format(createdAt) : '–');
    });

    it('should distinguish an empty ledger from an unsuccessful search', async () => {
        const { wrapper } = await createWrapper([]);
        expect(wrapper.get('.empty-state h3').text()).toBe('sw-finance.walletTransactions.list.emptyHeadline');
        await wrapper.get('.search').setValue('nomatch');
        await wrapper.get('.refresh').trigger('click');
        await flushPromises();
        expect(wrapper.get('.empty-state h3').text()).toBe('sw-finance.walletTransactions.list.noResultTitle');
    });

    it('should distinguish an empty ledger from a filter with no results', async () => {
        const { wrapper } = await createWrapper([]);
        await wrapper.get('[data-filter="type"] select').setValue('consume');
        await flushPromises();
        expect(wrapper.get('.empty-state h3').text()).toBe('sw-finance.walletTransactions.list.noResultTitle');
    });

    it('should apply the filter and reload the ledger', async () => {
        const { wrapper, search } = await createWrapper();
        search.mockClear();
        await wrapper.get('[data-filter="type"] select').setValue('consume');
        await flushPromises();
        expect(search.mock.calls[0][0].filters).toEqual([Criteria.equalsAny('type', ['consume'])]);
    });
});
