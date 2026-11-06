/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

import { stubs } from './sw-customer-detail-points.stubs';

type LoyaltyTierPayload = {
    id: string;
    technicalName: string;
    name: string;
    level: number;
    benefits: Record<string, number>;
};

type LoyaltySummary = {
    pointsBalance: number;
    tierPoints: number;
    tier: LoyaltyTierPayload | null;
    membershipPaidUntil: string | null;
};

type TransactionPage = { total: number; transactions: (typeof transactions)[number][] };
type LoyaltyApiService = ReturnType<typeof createLoyaltyApiService>;

/**
 * `$t` echoes the key in the unit test environment, so the labels below are asserted as keys. A
 * changed key means the operator sees a different text, which is exactly what should fail here.
 */
const loyaltySummary = {
    pointsBalance: 250,
    tierPoints: 1200,
    tier: {
        id: 'tier-1',
        technicalName: 'gold',
        name: 'Gold',
        level: 2,
        benefits: { earn_multiplier: 1.5 },
    },
    // mid year, so the assertion below holds in every time zone instead of tipping into the next year
    membershipPaidUntil: '2099-06-15T12:00:00+00:00',
};

const transactions = [
    {
        id: 'point-transaction-1',
        type: 'award',
        points: 50,
        balanceAfter: 250,
        orderId: null,
        comment: null,
        expiresAt: null,
        createdAt: '2024-02-02T00:00:00+00:00',
    },
    {
        id: 'point-transaction-2',
        type: 'redeem',
        points: -20,
        balanceAfter: 230,
        orderId: null,
        comment: 'Coupon',
        expiresAt: null,
        createdAt: '2024-02-03T00:00:00+00:00',
    },
];

const tiers = [
    { id: 'tier-1', name: 'Gold', level: 2, minTierPoints: 1000 },
    { id: 'tier-2', name: 'Platinum', level: 3, minTierPoints: 2000 },
];

const membershipPurchase = {
    id: 'membership-order-1',
    planName: 'Gold, one year',
    amount: 199,
    currency: 'EUR',
    state: 'activated',
    durationDays: 365,
    paidAt: '2024-01-01T00:00:00+00:00',
    expiresAt: '2025-01-01T00:00:00+00:00',
    createdAt: '2024-01-01T00:00:00+00:00',
};

const tierRepository = {
    search: jest.fn<Promise<typeof tiers>, []>(() => Promise.resolve(tiers)),
};

const orderRepository = {
    search: jest.fn<Promise<(typeof membershipPurchase)[]>, []>(() => Promise.resolve([membershipPurchase])),
};

function createLoyaltyApiService() {
    return {
        getCustomerSummary: jest.fn<Promise<LoyaltySummary>, []>(() => Promise.resolve(loyaltySummary)),
        getCustomerTransactions: jest.fn<Promise<TransactionPage>, []>(() => Promise.resolve({ total: 2, transactions })),
        adjustPoints: jest.fn<Promise<void>, []>(() => Promise.resolve()),
        grantMembership: jest.fn<Promise<{ startsAt: string; expiresAt: string }>, []>(() =>
            Promise.resolve({ startsAt: '2024-01-01', expiresAt: '2025-01-01' }),
        ),
    };
}

