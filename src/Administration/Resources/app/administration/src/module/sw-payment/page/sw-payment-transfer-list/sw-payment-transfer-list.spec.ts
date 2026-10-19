/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('filterService', () => {
    return {
        // the real service is async (src/app/service/filter.service.js), the double has to be too -
        // otherwise a missing await in the page is invisible to this spec
        mergeWithStoredFilters: (_storeKey: string, criteria: unknown) => Promise.resolve(criteria),
    };
});

const transferStates = [
    { id: 'state-succeeded', name: 'succeeded', technicalName: 'succeeded', translated: { name: '已成功' } },
    { id: 'state-failed', name: 'failed', technicalName: 'failed', translated: { name: '已失败' } },
];

const transfers = [
    {
        id: 'transfer-1',
        transferNo: 'T-1',
        outNo: 'O-1',
        provider: 'wechat',
        channel: 'wechat_transfer',
        amount: 500,
        currency: 'CNY',
        statusId: 'state-succeeded',
        createdAt: '2026-01-02T03:04:05.000Z',
        recipient: { holderName: '张三', providerAccountId: 'openid-1' },
    },
];

type TransferListVm = {
    defaultFilters: string[];
    listFilterOptions: Record<string, unknown>;
    criteria: {
        term: string;
        sortings: unknown[];
        associations: Array<{ association: string }>;
        filters: unknown[];
    };
    stateFilterOptions: Array<{ id: string; name: string }>;
    hasActiveSearchOrFilter: boolean;
    activeFilterNumber: number;
    page: number;
    total: number;
    transfers: unknown[];
    isLoading: boolean;
    term: string;
    updateCriteria: (filters: unknown[]) => void;
    transferStateLabel: (stateId: string) => string;
    transferStateVariant: (stateId: string) => string;
    recipientLabel: (item: unknown) => string;
    formatAmount: (amount: number, currency: string) => string;
    formatDateOrDash: (value: string | null) => string;
};

type TypedWrapper = { vm: TransferListVm } & VueWrapper;

function collection<T>(items: unknown[]): T {
    return Object.assign([...items], { total: items.length }) as unknown as T;
}

let activeWrapper: VueWrapper | null = null;

async function createWrapper(): Promise<{
    wrapper: TypedWrapper;
    search: jest.Mock;
    stateSearch: jest.Mock;
}> {
    const search = jest.fn(() => Promise.resolve(collection<EntityCollection<'payment_transfer'>>(transfers)));

    const stateSearch = jest.fn(() =>
        Promise.resolve(collection<EntityCollection<'state_machine_state'>>(transferStates)),
    );

    const wrapper = mount(await wrapTestComponent('sw-payment-transfer-list', { sync: true }), {
        global: {
            stubs: {
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
                'sw-search-bar': true,
                'sw-entity-listing': true,
                'sw-data-grid': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-pagination': true,
                'sw-data-grid-settings': true,
                'sw-data-grid-skeleton': true,
                'sw-sidebar': true,
                'sw-sidebar-item': true,
                'sw-sidebar-filter-panel': true,
                'router-link': { template: '<a><slot></slot></a>' },
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-empty-state': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity: string) => {
                        return entity === 'state_machine_state' ? { search: stateSearch } : { search };
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
                    buildSearchQueriesForEntity: (_searchFields: unknown, _term: string, criteria: unknown) =>
                        criteria,
                    isValidTerm: (term: string) => Boolean(term) && term.trim().length >= 1,
                },
            },
            mocks: {
                $route: {
                    name: 'sw.payment.transfer',
                    query: {},
                    meta: { $module: { icon: 'regular-credit-card' } },
                },
                $router: {
                    push: jest.fn(),
                    replace: jest.fn(),
                },
            },
        },
    }) as unknown as TypedWrapper;

    activeWrapper = wrapper;

    await flushPromises();

    return { wrapper, search, stateSearch };
}

describe('module/sw-payment/page/sw-payment-transfer-list', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should have a default filter for every filter option', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.defaultFilters).toEqual([
            'transfer-no-filter',
            'out-no-filter',
            'channel-filter',
            'provider-filter',
            'state-filter',
        ]);

        wrapper.vm.defaultFilters.forEach((filterKey) => {
            expect(wrapper.vm.listFilterOptions).toHaveProperty(filterKey);
        });
    });

    it('should keep the search, the sorting and the recipient in the criteria', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.term = 'T-1';

        const criteria = wrapper.vm.criteria;

        expect(criteria.term).toBe('T-1');
        expect(criteria.sortings).toEqual([{ field: 'createdAt', order: 'DESC', naturalSorting: false }]);
        expect(criteria.associations.map((association) => association.association)).toContain('recipient');
    });

    it('should load the transfers into the listing state', async () => {
        const { wrapper, search, stateSearch } = await createWrapper();

        expect(search).toHaveBeenCalled();
        expect(wrapper.vm.total).toBe(1);
        expect(wrapper.vm.transfers).toHaveLength(1);
        expect(wrapper.vm.isLoading).toBe(false);

        // the states are read once, not with every list request
        expect(stateSearch).toHaveBeenCalledTimes(1);
    });

    it('should offer the states of the transfer machine as filter options', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.stateFilterOptions).toEqual([
            { id: 'state-succeeded', name: '已成功' },
            { id: 'state-failed', name: '已失败' },
        ]);
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(false);

        wrapper.vm.activeFilterNumber = 1;
        expect(wrapper.vm.hasActiveSearchOrFilter).toBe(true);
    });

    it('should apply the filters the filter panel emits and reload the list', async () => {
        const { wrapper, search } = await createWrapper();

        search.mockClear();

        const filters = [Criteria.equalsAny('channel', ['wechat_transfer'])];
        wrapper.vm.updateCriteria(filters);
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.criteria.filters).toEqual(filters);
        expect(search).toHaveBeenCalled();
    });

    it('should render the state, the recipient and the amount of a transfer', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.transferStateLabel('state-succeeded')).toBe('已成功');
        expect(wrapper.vm.transferStateVariant('state-succeeded')).toBe('positive');
        expect(wrapper.vm.transferStateVariant('state-failed')).toBe('critical');
        expect(wrapper.vm.recipientLabel(transfers[0])).toBe('张三');
        expect(wrapper.vm.recipientLabel({ recipient: { providerAccountId: 'openid-1' } })).toBe('openid-1');
        expect(wrapper.vm.recipientLabel({})).toBe('-');
        expect(wrapper.vm.formatAmount(500, 'CNY')).toBe(Shopwell.Utils.format.currency(5, 'CNY', 2));
    });

    it('should render a dash instead of an invalid date', async () => {
        const { wrapper } = await createWrapper();
        const dateFilter = Shopwell.Filter.getByName('date') as (value: string) => string;

        expect(wrapper.vm.formatDateOrDash(null)).toBe('–');
        expect(wrapper.vm.formatDateOrDash('2026-01-02T03:04:05.000Z')).toBe(
            dateFilter('2026-01-02T03:04:05.000Z'),
        );
    });
});
