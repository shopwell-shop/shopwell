/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import Criteria from 'src/core/data/criteria.data';

type CapturedCriteria = {
    page: number;
    limit: number;
    term: string | null;
    filters: { type: string; field: string; value: unknown }[];
    sortings: { field: string; order: string }[];
    associations: { association: string }[];
};

const purchase = {
    id: 'purchase-1',
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
let searchResult: unknown[] = [];

const purchaseRepository = {
    search: jest.fn((criteria: CapturedCriteria) => {
        capturedCriteria = criteria;

        return Promise.resolve(Object.assign([...searchResult], { total: searchResult.length }));
    }),
};

/**
 * The grid is stubbed so that it renders the slots of the page and nothing else - the cells are what
 * this page is responsible for, the grid around them has its own specs.
 */
const dataGridStub = {
    name: 'sw-data-grid',
    props: ['dataSource', 'columns'],
    template: `
        <div class="sw-data-grid">
            <div
                v-for="item in dataSource"
                :key="item.id"
                class="sw-data-grid__row"
            >
                <div class="sw-data-grid__cell sw-data-grid__cell--customer">
                    <slot name="column-customer" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--plan">
                    <slot name="column-planName" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--amount">
                    <slot name="column-amount" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--state">
                    <slot name="column-state" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--paid-at">
                    <slot name="column-paidAt" :item="item" />
                </div>
                <div class="sw-data-grid__cell sw-data-grid__cell--created-at">
                    <slot name="column-createdAt" :item="item" />
                </div>
            </div>
        </div>`,
};

const stateFilterStub = {
    name: 'mt-select',
    props: ['modelValue', 'options', 'label'],
    template: `
        <div class="mt-select">
            <span
                v-for="option in options"
                :key="String(option.value)"
                class="mt-select__option"
            >{{ option.label }}</span>
            <button
                class="mt-select__pick-failed"
                @click="$emit('update:model-value', 'failed')"
            >failed</button>
        </div>`,
};

const paginationStub = {
    name: 'sw-pagination',
    props: ['page', 'limit', 'total'],
    template: '<button class="sw-pagination__next-page" @click="$emit(\'page-change\', { page: 2, limit: 10 })" />',
};

async function createWrapper(can = () => true) {
    const wrapper = mount(await wrapTestComponent('sw-membership-purchase-list', { sync: true }), {
        global: {
            mocks: {
                // the wording is not under test, only which key the page looks up
                $t: (key: string) => key,
                $route: { query: { page: 1, limit: 25 } },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="search-bar"></slot>
                            <slot name="smart-bar-header"></slot>
                            <slot name="language-switch"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'mt-card': { template: '<div><slot name="headerRight"></slot><slot></slot></div>' },
                'sw-data-grid': dataGridStub,
                'mt-select': stateFilterStub,
                'sw-pagination': paginationStub,
                'sw-search-bar': {
                    props: ['initialSearch'],
                    template: '<button class="sw-search-bar__search" @click="$emit(\'search\', \'Gold\')" />',
                },
                'sw-language-switch': {
                    template: '<button class="sw-language-switch__change" @click="$emit(\'on-change\')" />',
                },
                'mt-badge': { props: ['type'], template: '<span class="mt-badge" :data-type="type"><slot></slot></span>' },
                'mt-link': { props: ['to'], template: '<a class="mt-link" :data-target="JSON.stringify(to)"><slot></slot></a>' },
                'mt-empty-state': { props: ['headline'], template: '<div class="mt-empty-state">{{ headline }}</div>' },
                'mt-icon': true,
            },
            provide: {
                repositoryFactory: {
                    create: (name: string) => {
                        if (name === 'membership_purchase') {
                            return purchaseRepository;
                        }

                        throw new Error(`No repository for ${name} configured`);
                    },
                },
                acl: { can },
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('src/module/sw-membership/page/sw-membership-purchase-list', () => {
    beforeEach(() => {
        capturedCriteria = null;
        searchResult = [purchase];
        purchaseRepository.search.mockClear();
    });

    it('asks for the purchases of the shop when the page opens', async () => {
        await createWrapper();

        expect(purchaseRepository.search).toHaveBeenCalledTimes(1);
    });

    it('loads the customer of a purchase and shows the newest first', async () => {
        await createWrapper();

        const associations = (capturedCriteria?.associations ?? []).map((association) => association.association);

        expect(associations).toContain('customer');
        expect(capturedCriteria?.sortings[0]).toMatchObject({ field: 'createdAt', order: 'DESC' });
    });

    it('renders the customer of a purchase as a link to the customer detail page', async () => {
        const wrapper = await createWrapper();

        const link = wrapper.find('.sw-data-grid__cell--customer .mt-link');

        expect(link.text()).toBe('Ada Lovelace (10001)');
        expect(link.attributes('data-target')).toContain('sw.customer.detail');
        expect(link.attributes('data-target')).toContain('customer-1');
    });

    it('falls back to the customer id when the customer was not loaded', async () => {
        searchResult = [{ ...purchase, customer: null }];

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-data-grid__cell--customer').text()).toBe('customer-1');
    });

    it('renders the plan, the amount and the state of a purchase', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-data-grid__cell--plan').text()).toBe('Gold, one year');
        expect(wrapper.find('.sw-data-grid__cell--amount').text()).toContain('199');
        expect(wrapper.find('.sw-data-grid__cell--state').text()).toBe('sw-membership.purchase.state.activated');
    });

    it('marks a paid membership as success and a failed one as error', async () => {
        const activated = await createWrapper();
        expect(activated.find('.mt-badge').attributes('data-type')).toBe('success');

        searchResult = [{ ...purchase, state: 'failed' }];
        const failed = await createWrapper();
        expect(failed.find('.mt-badge').attributes('data-type')).toBe('error');

        searchResult = [{ ...purchase, state: 'open' }];
        const open = await createWrapper();
        expect(open.find('.mt-badge').attributes('data-type')).toBe('warning');
    });

    it('renders a dash instead of an empty date', async () => {
        searchResult = [{ ...purchase, paidAt: null, createdAt: null }];

        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-data-grid__cell--paid-at').text()).toBe('-');
        expect(wrapper.find('.sw-data-grid__cell--created-at').text()).toBe('-');
    });

    it('offers every purchase state as a filter option', async () => {
        const wrapper = await createWrapper();

        const labels = wrapper.findAll('.mt-select__option').map((option) => option.text());

        expect(labels).toStrictEqual([
            'sw-membership.purchase.list.filterStateAll',
            'sw-membership.purchase.state.open',
            'sw-membership.purchase.state.activated',
            'sw-membership.purchase.state.failed',
        ]);
    });

    it('does not filter by state while no state is selected', async () => {
        await createWrapper();

        expect(capturedCriteria?.filters.filter((filter) => filter.field === 'state')).toHaveLength(0);
    });

    it('filters by the state the operator picked and jumps back to the first page', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-pagination__next-page').trigger('click');
        await flushPromises();
        expect(capturedCriteria?.page).toBe(2);

        await wrapper.find('.mt-select__pick-failed').trigger('click');
        await flushPromises();

        const stateFilter = capturedCriteria?.filters.find((filter) => filter.field === 'state');

        expect(stateFilter?.value).toBe('failed');
        expect(capturedCriteria?.page).toBe(1);
    });

    it('passes the page size the operator picked to the next search', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-pagination__next-page').trigger('click');
        await flushPromises();

        expect(capturedCriteria?.page).toBe(2);
        expect(capturedCriteria?.limit).toBe(10);
        expect(purchaseRepository.search).toHaveBeenLastCalledWith(expect.any(Criteria));
    });

    it('searches by the term the operator typed and jumps back to the first page', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-pagination__next-page').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-search-bar__search').trigger('click');
        await flushPromises();

        expect(capturedCriteria?.term).toBe('Gold');
        expect(capturedCriteria?.page).toBe(1);
    });

    it('reloads the list when the language changed', async () => {
        const wrapper = await createWrapper();

        await wrapper.find('.sw-language-switch__change').trigger('click');
        await flushPromises();

        expect(purchaseRepository.search).toHaveBeenCalledTimes(2);
    });

    it('shows an empty state instead of an empty grid when nothing was bought yet', async () => {
        searchResult = [];

        const wrapper = await createWrapper();

        expect(wrapper.find('.mt-empty-state').text()).toBe('sw-membership.purchase.list.emptyStateHeadline');
        expect(wrapper.find('.sw-data-grid__row').exists()).toBe(false);
    });
});
