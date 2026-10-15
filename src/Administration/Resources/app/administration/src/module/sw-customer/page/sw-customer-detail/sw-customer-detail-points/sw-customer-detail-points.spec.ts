/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';

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
    membershipValidUntil: '2025-12-31T23:59:59+00:00',
    transactions: [
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
    ],
};

function createLoyaltyApiService() {
    return {
        getCustomerSummary: jest.fn(() => Promise.resolve(loyaltySummary)),
        adjustPoints: jest.fn(() => Promise.resolve()),
    };
}

async function createWrapper(privileges: string[] = [], loyaltyApiService = createLoyaltyApiService()) {
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
    it('should be a vue js component', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should load the loyalty summary of the customer', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper();

        expect(loyaltyApiService.getCustomerSummary).toHaveBeenCalledWith('customer-1');
        expect(wrapper.vm.loyaltySummary).toEqual(loyaltySummary);
        expect(wrapper.vm.pointsTransactions).toHaveLength(2);
    });

    it('should handle old machines values of an empty customer', async () => {
        const loyaltyApiService = createLoyaltyApiService();
        loyaltyApiService.getCustomerSummary.mockResolvedValue({
            pointsBalance: 0,
            growthValue: 0,
            membershipTier: null,
            membershipValidUntil: null,
            transactions: [],
        });

        const { wrapper } = await createWrapper([], loyaltyApiService);

        expect(wrapper.vm.pointsTransactions).toHaveLength(0);
        expect(wrapper.vm.membershipTierLabel).toBe('sw-customer.detailPoints.noMembershipTier');
    });

    it('should resolve the snippet key of a booking type', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.resolveTypeSnippetKey('top_up')).toBe('topUp');
        expect(wrapper.vm.resolveTypeSnippetKey('award')).toBe('award');
    });

    it('should not be able to adjust points without permission', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.allowAdjustPoints).toBe(false);

        const button = wrapper.find('.sw-customer-detail-points__adjust-points-action');
        expect(button.attributes('disabled')).toBeDefined();
    });

    it('should be able to adjust points with permission', async () => {
        const { wrapper } = await createWrapper(['loyalty.editor']);

        expect(wrapper.vm.allowAdjustPoints).toBe(true);

        const button = wrapper.find('.sw-customer-detail-points__adjust-points-action');
        expect(button.attributes('disabled')).toBeFalsy();
    });

    it('should book the adjusted points and reload the summary', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);
        loyaltyApiService.getCustomerSummary.mockClear();

        wrapper.vm.openPointsModal();
        expect(wrapper.vm.showPointsModal).toBe(true);

        wrapper.vm.pointsForm.points = 100;
        wrapper.vm.pointsForm.comment = 'Goodwill';
        await wrapper.vm.onSavePoints();

        expect(loyaltyApiService.adjustPoints).toHaveBeenCalledWith('customer-1', 100, 'Goodwill');
        expect(wrapper.vm.showPointsModal).toBe(false);
        expect(loyaltyApiService.getCustomerSummary).toHaveBeenCalledTimes(1);
    });

    it('should not book points when the amount is zero', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['loyalty.editor']);

        wrapper.vm.openPointsModal();
        wrapper.vm.pointsForm.points = 0;
        await wrapper.vm.onSavePoints();

        expect(loyaltyApiService.adjustPoints).not.toHaveBeenCalled();
    });

    it('should paginate the point transactions', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.page).toBe(1);
        expect(wrapper.vm.paginatedPointsTransactions[0].id).toBe('point-transaction-1');

        await wrapper.find('.sw-pagination-stub').trigger('click');
        await flushPromises();

        expect(wrapper.vm.page).toBe(2);
        expect(wrapper.vm.paginatedPointsTransactions).toHaveLength(0);
    });
});
