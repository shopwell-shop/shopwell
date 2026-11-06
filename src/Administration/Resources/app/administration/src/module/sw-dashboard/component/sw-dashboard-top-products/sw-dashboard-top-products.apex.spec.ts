/**
 * @sw-package after-sales
 */
import { mount, shallowMount } from '@vue/test-utils';
import ApexCharts from 'apexcharts';

describe('sw-dashboard-top-products ApexCharts integration', () => {
    it('creates the horizontal ranking chart with the shared chart defaults', async () => {
        const topProducts = shallowMount(await wrapTestComponent('sw-dashboard-top-products', { sync: true }), {
            global: {
                stubs: {
                    'sw-chart': {
                        name: 'sw-chart',
                        props: [
                            'options',
                            'series',
                            'type',
                            'height',
                        ],
                        template: '<div />',
                    },
                },
                provide: {
                    repositoryFactory: {
                        create: (entity: string) => ({
                            search: jest.fn().mockResolvedValue(
                                entity === 'order_line_item'
                                    ? {
                                          aggregations: {
                                              topProducts: { buckets: [{ key: 'Product A', qtySum: { sum: 30 } }] },
                                          },
                                      }
                                    : [],
                            ),
                        }),
                    },
                    acl: { can: () => true },
                },
            },
        });
        await flushPromises();

        const chart = mount(await wrapTestComponent('sw-chart', { sync: true }), {
            props: topProducts.getComponent({ name: 'sw-chart' }).props(),
            global: { stubs: ['apexchart'] },
        });

        await flushPromises();
        const widget = chart.getComponent({ name: 'apexchart' });
        const options = widget.props('options') as ApexCharts.ApexOptions;
        const series = widget.props('series') as ApexCharts.ApexOptions['series'];
        const type = widget.props('type') as 'bar';

        expect(type).toBe('bar');
        expect(options.plotOptions?.bar?.horizontal).toBe(true);
        expect(series).toEqual([
            { name: 'sw-dashboard.topProducts.seriesName', data: [{ x: 'Product A', y: 30 }] },
        ]);

        expect(() => {
            return new ApexCharts(document.createElement('div'), {
                ...options,
                chart: { ...options.chart, type },
                series,
            });
        }).not.toThrow();
    });
});
