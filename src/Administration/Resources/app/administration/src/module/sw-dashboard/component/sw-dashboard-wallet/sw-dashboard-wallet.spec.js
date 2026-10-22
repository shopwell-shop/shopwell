import { mount } from '@vue/test-utils';

function createRepositoryFactory(overrides = {}) {
    const repositories = {
        customer_wallet_top_up: {
            search: jest.fn().mockResolvedValue({ aggregations: {} }),
        },
        customer_wallet_transaction: {
            search: jest.fn().mockResolvedValue([]),
        },
        ...overrides,
    };

    return {
        create: (entity) => repositories[entity],
        repositories,
    };
}

async function createWrapper(privileges = ['wallet.viewer'], factory = createRepositoryFactory()) {
    return mount(await wrapTestComponent('sw-dashboard-wallet', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card"><slot name="title"></slot><slot name="headerRight"></slot><slot /></div>',
                    props: ['title', 'isLoading', 'positionIdentifier'],
                },
                'router-link': true,
                'sw-extension-component-section': true,
            },
            mocks: {
                $t: (key) => key,
            },
            provide: {
                repositoryFactory: factory,
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
            },
        },
    });
}

/**
 * @sw-package after-sales
 */
describe('module/sw-dashboard/component/sw-dashboard-wallet', () => {
    beforeEach(() => {
        Shopwell.Store.get('session').setCurrentUser({});
        Shopwell.Context.app.systemCurrencyISOCode = 'EUR';
    });

    it('does not load without wallet permission', async () => {
        const wrapper = await createWrapper([]);
        await flushPromises();

        expect(wrapper.vm.canViewWallet).toBe(false);
        expect(wrapper.vm.topUpToday).toBe(0);
        expect(wrapper.vm.transactions).toEqual([]);
    });

    it('sums only the credited top-ups of today', async () => {
        const topUpSearch = jest.fn().mockResolvedValue({
            aggregations: { topUpToday: { sum: 320 } },
        });
        const factory = createRepositoryFactory({
            customer_wallet_top_up: { search: topUpSearch },
        });

        const wrapper = await createWrapper(['wallet.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.topUpToday).toBe(320);
        expect(topUpSearch).toHaveBeenCalledTimes(1);
    });

    it('lists the latest transactions with the customer names', async () => {
        const factory = createRepositoryFactory({
            customer_wallet_transaction: {
                search: jest.fn().mockResolvedValue([
                    {
                        id: 'tx-1',
                        type: 'top_up',
                        amount: 100,
                        createdAt: '2026-06-15T08:00:00.000+00:00',
                        wallet: { customer: { firstName: 'Max', lastName: 'Mustermann' } },
                    },
                    {
                        id: 'tx-2',
                        type: 'consume',
                        amount: -20,
                        createdAt: '2026-06-15T07:00:00.000+00:00',
                        wallet: { customer: { company: 'Acme GmbH' } },
                    },
                ]),
            },
        });

        const wrapper = await createWrapper(['wallet.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.transactions).toHaveLength(2);
        expect(wrapper.vm.customerName(wrapper.vm.transactions[0])).toBe('Max Mustermann');
        expect(wrapper.vm.customerName(wrapper.vm.transactions[1])).toBe('Acme GmbH');
        expect(wrapper.vm.typeLabel(wrapper.vm.transactions[0])).toBe('sw-dashboard.wallet.types.top_up');
    });

    it('falls back for unknown customers and unknown types', async () => {
        const factory = createRepositoryFactory({
            customer_wallet_transaction: {
                search: jest.fn().mockResolvedValue([
                    {
                        id: 'tx-3',
                        type: 'mystery',
                        amount: 5,
                        createdAt: '2026-06-15T08:00:00.000+00:00',
                        wallet: { customer: null },
                    },
                ]),
            },
        });

        const wrapper = await createWrapper(['wallet.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.customerName(wrapper.vm.transactions[0])).toBe('sw-dashboard.wallet.unknownCustomer');
        expect(wrapper.vm.typeLabel(wrapper.vm.transactions[0])).toBe('mystery');
    });

    it('shows the empty state without transactions', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-wallet__empty').exists()).toBe(true);
    });
});
