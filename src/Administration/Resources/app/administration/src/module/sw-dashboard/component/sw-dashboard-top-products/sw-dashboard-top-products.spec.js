import { shallowMount } from '@vue/test-utils';

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
    return shallowMount(await wrapTestComponent('sw-dashboard-top-products', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card"><slot name="title"></slot><slot name="headerRight"></slot><slot /></div>',
                    props: ['title', 'isLoading', 'positionIdentifier'],
                },
                'sw-chart': {
                    props: [
                        'type',
                        'series',
                        'options',
                        'height',
                    ],
                    template: `<div class="sw-chart" :data-type="type"><div v-for="row in series" :key="row.name" class="chart-series"><span class="chart-series-name">{{ row.name }}</span><span v-for="point in row.data" :key="point.x" class="chart-point" :data-value="point.y">{{ point.x }}</span></div></div>`,
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

        expect(wrapper.find('.mt-card').exists()).toBe(false);
        expect(wrapper.find('.sw-chart').exists()).toBe(false);
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

        expect(
            wrapper
                .findAll('.chart-point')
                .map((point) => ({ label: point.text(), value: Number(point.attributes('data-value')) })),
        ).toEqual([
            { label: 'Produkt A', value: 30 },
            { label: 'Produkt B', value: 12 },
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

        expect(wrapper.findAll('.chart-point').map((point) => Number(point.attributes('data-value')))).toEqual([
            9,
            8,
            7,
            5,
            3,
        ]);
    });

    it('requests all quantity buckets before ranking the top five', async () => {
        const factory = createRepositoryFactory();
        await createWrapper(['order.viewer'], factory);
        await flushPromises();
        const criteria = factory.repositories.order_line_item.search.mock.calls[0][0];
        expect(criteria.aggregations[0]).toEqual({
            name: 'topProducts',
            type: 'terms',
            field: 'productId',
            limit: null,
            sort: null,
            aggregation: { name: 'qtySum', type: 'sum', field: 'quantity' },
        });
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

        expect(wrapper.get('.chart-point').text()).toBe('deleted-product');
    });
});
