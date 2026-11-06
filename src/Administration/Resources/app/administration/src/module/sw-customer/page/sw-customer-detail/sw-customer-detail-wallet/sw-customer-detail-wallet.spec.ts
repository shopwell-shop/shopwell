/**
 * @sw-package checkout
 */

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';

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
): Promise<{ wrapper: VueWrapper; loyaltyApiService: ReturnType<typeof createLoyaltyApiService> }> {
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
                    'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot/></button>' },
                    'mt-badge': true,
                    'mt-select': {
                        props: ['modelValue', 'options'],
                        template: `<select :value="modelValue" @change="$emit('update:modelValue', $event.target.value)"><option v-for="option in options" :value="option.value">{{ option.label }}</option></select>`,
                    },
                    'mt-empty-state': true,
                    'mt-number-field': {
                        props: ['modelValue'],
                        template: `<input type="number" :value="modelValue" @input="$emit('update:modelValue', Number($event.target.value))"/>`,
                    },
                    'mt-textarea': {
                        props: ['modelValue'],
                        template: `<textarea :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"/>`,
                    },
                    'mt-link': true,
                    'sw-container': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-data-grid': {
                        name: 'sw-data-grid',
                        props: ['dataSource'],
                        template: `<div><article v-for="item in dataSource" :key="item.id"><slot v-for="(_, name) in $slots" :name="name" :item="item"/></article></div>`,
                    },
                    'sw-entity-single-select': {
                        props: ['value', 'disabled'],
                        template: `<input :value="value" :disabled="disabled" @input="$emit('update:value', $event.target.value)"/>`,
                    },
                    'sw-pagination': {
                        template:
                            '<button class="sw-pagination-stub" @click="$emit(\'page-change\', { page: 2, limit: 25 })"></button>',
                    },
                    'sw-modal': {
                        props: ['title'],
                        template: '<section><h3>{{ title }}</h3><slot/><slot name="modal-footer"/></section>',
                    },
                },
                mocks: { $t: (key: string) => key },
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

    return { wrapper, loyaltyApiService };
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

    it('should render the wallet view', async () => {
        const { wrapper } = await createWrapper();
        expect(wrapper.find('.sw-customer-detail-wallet').exists()).toBe(true);
    });

    it('should load the balances, the bookings and the open top ups', async () => {
        const { wrapper } = await createWrapper();
        expect(walletRepository.search).toHaveBeenCalledTimes(1);
        expect(walletTransactionRepository.search).toHaveBeenCalledTimes(1);
        expect(topUpRepository.search).toHaveBeenCalledTimes(1);
        expect(wrapper.findAll('.sw-customer-detail-wallet__asset')).toHaveLength(1);
        expect(wrapper.findAllComponents({ name: 'sw-data-grid' })[1].props('dataSource')).toEqual(
            Object.assign([walletTransaction], { total: 1 }),
        );
        expect(wrapper.findAllComponents({ name: 'sw-data-grid' })[0].props('dataSource')).toEqual([pendingTopUp]);
        expect(wrapper.find('.sw-pagination-stub').exists()).toBe(true);
    });

    it('should not load transactions when the customer has no wallets', async () => {
        walletRepository.search.mockResolvedValueOnce([]);
        const { wrapper } = await createWrapper();
        expect(walletTransactionRepository.search).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-customer-detail-wallet__transaction-grid').exists()).toBe(false);
        expect(wrapper.find('.sw-customer-detail-wallet__assets-empty').exists()).toBe(true);
    });

    it.each([
        [[], true],
        [['wallet.editor'], false],
    ])('should restrict changes by permission (%s)', async (privileges, disabled) => {
        const { wrapper } = await createWrapper(privileges);
        expect(wrapper.get('.sw-customer-detail-wallet__top-up').attributes('disabled') !== undefined).toBe(disabled);
    });

    it('should top up the wallet of the given currency', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);
        await wrapper.get('.sw-customer-detail-wallet__asset-top-up').trigger('click');
        expect(wrapper.find('.sw-customer-detail-wallet__wallet-modal').exists()).toBe(true);
        expect(wrapper.get('.sw-customer-detail-wallet__wallet-currency').element).toHaveProperty('value', 'currency-1');
        await wrapper.get('.sw-customer-detail-wallet__wallet-amount').setValue(50);
        await wrapper.get('.sw-customer-detail-wallet__wallet-modal-save').trigger('click');
        await flushPromises();
        expect(loyaltyApiService.topUpWallet).toHaveBeenCalledWith('customer-1', 'currency-1', 50, undefined);
        expect(wrapper.find('.sw-customer-detail-wallet__wallet-modal').exists()).toBe(false);
    });

    it('should carry a typed reference into the top up', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);
        await wrapper.get('.sw-customer-detail-wallet__asset-top-up').trigger('click');
        await wrapper.get('.sw-customer-detail-wallet__wallet-amount').setValue(50);
        await wrapper.get('.sw-customer-detail-wallet__wallet-comment').setValue('paid in the shop');
        await wrapper.get('.sw-customer-detail-wallet__wallet-modal-save').trigger('click');
        await flushPromises();
        expect(loyaltyApiService.topUpWallet).toHaveBeenCalledWith('customer-1', 'currency-1', 50, 'paid in the shop');
    });

    it('should adjust the wallet balance', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);
        await wrapper.get('.sw-customer-detail-wallet__asset-adjust').trigger('click');
        expect(wrapper.find('.sw-customer-detail-wallet__quick-amounts').exists()).toBe(false);
        await wrapper.get('.sw-customer-detail-wallet__wallet-amount').setValue(-20);
        await wrapper.get('.sw-customer-detail-wallet__wallet-comment').setValue('Correction');
        await wrapper.get('.sw-customer-detail-wallet__wallet-modal-save').trigger('click');
        await flushPromises();
        expect(loyaltyApiService.adjustWallet).toHaveBeenCalledWith('customer-1', 'currency-1', -20, 'Correction');
    });

    it('should refuse a top up without a currency', async () => {
        walletRepository.search.mockResolvedValueOnce([]);
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);
        await wrapper.get('.sw-customer-detail-wallet__top-up').trigger('click');
        expect(wrapper.get('.sw-customer-detail-wallet__wallet-currency').element).toHaveProperty('value', '');
        await wrapper.get('.sw-customer-detail-wallet__wallet-amount').setValue(50);
        expect(wrapper.get('.sw-customer-detail-wallet__wallet-modal-save').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-customer-detail-wallet__wallet-modal-save').trigger('click');
        expect(loyaltyApiService.topUpWallet).not.toHaveBeenCalled();
    });

    it('should refuse to deduct money with a top up', async () => {
        const { wrapper, loyaltyApiService } = await createWrapper(['wallet.editor']);
        await wrapper.get('.sw-customer-detail-wallet__asset-top-up').trigger('click');
        await wrapper.get('.sw-customer-detail-wallet__wallet-amount').setValue(-10);
        expect(wrapper.get('.sw-customer-detail-wallet__wallet-modal-save').attributes('disabled')).toBeDefined();
        await wrapper.get('.sw-customer-detail-wallet__wallet-modal-save').trigger('click');
        expect(loyaltyApiService.topUpWallet).not.toHaveBeenCalled();
    });

    it('should take a quick amount over into the form', async () => {
        const { wrapper } = await createWrapper(['wallet.editor']);
        await wrapper.get('.sw-customer-detail-wallet__asset-top-up').trigger('click');
        await wrapper.get('.sw-customer-detail-wallet__quick-amount:nth-child(3)').trigger('click');
        expect(wrapper.get('.sw-customer-detail-wallet__wallet-amount').element).toHaveProperty('value', '500');
    });

    it.each([
        ['top_up', 'topUp'],
        ['refund', 'refund'],
    ])('should label booking type %s', async (type, snippet) => {
        walletTransactionRepository.search.mockResolvedValueOnce(
            Object.assign([{ ...walletTransaction, type }], { total: 1 }),
        );
        const { wrapper } = await createWrapper();
        expect(wrapper.get('.sw-customer-detail-wallet__transaction-grid').text()).toContain(
            `sw-customer.detailWallet.walletTransactionType.${snippet}`,
        );
    });

    it('should start on the first page when the type filter changes', async () => {
        const { wrapper } = await createWrapper();
        await wrapper.get('.sw-pagination-stub').trigger('click');
        walletTransactionRepository.search.mockClear();
        await wrapper.get('.sw-customer-detail-wallet__type-filter').setValue('consume');
        await flushPromises();
        expect(walletTransactionRepository.search).toHaveBeenCalledTimes(1);
        expect(lastTransactionCriteria?.page).toBe(1);
    });

    it('should reload the transactions when the page changes', async () => {
        const { wrapper } = await createWrapper();
        walletTransactionRepository.search.mockClear();
        await wrapper.get('.sw-pagination-stub').trigger('click');
        await flushPromises();
        expect(walletTransactionRepository.search).toHaveBeenCalledTimes(1);
        expect(lastTransactionCriteria?.page).toBe(2);
    });
});
