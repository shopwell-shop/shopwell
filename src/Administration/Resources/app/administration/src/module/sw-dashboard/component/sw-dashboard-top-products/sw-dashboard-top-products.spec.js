import { mount } from '@vue/test-utils';

function createRepositoryFactory(overrides = {}) {
    const repositories = {
        order_line_item: {
            search: jest.fn().mockResolvedValue({ aggregations: {} }),
        },
        product: {
            search: jest.fn().mockResolvedValue([]),
        },
        ...overrides,
    };

    return {
        create: (entity) => repositories[entity],
        repositories,
    };
}

async function createWrapper(privileges = ['order.viewer'], factory = createRepositoryFactory()) {
    return mount(await wrapTestComponent('sw-dashboard-top-products', { sync: true }), {
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
describe('module/sw-dashboard/component/sw-dashboard-top-products', () => {
    beforeEach(() => {
        Shopwell.Store.get('session').setCurrentUser({});
    });

    it('does not render without order permission', async () => {
        const wrapper = await createWrapper([]);
        await flushPromises();

        expect(wrapper.vm.canViewOrders).toBe(false);
        expect(wrapper.vm.products).toEqual([]);
    });

    it('renders the empty state without buckets', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-dashboard-top-products__empty').exists()).toBe(true);
    });

    it('resolves the product names of the top buckets', async () => {
        const factory = createRepositoryFactory({
            order_line_item: {
                search: jest.fn().mockResolvedValue({
                    aggregations: {
                        topProducts: {
                            buckets: [
                                { key: 'product-a', qtySum: { sum: 30 } },
                                { key: 'product-b', qtySum: { sum: 12 } },
                            ],
                        },
                    },
                }),
            },
            product: {
                search: jest.fn().mockResolvedValue([
                    { id: 'product-a', name: 'Product A', translated: { name: 'Produkt A' } },
                    { id: 'product-b', name: 'Product B', translated: { name: 'Produkt B' } },
                ]),
            },
        });

        const wrapper = await createWrapper(['order.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.products).toEqual([
            { name: 'Produkt A', quantity: 30 },
            { name: 'Produkt B', quantity: 12 },
        ]);
        expect(wrapper.vm.series[0].data).toEqual([
            { x: 'Produkt A', y: 30 },
            { x: 'Produkt B', y: 12 },
        ]);
    });

    it('cuts the buckets to the top five and drops empty ones', async () => {
        const buckets = [
            { key: 'p1', qtySum: { sum: 1 } },
            { key: 'p2', qtySum: { sum: 0 } },
            { key: 'p3', qtySum: { sum: 9 } },
            { key: 'p4', qtySum: { sum: 5 } },
            { key: 'p5', qtySum: { sum: 7 } },
            { key: 'p6', qtySum: { sum: 3 } },
            { key: 'p7', qtySum: { sum: 8 } },
        ];

        const factory = createRepositoryFactory({
            order_line_item: {
                search: jest.fn().mockResolvedValue({
                    aggregations: { topProducts: { buckets } },
                }),
            },
            product: {
                search: jest.fn().mockResolvedValue([]),
            },
        });

        const wrapper = await createWrapper(['order.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.products.map((product) => product.quantity)).toEqual([9, 8, 7, 5, 3]);
    });

    it('falls back to the product id when the product is gone', async () => {
        const factory = createRepositoryFactory({
            order_line_item: {
                search: jest.fn().mockResolvedValue({
                    aggregations: {
                        topProducts: {
                            buckets: [{ key: 'deleted-product', qtySum: { sum: 3 } }],
                        },
                    },
                }),
            },
            product: {
                search: jest.fn().mockResolvedValue([]),
            },
        });

        const wrapper = await createWrapper(['order.viewer'], factory);
        await flushPromises();

        expect(wrapper.vm.products[0].name).toBe('deleted-product');
    });
});
