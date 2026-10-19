/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

type TypedWrapper<T> = { vm: T } & VueWrapper;

type Wallet = typeof wallet;
type WalletTransaction = typeof walletTransaction;
type PendingTopUp = typeof pendingTopUp;

type WalletDetailVm = {
    wallets: Wallet[];
    walletTransactions: WalletTransaction[];
    pendingTopUps: PendingTopUp[];
    total: number;
    page: number;
    typeFilter: string | null;
    allowWalletChange: boolean;
    showWalletModal: boolean;
    walletModalMode: string;
    isWalletFormValid: boolean;
    walletForm: { currencyId: string | null; amount: number; comment: string | null };
    openTopUpModal: (wallet: Wallet | null) => void;
    openAdjustWalletModal: (wallet: Wallet) => void;
    onSaveWallet: () => Promise<void>;
    setAmount: (amount: number) => void;
    resolveTypeSnippetKey: (type: string) => string;
    onFilterChange: () => void;
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

const walletTransaction = {
    id: 'wallet-transaction-1',
    type: 'top_up',
    amount: 100,
    balanceAfter: 100,
    currency: wallet.currency,
    orderId: 'order-1',
    comment: null,
    createdAt: '2024-01-01T00:00:00+00:00',
};

const pendingTopUp = {
    id: 'top-up-1',
    amount: 50,
    currency: 'EUR',
    state: 'open',
    channelTechnicalName: 'alipay_h5',
    createdAt: '2024-01-02T00:00:00+00:00',
};

const walletRepository = {
    search: jest.fn(() => Promise.resolve([wallet])),
};

interface TransactionCriteria {
    page: number;
    limit: number;
}

let lastTransactionCriteria: TransactionCriteria | null = null;

/**
 * The component always asks through a criteria object; the last one is kept so a test can check the
 * page and the filter the list was asked for.
 */
function searchTransactions(criteria: TransactionCriteria) {
    lastTransactionCriteria = criteria;

    return Promise.resolve(Object.assign([walletTransaction], { total: 1 }));
}

const walletTransactionRepository = {
    search: jest.fn(searchTransactions),
};

const topUpRepository = {
    search: jest.fn(() => Promise.resolve([pendingTopUp])),
};

function createLoyaltyApiService() {
    return {
        topUpWallet: jest.fn(() => Promise.resolve()),
        adjustWallet: jest.fn(() => Promise.resolve()),
    };
}

async function createWrapper(
    privileges: string[] = [],
    loyaltyApiService = createLoyaltyApiService(),
): Promise<{ wrapper: TypedWrapper<WalletDetailVm>; loyaltyApiService: ReturnType<typeof createLoyaltyApiService> }> {
    const wrapper = mount(
        await wrapTestComponent('sw-customer-detail-wallet', {
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
                    'mt-empty-state': true,
                    'mt-number-field': true,
                    'mt-textarea': true,
                    'mt-link': true,
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-data-grid': true,
                    'sw-entity-single-select': true,
                    'sw-pagination': {
                        template:
                            '<button class="sw-pagination-stub" @click="$emit(\'page-change\', { page: 2, limit: 25 })"></button>',
                    },
                    'sw-modal': true,
                },
                provide: {
                    repositoryFactory: {
                        create: (name: string) => {
                            if (name === 'customer_wallet') {
                                return walletRepository;
                            }

                            if (name === 'customer_wallet_transaction') {
                                return walletTransactionRepository;
                            }

                            if (name === 'customer_wallet_top_up') {
                                return topUpRepository;
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

    return { wrapper: wrapper as unknown as TypedWrapper<WalletDetailVm>, loyaltyApiService };
}

describe('src/module/sw-customer/page/sw-customer-detail/sw-customer-detail-wallet', () => {
    beforeEach(() => {
        lastTransactionCriteria = null;
        walletRepository.search.mockClear();
        walletTransactionRepository.search.mockClear();
        topUpRepository.search.mockClear();
        walletRepository.search.mockResolvedValue([wallet]);
        walletTransactionRepository.search.mockImplementation(searchTransactions);
        topUpRepository.search.mockResolvedValue([pendingTopUp]);
    });

    it('should be a vue js component', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should load the balances, the bookings and the open top ups', async () => {
        const { wrapper } = await createWrapper();

        expect(walletRepository.search).toHaveBeenCalledTimes(1);
        expect(walletTransactionRepository.search).toHaveBeenCalledTimes(1);
        expect(topUpRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.wallets).toHaveLength(1);
        expect(wrapper.vm.walletTransactions).toHaveLength(1);
        expect(wrapper.vm.pendingTopUps).toHaveLength(1);
        expect(wrapper.vm.total).toBe(1);
    });

    it('should not load transactions when the customer has no wallets', async () => {
        walletRepository.search.mockResolvedValueOnce([]);

        const { wrapper } = await createWrapper();

        expect(walletTransactionRepository.search).not.toHaveBeenCalled();
        expect(wrapper.vm.walletTransactions).toHaveLength(0);
    });

    it('should not be able to change the wallet without permission', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.allowWalletChange).toBe(false);
        expect(wrapper.find('.sw-customer-detail-wallet__top-up').attributes('disabled')).toBeDefined();
    });

    it('should be able to change the wallet with permission', async () => {
        const { wrapper } = await createWrapper(['wallet.editor']);

        expect(wrapper.vm.allowWalletChange).toBe(true);
        expect(wrapper.find('.sw-customer-detail-wallet__top-up').attributes('disabled')).toBeFalsy();
    });

    it('should top up the wallet of the given currency', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);

        wrapper.vm.openTopUpModal(wallet);
        expect(wrapper.vm.showWalletModal).toBe(true);
        expect(wrapper.vm.walletForm.currencyId).toBe('currency-1');

        wrapper.vm.walletForm.amount = 50;
        await wrapper.vm.onSaveWallet();

        expect(loyaltyApiService.topUpWallet).toHaveBeenCalledWith('customer-1', 'currency-1', 50, undefined);
        expect(wrapper.vm.showWalletModal).toBe(false);
    });

    it('should carry a typed reference into the top up', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);

        wrapper.vm.openTopUpModal(wallet);
        wrapper.vm.walletForm.amount = 50;
        wrapper.vm.walletForm.comment = 'paid in the shop';
        await wrapper.vm.onSaveWallet();

        expect(loyaltyApiService.topUpWallet).toHaveBeenCalledWith(
            'customer-1',
            'currency-1',
            50,
            'paid in the shop',
        );
    });

    it('should adjust the wallet balance', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);

        wrapper.vm.openAdjustWalletModal(wallet);
        expect(wrapper.vm.walletModalMode).toBe('adjust');

        wrapper.vm.walletForm.amount = -20;
        wrapper.vm.walletForm.comment = 'Correction';
        await wrapper.vm.onSaveWallet();

        expect(loyaltyApiService.adjustWallet).toHaveBeenCalledWith('customer-1', 'currency-1', -20, 'Correction');
    });

    it('should refuse a top up without a currency', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);

        wrapper.vm.wallets = [];
        wrapper.vm.openTopUpModal(null);

        expect(wrapper.vm.walletForm.currencyId).toBeNull();

        wrapper.vm.walletForm.amount = 50;
        await wrapper.vm.onSaveWallet();

        expect(loyaltyApiService.topUpWallet).not.toHaveBeenCalled();
    });

    it('should refuse to deduct money with a top up', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);

        wrapper.vm.openTopUpModal(wallet);
        wrapper.vm.walletForm.amount = -10;

        expect(wrapper.vm.isWalletFormValid).toBe(false);

        await wrapper.vm.onSaveWallet();

        expect(loyaltyApiService.topUpWallet).not.toHaveBeenCalled();
    });

    it('should take a quick amount over into the form', async () => {
        const { wrapper } = await createWrapper(['wallet.editor']);

        wrapper.vm.openTopUpModal(wallet);
        wrapper.vm.setAmount(500);

        expect(wrapper.vm.walletForm.amount).toBe(500);
    });

    it('should resolve the snippet key of a booking type', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.resolveTypeSnippetKey('top_up')).toBe('topUp');
        expect(wrapper.vm.resolveTypeSnippetKey('refund')).toBe('refund');
    });

    it('should start on the first page when the type filter changes', async () => {
        const { wrapper } = await createWrapper();
        walletTransactionRepository.search.mockClear();

        wrapper.vm.page = 3;
        wrapper.vm.typeFilter = 'consume';
        wrapper.vm.onFilterChange();
        await flushPromises();

        expect(wrapper.vm.page).toBe(1);
        expect(walletTransactionRepository.search).toHaveBeenCalledTimes(1);
    });

    it('should reload the transactions when the page changes', async () => {
        const { wrapper } = await createWrapper();
        walletTransactionRepository.search.mockClear();

        await wrapper.find('.sw-pagination-stub').trigger('click');
        await flushPromises();

        expect(wrapper.vm.page).toBe(2);
        expect(walletTransactionRepository.search).toHaveBeenCalledTimes(1);
        expect(lastTransactionCriteria?.page).toBe(2);
    });
});
