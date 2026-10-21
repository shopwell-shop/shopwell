/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type TypedWrapper<T> = { vm: T } & VueWrapper;

type LoyaltyTierPayload = {
    id: string;
    technicalName: string;
    name: string;
    level: number;
    pointsMultiplier: number;
};

type LoyaltySummary = {
    pointsBalance: number;
    growthValue: number;
    membershipTier: LoyaltyTierPayload | null;
    membershipValidUntil: string | null;
};

type TransactionPage = { total: number; transactions: (typeof transactions)[number][] };
type PointTransaction = (typeof transactions)[number];
type Tier = (typeof tiers)[number];
type MembershipOrder = typeof membershipOrder;

type PointsDetailVm = {
    loyaltySummary: LoyaltySummary | null;
    transactions: PointTransaction[];
    orders: MembershipOrder[];
    total: number;
    page: number;
    typeFilter: string | null;
    nextTier: Tier | null;
    growthProgress: number;
    membershipTierLabel: string;
    membershipIsRunning: boolean;
    allowAdjustPoints: boolean;
    showPointsModal: boolean;
    showMembershipModal: boolean;
    pointsForm: { points: number; comment: string | null };
    membershipForm: { tierId: string | null; durationDays: number };
    openPointsModal: () => void;
    openMembershipModal: () => void;
    onSavePoints: () => Promise<void>;
    onSaveMembership: () => Promise<void>;
    resolveTypeSnippetKey: (type: string) => string;
    getOrderStateLabel: (state: string) => string;
    onFilterChange: () => void;
};

const loyaltySummary = {
    pointsBalance: 250,
    growthValue: 1200,
    membershipTier: {
        id: 'tier-1',
        technicalName: 'gold',
        name: 'Gold',
        level: 2,
        pointsMultiplier: 1.5,
    },
    membershipValidUntil: '2099-12-31T23:59:59+00:00',
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
    { id: 'tier-1', name: 'Gold', level: 2, minGrowthValue: 1000 },
    { id: 'tier-2', name: 'Platinum', level: 3, minGrowthValue: 2000 },
];

const membershipOrder = {
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
    search: jest.fn(() => Promise.resolve(tiers)),
};

