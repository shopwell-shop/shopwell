import { mount } from '@vue/test-utils';

function createRepositoryFactory(overrides = {}) {
    const repositories = {
        customer_loyalty: {
            search: jest.fn().mockResolvedValue({ aggregations: {} }),
        },
        loyalty_tier: {
            search: jest.fn().mockResolvedValue([]),
        },
        ...overrides,
    };

    return {
        create: (entity) => repositories[entity],
        repositories,
    };
}

async function createWrapper(privileges = ['loyalty.viewer'], factory = createRepositoryFactory()) {
    return mount(await wrapTestComponent('sw-dashboard-loyalty-distribution', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card"><slot name="title"></slot><slot name="headerRight"></slot><slot /></div>',
                    props: ['title', 'isLoading', 'positionIdentifier'],
                },
                'sw-chart': {
                    props: ['type', 'series', 'options', 'height'],
                    template: '<div class="sw-chart" :data-type="type"></div>',
                },
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
 * @sw-package checkout
 */
describe('module/sw-dashboard/component/sw-dashboard-loyalty-distribution', () => {
    beforeEach(() => {
        Shopwell.Store.get('session').setCurrentUser({});
    });

    it('does not render without loyalty permission', async () => {
        const wrapper = await createWrapper([]);
        await flushPromises();

        expect(wrapper.vm.canViewLoyalty).toBe(false);
        expect(wrapper.vm.tiers).toEqual([]);
    });

    it('renders the empty state without buckets', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-loyalty-distribution__empty').exists()).toBe(true);
    });

    it('resolves the tier names of the buckets', async () => {
        const factory = createRepositoryFactory({
            customer_loyalty: {
                search: jest.fn().mockResolvedValue({
                    aggregations: {
                        tierDistribution: {
                            buckets: [
                                { key: 'tier-1', tierCount: { count: 120 } },
                                { key: 'tier-2', tierCount: { count: 30 } },
                            ],
                        },
                    },
                }),
            },
            loyalty_tier: {
                search: jest.fn().mockResolvedValue([
                    { id: 'tier-1', name: 'Bronze', translated: { name: '铜牌' } },
                    { id: 'tier-2', name: 'Gold', translated: { name: '金牌' } },
                ]),
            },
        });

        const wrapper = await createWrapper(['loyalty.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.tiers).toEqual([
            { name: '铜牌', count: 120 },
            { name: '金牌', count: 30 },
        ]);
        expect(wrapper.vm.series[0].data).toEqual([
            { x: '铜牌', y: 120 },
            { x: '金牌', y: 30 },
        ]);
    });

    it('falls back to the bucket key when the tier is gone', async () => {
        const factory = createRepositoryFactory({
            customer_loyalty: {
                search: jest.fn().mockResolvedValue({
                    aggregations: {
                        tierDistribution: {
                            buckets: [{ key: 'deleted-tier', tierCount: { count: 2 } }],
                        },
                    },
                }),
            },
        });

        const wrapper = await createWrapper(['loyalty.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.tiers[0].name).toBe('deleted-tier');
    });
});
