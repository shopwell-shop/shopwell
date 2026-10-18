/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';

type TypedWrapper<T> = { vm: T } & VueWrapper;

type TopUp = typeof topUp;

type TopUpListVm = {
    topUps: TopUp[];
    total: number;
    page: number;
    limit: number;
    isLoading: boolean;
    stateFilter: string | null;
    stateFilterOptions: { value: string | null }[];
    getStateVariant: (state: string) => string;
    formatCustomer: (topUp: Omit<TopUp, 'customer'> & { customer: TopUp['customer'] | null }) => string;
    formatAmount: (topUp: TopUp) => string;
    formatDate: (date: string | null) => string;
    onFilterChange: () => void;
    onPageChange: (options: { page: number; limit: number }) => void;
    onSearch: (term: string) => void;
    onRefresh: () => void;
};

type CapturedCriteria = {
    term: string | null;
    filters: { type: string; field: string; value: unknown }[];
    sortings: { field: string; order: string }[];
    associations: { association: string }[];
};

const topUp = {
    id: 'top-up-1',
    customerId: 'customer-1',
    amount: 100,
    currency: 'EUR',
    state: 'credited',
    paymentNo: 'PAY-1',
    channelTechnicalName: 'payment_sandbox',
    paidAt: '2026-01-02T10:00:00.000Z',
    creditedAt: '2026-01-02T10:00:05.000Z',
    createdAt: '2026-01-02T09:59:00.000Z',
    customer: {
        id: 'customer-1',
        firstName: 'Ada',
        lastName: 'Lovelace',
        customerNumber: '10001',
    },
};

let capturedCriteria: CapturedCriteria | null = null;

const topUpRepository = {
    search: jest.fn((criteria: CapturedCriteria) => {
        capturedCriteria = criteria;

        return Promise.resolve(Object.assign([topUp], { total: 1 }));
    }),
};

async function createWrapper(): Promise<TypedWrapper<TopUpListVm>> {
    const wrapper = mount(
        await wrapTestComponent('sw-wallet-top-up-list', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: { query: { page: 1, limit: 25 } },
                },
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="content"></slot><slot></slot></div>',
                    },
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'mt-card': {
                        template: '<div><slot name="headerRight"></slot><slot></slot></div>',
                    },
                    'sw-data-grid': true,
                    'sw-pagination': true,
                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'mt-icon': true,
                    'mt-link': true,
                    'mt-badge': true,
                    'mt-select': true,
                    'mt-empty-state': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (name: string) => {
                            if (name === 'customer_wallet_top_up') {
                                return topUpRepository;
                            }

                            throw new Error(`No repository for ${name} configured`);
                        },
                    },
                    acl: {
                        can: () => true,
                    },
                },
            },
        },
    );

    return wrapper as unknown as TypedWrapper<TopUpListVm>;
}

describe('src/module/sw-loyalty/page/sw-wallet-top-up-list', () => {
    beforeEach(() => {
        capturedCriteria = null;
        topUpRepository.search.mockClear();
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should load the top ups on creation', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(topUpRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.topUps).toHaveLength(1);
        expect(wrapper.vm.total).toBe(1);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should load the customer association and sort by creation date', async () => {
        await createWrapper();
        await flushPromises();

        const associations = (capturedCriteria?.associations ?? []).map((association) => association.association);

        expect(associations).toContain('customer');
        expect(capturedCriteria?.sortings[0]).toMatchObject({ field: 'createdAt', order: 'DESC' });
    });

    it('should not filter by state when no state is selected', async () => {
        await createWrapper();
        await flushPromises();

        expect(capturedCriteria?.filters.filter((filter) => filter.field === 'state')).toHaveLength(0);
    });

    it('should filter by state and reset the page on filter change', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.page = 3;
        wrapper.vm.stateFilter = 'credited';
        wrapper.vm.onFilterChange();
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);

        const stateFilter = capturedCriteria?.filters.find((filter) => filter.field === 'state');
        expect(stateFilter?.value).toBe('credited');
    });

    it('should offer every top up state as filter option', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.stateFilterOptions.map((option) => option.value)).toStrictEqual([
            null,
            'open',
            'paid',
            'credited',
            'failed',
        ]);
    });

    it('should map the top up state to a badge variant', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.getStateVariant('credited')).toBe('success');
        expect(wrapper.vm.getStateVariant('paid')).toBe('success');
        expect(wrapper.vm.getStateVariant('open')).toBe('warning');
        expect(wrapper.vm.getStateVariant('failed')).toBe('error');
    });

    it('should format the customer and the amount of a top up', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.formatCustomer(topUp)).toBe('Ada Lovelace (10001)');
        expect(wrapper.vm.formatAmount(topUp)).toContain('100');
    });

    it('should fall back to the customer id when the customer was not loaded', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.formatCustomer({ ...topUp, customer: null })).toBe('customer-1');
    });

    it('should render a dash for a missing date', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.formatDate(null)).toBe('-');
    });

    it('should pass the pagination to the search criteria', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onPageChange({ page: 2, limit: 10 });
        await flushPromises();

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.limit).toBe(10);
        expect(topUpRepository.search).toHaveBeenLastCalledWith(expect.any(Criteria));
    });

    it('should search by term and reset the page', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.page = 4;
        wrapper.vm.onSearch('10001');
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);
        expect(capturedCriteria?.term).toBe('10001');
    });

    it('should reload the list when the language changed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onRefresh();
        await flushPromises();

        expect(topUpRepository.search).toHaveBeenCalledTimes(2);
    });
});
