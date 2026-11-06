import { shallowMount } from '@vue/test-utils';
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

    return shallowMount(await wrapTestComponent('sw-dashboard-statistics', { sync: true }), {
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
                    emits: ['update:modelValue'],
                    template: `<select class="mt-select" :value="modelValue" @change="$emit('update:modelValue', $event.target.value)"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
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
                    template:
                        '<div class="sw-chart" :data-series="JSON.stringify(series)" :data-axes="JSON.stringify(options.yaxis)" :data-aggregation="fillEmptyValues"></div>',
                },
                'sw-extension-component-section': true,
            },
            directives: {
                tooltip: (element, binding) => {
                    element.setAttribute('title', binding.value);
                },
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

    it('plots the revenue and the order count as two series', async () => {
        statisticResponse = {
            paid: [{ date: '2026-06-14', count: 2, amount: 150 }],
            all: [{ date: '2026-06-14', count: 9, amount: 900 }],
        };

        const wrapper = await createWrapper();
        await flushPromises();

        const today = { x: new Date(2026, 5, 15).getTime(), y: 0 };
        const series = JSON.parse(wrapper.get('.sw-chart').attributes('data-series'));

        expect(series[0].name).toBe('sw-dashboard.statistics.metricTurnover');
        expect(series[0].data).toEqual([
            { x: new Date(2026, 5, 14).getTime(), y: 150 },
            today,
        ]);

        expect(series[1].name).toBe('sw-dashboard.statistics.metricOrderCount');
        expect(series[1].data).toEqual([
            { x: new Date(2026, 5, 14).getTime(), y: 9 },
            today,
        ]);
    });

    it('formats both y axes, currency left and counts right', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const axes = JSON.parse(wrapper.get('.sw-chart').attributes('data-axes'));
        expect(axes).toHaveLength(2);
        expect(axes[0].opposite).toBeFalsy();
        expect(axes[1].opposite).toBe(true);
    });

    it('offers all supported ranges', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findAll('option').map((option) => option.attributes('value'))).toEqual([
            '30Days',
            '14Days',
            '7Days',
            '24Hours',
            'yesterday',
        ]);
        expect(wrapper.get('select').element.value).toBe('30Days');
        expect(wrapper.get('.sw-chart').attributes('data-aggregation')).toBe('day');
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

        const today = { x: new Date(2026, 5, 15).getTime(), y: 0 };
        const series = JSON.parse(wrapper.get('.sw-chart').attributes('data-series'));

        expect(series[0].data).toEqual([
            { x: new Date(2026, 5, 14).getTime(), y: 150 },
            today,
        ]);
    });

    it('appends a zero point for today when there is no data yet', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(JSON.parse(wrapper.get('.sw-chart').attributes('data-series'))[0].data).toEqual([
            { x: new Date(2026, 5, 15).getTime(), y: 0 },
        ]);
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

        expect(JSON.parse(wrapper.get('.sw-chart').attributes('data-series'))[0].data).toEqual([
            { x: new Date(2026, 5, 14).getTime(), y: 10 },
            { x: new Date(2026, 5, 15).getTime(), y: 0 },
        ]);
    });

    it('reloads the data when the range changes', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(requestedUrls).toHaveLength(2);

        await wrapper.get('select').setValue('7Days');
        await flushPromises();

        expect(requestedUrls).toHaveLength(4);
        expect(wrapper.get('select').element.value).toBe('7Days');
        expect(requestedUrls[2]).toContain('2026-06-08');
    });

    it('explains the paid only revenue', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.get('.sw-dashboard-statistics__title-help-text').attributes('title')).toBe(
            'sw-dashboard.statistics.helpText',
        );
    });

    it('ships the labels of every range', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.findAll('option').forEach((option) => {
            expect(dictionary['sw-dashboard'].statistics.dateRanges[option.attributes('value')]).toBeDefined();
        });
    });
});
