import { shallowMount } from '@vue/test-utils';

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
    return shallowMount(await wrapTestComponent('sw-dashboard-wallet', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card"><slot name="title"></slot><slot name="headerRight"></slot><slot /></div>',
                    props: ['title', 'isLoading', 'positionIdentifier'],
                },
                'router-link': { props: ['to'], template: '<a :data-route="to.name"><slot /></a>' },
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

        expect(wrapper.find('.mt-card').exists()).toBe(false);
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

        expect(wrapper.get('.sw-dashboard-wallet__top-up-value').text()).toContain('320');
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
                        wallet: { customer: { name: 'Max Mustermann' } },
                    },
                    {
                        id: 'tx-2',
                        type: 'consume',
                        amount: -20,
                        createdAt: '2026-06-15T07:00:00.000+00:00',
                        wallet: { customer: { name: 'Acme GmbH' } },
                    },
                ]),
            },
        });

        const wrapper = await createWrapper(['wallet.viewer'], factory);
        await flushPromises();

        expect(wrapper.findAll('.sw-dashboard-wallet__item')).toHaveLength(2);
        expect(wrapper.findAll('.sw-dashboard-wallet__item-customer').map((customer) => customer.text())).toEqual([
            'Max Mustermann',
            'Acme GmbH',
        ]);
        expect(wrapper.findAll('.sw-dashboard-wallet__item-type')[0].text()).toBe('sw-dashboard.wallet.types.top_up');
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

        expect(wrapper.get('.sw-dashboard-wallet__item-customer').text()).toBe('sw-dashboard.wallet.unknownCustomer');
        expect(wrapper.get('.sw-dashboard-wallet__item-type').text()).toBe('mystery');
    });

    it('shows the empty state without transactions', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-wallet__empty').exists()).toBe(true);
    });
});
