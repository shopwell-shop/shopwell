/**
 * @sw-package checkout
 */
import { mount, shallowMount } from '@vue/test-utils';
import ApexCharts from 'apexcharts';

describe('sw-dashboard-loyalty-distribution ApexCharts integration', () => {
    it('creates the horizontal membership chart with the shared chart defaults', async () => {
        const distribution = shallowMount(await wrapTestComponent('sw-dashboard-loyalty-distribution', { sync: true }), {
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
                                entity === 'loyalty_account'
                                    ? {
                                          aggregations: {
                                              tierDistribution: { buckets: [{ key: 'Bronze', tierCount: { count: 30 } }] },
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
            props: distribution.getComponent({ name: 'sw-chart' }).props(),
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
            { name: 'sw-dashboard.loyaltyDistribution.seriesName', data: [{ x: 'Bronze', y: 30 }] },
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
