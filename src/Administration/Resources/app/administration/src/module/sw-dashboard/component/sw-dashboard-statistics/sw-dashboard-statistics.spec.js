import { shallowMount } from '@vue/test-utils';

const hours = [
    { hour: 0, amount: 150, count: 2 },
    { hour: 1, amount: null, count: null },
];

async function createWrapper(props = {}, privileges = ['order.viewer']) {
    return shallowMount(await wrapTestComponent('sw-dashboard-statistics', { sync: true }), {
        props: {
            hours,
            ...props,
        },
        global: {
            stubs: {
                'mt-empty-state': {
                    props: { headline: String, description: String, icon: String, centered: Boolean },
                    template: '<div class="mt-empty-state">{{ headline }}</div>',
                },
                'mt-chart': {
                    name: 'mt-chart',
                    props: [
                        'series',
                        'options',
                        'type',
                        'height',
                    ],
                    template: '<div class="mt-chart" />',
                },
                'mt-card': {
                    template: '<div><slot name="title"/><slot name="headerRight"/><slot/></div>',
                    props: ['isLoading'],
                },
            },
            mocks: { $t: (key) => key },
            provide: { acl: { can: (privilege) => privileges.includes(privilege) } },
        },
    });
}

describe('dashboard sales trend', () => {
    beforeEach(() => {
        Shopwell.Context.app.systemCurrencyISOCode = 'EUR';
    });

    it('shows hourly sales and paid orders together with separate axes', async () => {
        const wrapper = await createWrapper();
        const charts = wrapper.findAllComponents({ name: 'mt-chart' });
        expect(charts).toHaveLength(1);
        const series = charts[0].props('series');
        expect(series).toHaveLength(2);
        expect(series[0].data).toEqual([
            { x: '00:00', y: 150 },
            { x: '01:00', y: null },
        ]);
        expect(series[1].data).toEqual([
            { x: '00:00', y: 2 },
            { x: '01:00', y: null },
        ]);
        const options = charts[0].props('options');
        expect(options.yaxis[1].opposite).toBe(true);
        expect(options.yaxis[0].seriesName).toBe(series[0].name);
        expect(options.yaxis[1].seriesName).toBe(series[1].name);
        expect(options.stroke.dashArray).toEqual([0, 5]);
        expect(wrapper.find('.sw-dashboard-statistics__caption').exists()).toBe(false);
    });

    it('shows an empty state instead of plotting zero activity', async () => {
        const wrapper = await createWrapper({
            hours: [
                { hour: 0, amount: 0, count: 0 },
                { hour: 1, amount: null, count: null },
            ],
        });
        expect(wrapper.find('.mt-chart').exists()).toBe(false);
        expect(wrapper.getComponent('.mt-empty-state').props('centered')).toBe(true);
        expect(wrapper.find('.sw-dashboard-statistics__legend').exists()).toBe(false);
    });

    it('keeps a zero amount with a successful order in the trend', async () => {
        const wrapper = await createWrapper({ hours: [{ hour: 0, amount: 0, count: 1 }] });
        expect(wrapper.find('.mt-chart').exists()).toBe(true);
    });

    it('does not show an empty state while loading', async () => {
        const wrapper = await createWrapper({ hours: [], isLoading: true });
        expect(wrapper.find('.mt-empty-state').exists()).toBe(false);
    });

    it('does not render without order permission', async () => {
        const wrapper = await createWrapper({}, []);
        expect(wrapper.find('.mt-chart').exists()).toBe(false);
    });

    it('shows an error instead of plotting failed data as zero', async () => {
        const wrapper = await createWrapper({ salesError: true });
        expect(wrapper.find('.mt-chart').exists()).toBe(false);
        expect(wrapper.text()).toContain('sw-dashboard.general.loadError');
    });
});