const orderRepository = {
    search: jest.fn(() => Promise.resolve([membershipOrder])),
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
    loyaltyApiService = createLoyaltyApiService(),
): Promise<{ wrapper: TypedWrapper<PointsDetailVm>; loyaltyApiService: ReturnType<typeof createLoyaltyApiService> }> {
    const wrapper = mount(
        await wrapTestComponent('sw-customer-detail-points', {
            sync: true,
        }),
        {
            props: {
                customer: { id: 'customer-1' },
            },
            global: {
                stubs: {
                    'mt-card': {
                        template: '<div><slot name="headerRight"></slot><slot></slot></div>',
                    },
                    'mt-button': true,
                    'mt-badge': true,
                    'mt-select': true,
                    'mt-progress-bar': true,
                    'mt-empty-state': true,
                    'mt-number-field': true,
                    'mt-textarea': true,
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-data-grid': true,
                    'sw-pagination': {
                        template:
                            '<button class="sw-pagination-stub" @click="$emit(\'page-change\', { page: 2, limit: 25 })"></button>',
                    },
                    'sw-modal': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (name: string) => {
                            if (name === 'loyalty_tier') {
                                return tierRepository;
                            }

                            if (name === 'membership_order') {
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

    return { wrapper: wrapper as unknown as TypedWrapper<PointsDetailVm>, loyaltyApiService };
}

describe('src/module/sw-customer/page/sw-customer-detail/sw-customer-detail-points', () => {
    beforeEach(() => {
        tierRepository.search.mockClear();
        orderRepository.search.mockClear();
        tierRepository.search.mockResolvedValue(tiers);
        orderRepository.search.mockResolvedValue([membershipOrder]);
    });

    it('should be a vue js component', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should load the summary, the ledger, the tiers and the orders', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper();

        expect(loyaltyApiService.getCustomerSummary).toHaveBeenCalledWith('customer-1');
        expect(loyaltyApiService.getCustomerTransactions).toHaveBeenCalledWith('customer-1', {
            page: 1,
            limit: 25,
            type: null,
        });
        expect(tierRepository.search).toHaveBeenCalledTimes(1);
        expect(orderRepository.search).toHaveBeenCalledTimes(1);

        expect(wrapper.vm.loyaltySummary).toEqual(loyaltySummary);
        expect(wrapper.vm.transactions).toHaveLength(2);
        expect(wrapper.vm.total).toBe(2);
        expect(wrapper.vm.orders).toHaveLength(1);
    });

    it('should present an empty customer without a tier', async () => {
        const loyaltyApiService = createLoyaltyApiService();
        loyaltyApiService.getCustomerSummary.mockResolvedValue({
            pointsBalance: 0,
            growthValue: 0,
            membershipTier: null,
            membershipValidUntil: null,
        });

        const { wrapper } = await createWrapper([], loyaltyApiService);

        expect(wrapper.vm.membershipTierLabel).toBe('sw-customer.detailPoints.noMembershipTier');
        expect(wrapper.vm.membershipIsRunning).toBe(false);
        expect(wrapper.vm.growthProgress).toBe(0);
    });

    it('should show how far the customer is on the way to the next tier', async () => {
        const { wrapper } = await createWrapper();

        // 1200 of 1000..2000 -> 20 %
        expect(wrapper.vm.nextTier?.id).toBe('tier-2');
        expect(wrapper.vm.growthProgress).toBe(20);
    });

    it('should treat a membership without expiry as not running', async () => {
        const loyaltyApiService = createLoyaltyApiService();
        loyaltyApiService.getCustomerSummary.mockResolvedValue({
            ...loyaltySummary,
            membershipValidUntil: null,
        });

        const { wrapper } = await createWrapper([], loyaltyApiService);

        expect(wrapper.vm.membershipIsRunning).toBe(false);
    });

    it('should resolve the snippet key of a booking type and an order state', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.resolveTypeSnippetKey('top_up')).toBe('topUp');
        expect(wrapper.vm.resolveTypeSnippetKey('award')).toBe('award');
        expect(wrapper.vm.getOrderStateLabel('activated')).toBe('sw-customer.detailPoints.membershipOrderState.activated');
    });

    it('should not be able to adjust points without permission', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.allowAdjustPoints).toBe(false);
        expect(wrapper.find('.sw-customer-detail-points__adjust-points-action').attributes('disabled')).toBeDefined();
    });

    it('should be able to adjust points with permission', async () => {
        const { wrapper } = await createWrapper(['loyalty.editor']);

        expect(wrapper.vm.allowAdjustPoints).toBe(true);
        expect(wrapper.find('.sw-customer-detail-points__adjust-points-action').attributes('disabled')).toBeFalsy();
    });

    it('should book an adjustment and reload the balances', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);

        wrapper.vm.openPointsModal();
        wrapper.vm.pointsForm.points = -40;
        wrapper.vm.pointsForm.comment = 'goodwill';

        await wrapper.vm.onSavePoints();

        expect(loyaltyApiService.adjustPoints).toHaveBeenCalledWith('customer-1', -40, 'goodwill');
        expect(loyaltyApiService.getCustomerSummary).toHaveBeenCalledTimes(2);
        expect(loyaltyApiService.getCustomerTransactions).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.showPointsModal).toBe(false);
    });

    it('should not book an adjustment of zero points', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);

        wrapper.vm.openPointsModal();
        wrapper.vm.pointsForm.points = 0;

        await wrapper.vm.onSavePoints();

        expect(loyaltyApiService.adjustPoints).not.toHaveBeenCalled();
    });

    it('should preselect the current tier when a membership is granted', async () => {
        const { wrapper } = await createWrapper(['loyalty.editor']);

        wrapper.vm.openMembershipModal();

        expect(wrapper.vm.membershipForm.tierId).toBe('tier-1');
        expect(wrapper.vm.membershipForm.durationDays).toBe(365);
    });

    it('should grant the membership for the chosen tier and duration', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);

        wrapper.vm.openMembershipModal();
        wrapper.vm.membershipForm.tierId = 'tier-2';
        wrapper.vm.membershipForm.durationDays = 30;

        await wrapper.vm.onSaveMembership();

        expect(loyaltyApiService.grantMembership).toHaveBeenCalledWith('customer-1', 'tier-2', 30);
        expect(wrapper.vm.showMembershipModal).toBe(false);
    });

    it('should refuse a membership without a tier', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);

        wrapper.vm.openMembershipModal();
        wrapper.vm.membershipForm.tierId = null;

        await wrapper.vm.onSaveMembership();

        expect(loyaltyApiService.grantMembership).not.toHaveBeenCalled();
    });

    it('should start on the first page when the type filter changes', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper();
        loyaltyApiService.getCustomerTransactions.mockClear();

        wrapper.vm.page = 3;
        wrapper.vm.typeFilter = 'redeem';
        wrapper.vm.onFilterChange();
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);
        expect(loyaltyApiService.getCustomerTransactions).toHaveBeenCalledWith('customer-1', {
            page: 1,
            limit: 25,
            type: 'redeem',
        });
    });

    it('should reload the ledger when the page changes', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper();
        loyaltyApiService.getCustomerTransactions.mockClear();

        await wrapper.find('.sw-pagination-stub').trigger('click');
        await flushPromises();

        expect(wrapper.vm.page).toBe(2);
        expect(loyaltyApiService.getCustomerTransactions).toHaveBeenCalledWith('customer-1', {
            page: 2,
            limit: 25,
            type: null,
        });
    });
});
