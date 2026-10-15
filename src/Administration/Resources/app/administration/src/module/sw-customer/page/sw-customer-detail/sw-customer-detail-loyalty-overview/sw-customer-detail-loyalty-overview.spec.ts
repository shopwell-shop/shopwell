/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';

const loyalty = {
    id: 'loyalty-1',
    customerId: 'customer-1',
    pointsBalance: 250,
    growthValue: 1200,
    membershipTierId: 'tier-1',
    membershipValidUntil: '2025-12-31T23:59:59+00:00',
    membershipTier: {
        id: 'tier-1',
        name: 'Gold',
        translated: {
            name: 'Gold',
        },
    },
};

const wallet = {
    id: 'wallet-1',
    currencyId: 'currency-1',
    currency: {
        id: 'currency-1',
        isoCode: 'EUR',
        name: 'Euro',
    },
    balance: 100,
    lockedBalance: 5,
};

const loyaltyRepository = {
    search: jest.fn(() => Promise.resolve([loyalty])),
};

const walletRepository = {
    search: jest.fn(() => Promise.resolve([wallet])),
};

async function createWrapper(privileges: string[] = []) {
    const wrapper = mount(
        await wrapTestComponent('sw-customer-detail-loyalty-overview', {
            sync: true,
        }),
        {
            props: {
                customer: { id: 'customer-1' },
                customerEditMode: false,
            },
            global: {
                stubs: {
                    'mt-card': {
                        template: '<div class="mt-card-stub"><slot></slot></div>',
                    },
                    'mt-badge': true,
                    'mt-link': true,
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: (name: string) => {
                            if (name === 'customer_loyalty') {
                                return loyaltyRepository;
                            }

                            if (name === 'customer_wallet') {
                                return walletRepository;
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
                },
            },
        },
    );

    await flushPromises();

    return wrapper;
}

describe('src/module/sw-customer/page/sw-customer-detail/sw-customer-detail-loyalty-overview', () => {
    beforeEach(() => {
        loyaltyRepository.search.mockClear();
        walletRepository.search.mockClear();
    });

    it('should be a vue js component', async () => {
        const wrapper = await createWrapper(['loyalty.viewer']);

        expect(wrapper.vm).toBeTruthy();
    });

    it('should hide the whole card without any loyalty or wallet privileges', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.showOverview).toBe(false);
        expect(loyaltyRepository.search).not.toHaveBeenCalled();
        expect(walletRepository.search).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-customer-detail-loyalty-overview').exists()).toBe(false);
    });

    it('should load the loyalty and the first wallet for the customer', async () => {
        const wrapper = await createWrapper(['loyalty.viewer', 'wallet.viewer']);

        expect(loyaltyRepository.search).toHaveBeenCalledTimes(1);
        expect(walletRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.loyalty).toEqual(loyalty);
        expect(wrapper.vm.wallet).toEqual(wallet);
    });

    it('should show all blocks and links with both privileges', async () => {
        const wrapper = await createWrapper(['loyalty.viewer', 'wallet.viewer']);

        expect(wrapper.findAll('.sw-customer-detail-loyalty-overview__block')).toHaveLength(3);

        const links = wrapper.findAll('.sw-customer-detail-loyalty-overview__link');
        expect(links).toHaveLength(2);
    });

    it('should show the wallet block only with the wallet privilege', async () => {
        const wrapper = await createWrapper(['wallet.viewer']);

        expect(loyaltyRepository.search).not.toHaveBeenCalled();
        expect(walletRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.findAll('.sw-customer-detail-loyalty-overview__block')).toHaveLength(1);
    });

    it('should fall back to dashes when the customer has no loyalty or wallet yet', async () => {
        loyaltyRepository.search.mockResolvedValueOnce([]);
        walletRepository.search.mockResolvedValueOnce([]);

        const wrapper = await createWrapper(['loyalty.viewer', 'wallet.viewer']);

        expect(wrapper.vm.loyalty).toBeNull();
        expect(wrapper.vm.wallet).toBeNull();
        expect(wrapper.vm.membershipTierName).toBe('sw-customer.detailLoyaltyOverview.noMembershipTier');
        expect(wrapper.vm.membershipValidUntilLabel).toBe('sw-customer.detailLoyaltyOverview.unlimited');
        expect(wrapper.vm.formatAmount(wallet.balance)).toBe('100');
    });

    it('should show a notification when the data cannot be loaded', async () => {
        loyaltyRepository.search.mockRejectedValueOnce(new Error('Failed to load loyalty'));
        const notificationSpy = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        await createWrapper(['loyalty.viewer', 'wallet.viewer']);

        expect(notificationSpy).toHaveBeenCalledWith({
            variant: 'error',
            title: 'global.default.error',
            message: 'global.notification.notificationLoadingDataErrorMessage',
        });

        notificationSpy.mockRestore();
    });
});
