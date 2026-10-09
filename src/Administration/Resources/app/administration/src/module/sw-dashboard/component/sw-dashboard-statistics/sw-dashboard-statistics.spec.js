import { mount } from '@vue/test-utils';
import dictionary from 'src/module/sw-dashboard/snippet/en.json';

let statisticResponse = {
    paid: [],
    all: [],
};

let requestedUrls = [];

async function createWrapper(privileges = ['order.viewer'], repository = {}) {
    const repositoryMock = {
        buildHeaders: () => ({ Authorization: 'Bearer test' }),
        ...repository,
    };

    Shopwell.Store.get('session').setCurrentUser({});

    return mount(await wrapTestComponent('sw-dashboard-statistics', { sync: true }), {
        global: {
            stubs: {
                'mt-card': {
                    template:
                        '<div class="mt-card"><slot name="title"></slot><slot name="headerRight"></slot><slot /></div>',
                    props: [
                        'title',
                        'subtitle',
                        'helpText',
                        'isLoading',
                        'positionIdentifier',
                    ],
                },
                'mt-select': {
                    name: 'mt-select',
                    props: ['modelValue', 'options'],
                    template: '<select class="mt-select"></select>',
                },
                'mt-icon': true,
                'sw-chart': {
                    props: [
                        'type',
                        'series',
                        'options',
                        'fillEmptyValues',
                        'height',
                        'sort',
                    ],
                    template: '<div class="sw-chart" :data-series="JSON.stringify(series)"></div>',
                },
                'sw-extension-component-section': true,
            },
            mocks: {
                $t: (key) => key,
            },
            provide: {
                repositoryFactory: {
                    create: () => repositoryMock,
                },
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
describe('module/sw-dashboard/component/sw-dashboard-statistics', () => {
    beforeAll(() => {
        Shopwell.Context.app.systemCurrencyISOCode = 'EUR';

        Shopwell.Application.addInitializer('httpClient', () => {
            return {
                get: (url) => {
                    requestedUrls.push(url);

                    return Promise.resolve({
                        data: {
                            statistic: url.includes('paid=true') ? statisticResponse.paid : statisticResponse.all,
                        },
                    });
                },
            };
        });

        jest.useFakeTimers('modern');
    });

    beforeEach(() => {
        jest.setSystemTime(new Date('2026-06-15T10:00:00.000Z'));
        statisticResponse = { paid: [], all: [] };
        requestedUrls = [];
    });

    afterAll(() => {
        jest.useRealTimers();
    });

    it('does not render the chart without order permission', async () => {
        const wrapper = await createWrapper([]);
        await flushPromises();

        expect(wrapper.find('.sw-chart').exists()).toBe(false);
    });

    it('renders a single chart', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('.sw-chart')).toHaveLength(1);
    });

    it('offers the metric switcher with revenue and order count', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.metricOptions).toEqual([
            { label: 'sw-dashboard.statistics.metricTurnover', value: 'turnover' },
            { label: 'sw-dashboard.statistics.metricOrderCount', value: 'orderCount' },
        ]);
    });

    it('offers all supported ranges', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.rangeOptions.map((option) => option.value)).toEqual([
            '30Days',
            '14Days',
            '7Days',
            '24Hours',
            'yesterday',
        ]);
        expect(wrapper.vm.activeRange).toEqual({
            label: '30Days',
            range: 30,
            interval: 'day',
            aggregate: 'day',
        });
    });

    it('requests both the paid and the overall revenue', async () => {
        await createWrapper();
        await flushPromises();

        expect(requestedUrls).toHaveLength(2);
        expect(requestedUrls[0]).toContain('paid=true');
        expect(requestedUrls[1]).toContain('paid=false');
    });

    it('plots the paid amount when the revenue metric is selected', async () => {
        statisticResponse = {
            paid: [{ date: '2026-06-14', count: 2, amount: 150 }],
            all: [{ date: '2026-06-14', count: 9, amount: 900 }],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        const today = { x: wrapper.vm.getToday().getTime(), y: 0 };

        expect(wrapper.vm.series[0].data).toEqual([
            { x: wrapper.vm.parseDate('2026-06-14'), y: 150 },
            today,
        ]);

        wrapper.vm.selectedMetric = 'orderCount';
        await flushPromises();

        expect(wrapper.vm.series[0].data).toEqual([
            { x: wrapper.vm.parseDate('2026-06-14'), y: 9 },
            today,
        ]);
    });

    it('appends a zero point for today when there is no data yet', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.series[0].data).toEqual([{ x: wrapper.vm.getToday().getTime(), y: 0 }]);
    });

    it('does not plot buckets after today', async () => {
        statisticResponse = {
            paid: [
                { date: '2026-06-14', count: 1, amount: 10 },
                { date: '2026-06-16', count: 1, amount: 99 },
            ],
            all: [],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.paidBuckets).toEqual([{ date: '2026-06-14', count: 1, amount: 10 }]);
    });

    it('reloads the data when the range changes', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(requestedUrls).toHaveLength(2);

        wrapper.vm.selectedRange = '7Days';
        await flushPromises();

        expect(requestedUrls).toHaveLength(4);
        expect(wrapper.vm.activeRange.range).toBe(7);
    });

    it('explains the paid only revenue for the revenue metric', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.helpText).toBe('sw-dashboard.statistics.helpText');

        wrapper.vm.selectedMetric = 'orderCount';
        await flushPromises();

        expect(wrapper.vm.helpText).toBe('');
    });

    it('ships the labels of every range', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.rangesValueMap.forEach((range) => {
            expect(dictionary['sw-dashboard'].statistics.dateRanges[range.label]).toBeDefined();
        });
    });
});
