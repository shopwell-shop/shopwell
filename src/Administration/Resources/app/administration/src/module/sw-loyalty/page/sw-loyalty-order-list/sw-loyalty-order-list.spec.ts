/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';

type TypedWrapper<T> = { vm: T } & VueWrapper;

type Order = typeof order;

type OrderListVm = {
    orders: Order[];
    total: number;
    page: number;
    limit: number;
    isLoading: boolean;
    stateFilter: string | null;
    stateFilterOptions: { value: string | null }[];
    getStateVariant: (state: string) => string;
    formatCustomer: (order: Omit<Order, 'customer'> & { customer: Order['customer'] | null }) => string;
    formatAmount: (order: Order) => string;
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

const order = {
    id: 'order-1',
    customerId: 'customer-1',
    planName: 'Gold, one year',
    amount: 199,
    currency: 'EUR',
    state: 'activated',
    durationDays: 365,
    paymentNo: 'PAY-1',
    paidAt: '2026-01-02T10:00:00.000Z',
    expiresAt: '2027-01-02T10:00:00.000Z',
    createdAt: '2026-01-02T09:59:00.000Z',
    customer: {
        id: 'customer-1',
        firstName: 'Ada',
        lastName: 'Lovelace',
        customerNumber: '10001',
    },
};

let capturedCriteria: CapturedCriteria | null = null;

const orderRepository = {
    search: jest.fn((criteria: CapturedCriteria) => {
        capturedCriteria = criteria;

        return Promise.resolve(Object.assign([order], { total: 1 }));
    }),
};

async function createWrapper(): Promise<TypedWrapper<OrderListVm>> {
    const wrapper = mount(
        await wrapTestComponent('sw-loyalty-order-list', {
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
                            if (name === 'membership_order') {
                                return orderRepository;
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

    return wrapper as unknown as TypedWrapper<OrderListVm>;
}

describe('src/module/sw-loyalty/page/sw-loyalty-order-list', () => {
    beforeEach(() => {
        capturedCriteria = null;
        orderRepository.search.mockClear();
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should load the membership orders on creation', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(orderRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.orders).toHaveLength(1);
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
        wrapper.vm.stateFilter = 'failed';
        wrapper.vm.onFilterChange();
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);
        expect(orderRepository.search).toHaveBeenCalledTimes(2);

        const stateFilter = capturedCriteria?.filters.find((filter) => filter.field === 'state');
        expect(stateFilter?.value).toBe('failed');
    });

    it('should offer every order state as filter option', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.stateFilterOptions.map((option) => option.value)).toStrictEqual([
            null,
            'open',
            'activated',
            'failed',
        ]);
    });

    it('should map the order state to a badge variant', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.getStateVariant('activated')).toBe('success');
        expect(wrapper.vm.getStateVariant('open')).toBe('warning');
        expect(wrapper.vm.getStateVariant('failed')).toBe('error');
    });

    it('should format the customer and the amount of an order', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.formatCustomer(order)).toBe('Ada Lovelace (10001)');
        expect(wrapper.vm.formatAmount(order)).toContain('199');
    });

    it('should fall back to the customer id when the customer was not loaded', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.formatCustomer({ ...order, customer: null })).toBe('customer-1');
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
        expect(orderRepository.search).toHaveBeenLastCalledWith(expect.any(Criteria));
    });

    it('should search by term and reset the page', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.page = 4;
        wrapper.vm.onSearch('Gold');
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);
        expect(capturedCriteria?.term).toBe('Gold');
    });

    it('should reload the list when the language changed', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.onRefresh();
        await flushPromises();

        expect(orderRepository.search).toHaveBeenCalledTimes(2);
    });
});