async function createWrapper(
    privileges: string[] = [],
    loyaltyApiService: LoyaltyApiService = createLoyaltyApiService(),
): Promise<{ wrapper: VueWrapper; loyaltyApiService: LoyaltyApiService }> {
    const wrapper = mount(
        await wrapTestComponent('sw-customer-detail-points', {
            sync: true,
        }),
        {
            props: {
                customer: { id: 'customer-1' },
            },
            global: {
                stubs: stubs(),
                provide: {
                    repositoryFactory: {
                        create: (name: string) => {
                            if (name === 'loyalty_tier') {
                                return tierRepository;
                            }

                            if (name === 'membership_purchase') {
                                return orderRepository;
                            }

                            throw new Error(`No repository for ${name} configured`);
                        },
                    },
                    acl: {
                        can: (identifier: string) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    loyaltyApiService,
                },
            },
        },
    );

    await flushPromises();

    return { wrapper, loyaltyApiService };
}

describe('src/module/sw-customer/page/sw-customer-detail/sw-customer-detail-points', () => {
    beforeEach(() => {
        tierRepository.search.mockClear();
        orderRepository.search.mockClear();
        tierRepository.search.mockResolvedValue(tiers);
        orderRepository.search.mockResolvedValue([membershipPurchase]);
    });

    it('shows the membership the customer holds, its validity and the balance', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-customer-detail-points').exists()).toBe(true);

        const membership = wrapper.find('.sw-customer-detail-points__membership');
        expect(membership.find('.mt-card-stub__title').text()).toBe('sw-customer.detailPoints.cardMembershipTitle');

        const values = membership.findAll('.sw-customer-detail-points__item-value');
        expect(values[0].text()).toBe('sw-customer.detailPoints.tierValue');
        expect(values[1].text()).toContain('2099');
        expect(values[2].text()).toBe('250');

        const state = membership.find('.sw-customer-detail-points__membership-state');
        expect(state.text()).toBe('sw-customer.detailPoints.membershipRunning');
        expect(state.attributes('data-type')).toBe('success');
    });

    it('lists the membership orders and the point ledger', async () => {
        const { wrapper } = await createWrapper();

        const [orderGrid, transactionGrid] = wrapper.findAll('.sw-data-grid-stub');

        expect(orderGrid.find('.sw-data-grid-stub__cell-planName').text()).toBe('Gold, one year');
        expect(orderGrid.find('.sw-data-grid-stub__cell-amount').text()).toContain('199');

        const orderState = orderGrid.find('.sw-customer-detail-points__order-state');
        expect(orderState.text()).toBe('sw-customer.detailPoints.membershipOrderState.activated');
        expect(orderState.attributes('data-type')).toBe('success');

        const rows = transactionGrid.findAll('.sw-data-grid-stub__row');
        expect(rows).toHaveLength(2);

        expect(rows[0].find('.sw-data-grid-stub__cell-createdAt').text()).toContain('2024');
        expect(rows[0].find('.sw-data-grid-stub__cell-type').text()).toBe(
            'sw-customer.detailPoints.pointTransactionType.award',
        );
        expect(rows[0].find('.sw-data-grid-stub__cell-points span').text()).toBe('50');
        expect(rows[0].find('.sw-data-grid-stub__cell-points span').classes()).not.toContain('is--negative');
        expect(rows[0].find('.sw-data-grid-stub__cell-balanceAfter').text()).toBe('250');
        expect(rows[0].find('.sw-data-grid-stub__cell-comment').text()).toBe('-');

        expect(rows[1].find('.sw-data-grid-stub__cell-type').text()).toBe(
            'sw-customer.detailPoints.pointTransactionType.redeem',
        );
        expect(rows[1].find('.sw-data-grid-stub__cell-points span').text()).toBe('-20');
        expect(rows[1].find('.sw-data-grid-stub__cell-points span').classes()).toContain('is--negative');
        expect(rows[1].find('.sw-data-grid-stub__cell-comment').text()).toBe('Coupon');
    });

    it('shows how far the customer is on the way to the next tier', async () => {
        const { wrapper } = await createWrapper();

        const growth = wrapper.find('.sw-customer-detail-points__tier-points');
        expect(growth.find('.sw-customer-detail-points__tier-points-values').text()).toBe(
            'sw-customer.detailPoints.tierPointsProgress',
        );
        // 1200 of the 1000..2000 span the Platinum tier opens at -> 20 %
        expect(growth.find('.sw-customer-detail-points__tier-points-bar').attributes('data-value')).toBe('20');
    });

    it('presents a customer without a membership', async () => {
        const loyaltyApiService = createLoyaltyApiService();
        loyaltyApiService.getCustomerSummary.mockResolvedValue({
            pointsBalance: 0,
            tierPoints: 0,
            tier: null,
            membershipPaidUntil: null,
        });

        const { wrapper } = await createWrapper([], loyaltyApiService);

        const membership = wrapper.find('.sw-customer-detail-points__membership');
        const values = membership.findAll('.sw-customer-detail-points__item-value');
        expect(values[0].text()).toBe('sw-customer.detailPoints.noTier');
        expect(values[1].text()).toBe('sw-customer.detailPoints.membershipExpiresNever');
        expect(values[2].text()).toBe('0');

        expect(membership.find('.sw-customer-detail-points__membership-state').exists()).toBe(false);

        // without a tier the customer is still measured against the entry level
        expect(membership.find('.sw-customer-detail-points__tier-points-bar').attributes('data-value')).toBe('0');
    });

    it('treats a membership without expiry as expired', async () => {
        const loyaltyApiService = createLoyaltyApiService();
        loyaltyApiService.getCustomerSummary.mockResolvedValue({
            ...loyaltySummary,
            membershipPaidUntil: null,
        });

        const { wrapper } = await createWrapper([], loyaltyApiService);

        const state = wrapper.find('.sw-customer-detail-points__membership-state');
        expect(state.text()).toBe('sw-customer.detailPoints.membershipExpired');
        expect(state.attributes('data-type')).toBe('warning');
    });

    it('renders the empty states when the customer has neither orders nor bookings', async () => {
        tierRepository.search.mockResolvedValue([]);
        orderRepository.search.mockResolvedValue([]);

        const loyaltyApiService = createLoyaltyApiService();
        loyaltyApiService.getCustomerTransactions.mockResolvedValue({ total: 0, transactions: [] });

        const { wrapper } = await createWrapper([], loyaltyApiService);

        expect(wrapper.find('.sw-data-grid-stub').exists()).toBe(false);
        expect(wrapper.findAll('.mt-empty-state-stub').map((state) => state.text())).toEqual([
            'sw-customer.detailPoints.emptyMembershipOrders',
            'sw-customer.detailPoints.emptyPointTransactions',
        ]);
        expect(wrapper.find('.sw-pagination-stub').exists()).toBe(false);
    });

    it('does not offer a points adjustment without the privilege', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-customer-detail-points__adjust-points-action').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-customer-detail-points__grant-membership-action').attributes('disabled')).toBeDefined();
    });

    it('offers a points adjustment with the privilege', async () => {
        const { wrapper } = await createWrapper(['loyalty.editor']);

        expect(wrapper.find('.sw-customer-detail-points__adjust-points-action').attributes('disabled')).toBeUndefined();
        expect(wrapper.find('.sw-customer-detail-points__grant-membership-action').attributes('disabled')).toBeUndefined();
    });

    it('books a points adjustment and reloads the balance and the ledger', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);

        await wrapper.find('.sw-customer-detail-points__adjust-points-action').trigger('click');

        const modal = wrapper.find('.sw-customer-detail-points__points-modal');
        expect(modal.exists()).toBe(true);
        expect(modal.find('.sw-modal-stub__title').text()).toBe('sw-customer.detailPoints.pointsModalTitle');
        expect(modal.find('.sw-customer-detail-points__points-modal-save').attributes('disabled')).toBeDefined();

        await modal.find('.sw-customer-detail-points__points-input').setValue(-40);
        await modal.find('.sw-customer-detail-points__points-comment').setValue('goodwill');

        expect(wrapper.find('.sw-customer-detail-points__points-modal-save').attributes('disabled')).toBeUndefined();

        await wrapper.find('.sw-customer-detail-points__points-modal-save').trigger('click');
        await flushPromises();

        expect(loyaltyApiService.adjustPoints).toHaveBeenCalledWith('customer-1', -40, 'goodwill');
        expect(loyaltyApiService.getCustomerSummary).toHaveBeenCalledTimes(2);
        expect(loyaltyApiService.getCustomerTransactions).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-customer-detail-points__points-modal').exists()).toBe(false);
    });

    it('does not book an adjustment of zero points', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);

        await wrapper.find('.sw-customer-detail-points__adjust-points-action').trigger('click');

        expect(wrapper.find('.sw-customer-detail-points__points-modal-save').attributes('disabled')).toBeDefined();

        await wrapper.find('.sw-customer-detail-points__points-modal-save').trigger('click');
        await flushPromises();

        expect(loyaltyApiService.adjustPoints).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-customer-detail-points__points-modal').exists()).toBe(true);
    });

    it('preselects the tier the customer holds when a membership is granted', async () => {
        const { wrapper } = await createWrapper(['loyalty.editor']);

        await wrapper.find('.sw-customer-detail-points__grant-membership-action').trigger('click');

        const modal = wrapper.find('.sw-customer-detail-points__membership-modal');
        expect(modal.exists()).toBe(true);
        expect(modal.find('.sw-modal-stub__title').text()).toBe('sw-customer.detailPoints.membershipModalTitle');
        expect(modal.find('.sw-customer-detail-points__membership-tier').attributes('data-selected')).toBe('tier-1');
        expect(modal.find('.sw-customer-detail-points__membership-duration').element).toHaveProperty('value', '365');
        expect(modal.findAll('option').map((option) => option.text())).toEqual([
            'Gold (sw-customer.detailPoints.tierLevel)',
            'Platinum (sw-customer.detailPoints.tierLevel)',
        ]);
    });

    it('grants the membership for the chosen tier and duration', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);

        await wrapper.find('.sw-customer-detail-points__grant-membership-action').trigger('click');
        await wrapper.find('.sw-customer-detail-points__membership-tier').setValue('tier-2');
        await wrapper.find('.sw-customer-detail-points__membership-duration').setValue(30);

        expect(wrapper.find('.sw-customer-detail-points__membership-modal-save').attributes('disabled')).toBeUndefined();

        await wrapper.find('.sw-customer-detail-points__membership-modal-save').trigger('click');
        await flushPromises();

        expect(loyaltyApiService.grantMembership).toHaveBeenCalledWith('customer-1', 'tier-2', 30);
        expect(loyaltyApiService.getCustomerSummary).toHaveBeenCalledTimes(2);
        expect(orderRepository.search).toHaveBeenCalledTimes(2);
        expect(wrapper.find('.sw-customer-detail-points__membership-modal').exists()).toBe(false);
    });

    it('refuses a membership without a tier', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);

        await wrapper.find('.sw-customer-detail-points__grant-membership-action').trigger('click');
        await wrapper.find('.sw-customer-detail-points__membership-tier').setValue('');

        expect(wrapper.find('.sw-customer-detail-points__membership-modal-save').attributes('disabled')).toBeDefined();

        await wrapper.find('.sw-customer-detail-points__membership-modal-save').trigger('click');
        await flushPromises();

        expect(loyaltyApiService.grantMembership).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-customer-detail-points__membership-modal').exists()).toBe(true);
    });

    it('reloads the ledger when the page changes', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper();
        loyaltyApiService.getCustomerTransactions.mockClear();

        await wrapper.find('.sw-pagination-stub').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-pagination-stub').attributes('data-page')).toBe('2');
        expect(loyaltyApiService.getCustomerTransactions).toHaveBeenCalledWith('customer-1', {
            page: 2,
            limit: 25,
            type: null,
        });
    });

    it('returns to the first page when the type filter changes', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper();

        await wrapper.find('.sw-pagination-stub').trigger('click');
        await flushPromises();
        loyaltyApiService.getCustomerTransactions.mockClear();

        const filter = wrapper.find('.sw-customer-detail-points__type-filter');
        expect(filter.findAll('option').map((option) => option.text())).toEqual([
            'sw-customer.detailPoints.filterTypeAll',
            'sw-customer.detailPoints.pointTransactionType.award',
            'sw-customer.detailPoints.pointTransactionType.redeem',
            'sw-customer.detailPoints.pointTransactionType.adjust',
            'sw-customer.detailPoints.pointTransactionType.expire',
        ]);

        await filter.setValue('redeem');
        await flushPromises();

        expect(loyaltyApiService.getCustomerTransactions).toHaveBeenCalledWith('customer-1', {
            page: 1,
            limit: 25,
            type: 'redeem',
        });
    });
});
