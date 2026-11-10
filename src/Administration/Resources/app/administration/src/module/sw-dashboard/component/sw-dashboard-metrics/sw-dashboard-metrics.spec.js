import { shallowMount } from '@vue/test-utils';

const summary = {
    today: {
        revenue: 200,
        orders: 4,
        customers: 3,
        refundAmount: 20,
        refundCount: 2,
        memberships: 5,
        walletTopUpAmount: 80,
        walletTopUpCount: 2,
    },
    previous: {
        revenue: 100,
        orders: 2,
        customers: 2,
        refundAmount: 10,
        memberships: 2,
        walletTopUpAmount: 40,
        walletTopUpCount: 1,
    },
};

async function createWrapper(props = {}, allowed = true) {
    return shallowMount(await wrapTestComponent('sw-dashboard-metrics', { sync: true }), {
        props: { summary, ...props },
        global: {
            stubs: { 'mt-card': { template: '<article><slot/></article>' } },
            mocks: { $t: (key, values) => `${key}${values?.count ?? ''}` },
            provide: { acl: { can: (permission) => (Array.isArray(allowed) ? allowed.includes(permission) : allowed) } },
        },
    });
}

describe('dashboard today metrics', () => {
    beforeEach(() => {
        Shopwell.Context.app.systemCurrencyISOCode = 'EUR';
    });

    it('shows four distinct metrics and only the order count under revenue', async () => {
        const wrapper = await createWrapper();
        const cards = wrapper.findAll('.sw-dashboard-metrics__card');
        expect(cards).toHaveLength(4);
        expect(cards.map((card) => card.classes().find((name) => name.startsWith('sw-dashboard-metrics__card--')))).toEqual([
            'sw-dashboard-metrics__card--revenue',
            'sw-dashboard-metrics__card--refunds',
            'sw-dashboard-metrics__card--walletTopUps',
            'sw-dashboard-metrics__card--memberships',
        ]);
        expect(wrapper.find('.sw-dashboard-metrics__card--orders').exists()).toBe(false);
        expect(wrapper.find('.sw-dashboard-metrics__card--customers').exists()).toBe(false);
        const revenue = wrapper.get('.sw-dashboard-metrics__card--revenue');
        expect(revenue.get('.sw-dashboard-metrics__footnote').text()).toContain('sw-dashboard.metrics.salesCount4');
        expect(revenue.text()).not.toContain('buyersCount');
        const wallet = wrapper.get('.sw-dashboard-metrics__card--walletTopUps');
        expect(wallet.get('.sw-dashboard-metrics__value').text()).toContain('80');
        expect(wallet.get('.sw-dashboard-metrics__footnote').text()).toContain('sw-dashboard.metrics.topUpCount2');
        expect(wrapper.get('.sw-dashboard-metrics__card--refunds .sw-dashboard-metrics__footnote').text()).toContain('2');
        expect(revenue.get('.sw-dashboard-metrics__delta').text()).toContain('100.0%');
        expect(wrapper.findAll('.sw-dashboard-metrics__delta')).toHaveLength(4);
        expect(wrapper.get('.sw-dashboard-metrics__card--refunds .sw-dashboard-metrics__delta').classes()).toContain(
            'is-critical',
        );
    });

    it('shows activated memberships and preserves unavailable membership data', async () => {
        const wrapper = await createWrapper();
        let card = wrapper.get('.sw-dashboard-metrics__card--memberships');
        expect(card.get('.sw-dashboard-metrics__value').text()).toContain('5');
        expect(card.get('.sw-dashboard-metrics__delta').text()).toContain('150.0%');
        await wrapper.setProps({ summary: { ...summary, today: { ...summary.today, memberships: null } } });
        card = wrapper.get('.sw-dashboard-metrics__card--memberships');
        expect(card.get('.sw-dashboard-metrics__value').text()).toContain('—');
    });

    it('does not invent a percentage when yesterday was zero', async () => {
        const wrapper = await createWrapper({ summary: { ...summary, previous: { revenue: 0, orders: 0, customers: 0 } } });
        expect(wrapper.get('.sw-dashboard-metrics__card--revenue .sw-dashboard-metrics__delta').text()).toContain('200');
        expect(wrapper.get('.sw-dashboard-metrics__card--revenue .sw-dashboard-metrics__delta').text()).not.toContain('%');
    });

    it('shows zero for unchanged values when there is activity', async () => {
        const wrapper = await createWrapper({
            summary: {
                ...summary,
                today: { ...summary.today, walletTopUpAmount: 0 },
                previous: { ...summary.previous, walletTopUpAmount: 0 },
            },
        });
        expect(wrapper.get('.sw-dashboard-metrics__card--walletTopUps .sw-dashboard-metrics__delta').text()).toBe('0');
    });

    it('hides counts and comparisons when each metric has no activity', async () => {
        const empty = Object.fromEntries(Object.keys(summary.today).map((key) => [key, 0]));
        const wrapper = await createWrapper({ summary: { today: empty, previous: empty } });

        expect(wrapper.findAll('.sw-dashboard-metrics__card')).toHaveLength(4);
        expect(wrapper.findAll('.sw-dashboard-metrics__value').every((value) => value.text().includes('0'))).toBe(true);
        expect(wrapper.find('.sw-dashboard-metrics__detail').exists()).toBe(false);
        expect(wrapper.find('.sw-dashboard-metrics__comparison').exists()).toBe(false);
    });

    it('keeps comparisons for zero today when there was activity yesterday', async () => {
        const empty = Object.fromEntries(Object.keys(summary.today).map((key) => [key, 0]));
        const wrapper = await createWrapper({ summary: { today: empty, previous: summary.previous } });

        expect(wrapper.find('.sw-dashboard-metrics__detail').exists()).toBe(false);
        expect(wrapper.findAll('.sw-dashboard-metrics__delta')).toHaveLength(4);
        expect(wrapper.findAll('.sw-dashboard-metrics__delta').every((delta) => delta.text() === '↓ 100.0%')).toBe(true);
    });

    it('shows errors and loading as unavailable instead of zero', async () => {
        const wrapper = await createWrapper({ summary: null, salesError: true });
        expect(wrapper.findAll('.sw-dashboard-metrics__value').every((value) => value.text().startsWith('—'))).toBe(true);
        expect(wrapper.find('.sw-dashboard-metrics__delta').exists()).toBe(false);
        await wrapper.setProps({ summary, salesError: false, salesLoading: true });
        expect(wrapper.findAll('.sw-dashboard-metrics__value').every((value) => value.text().startsWith('—'))).toBe(true);
    });

    it('hides wallet metrics without wallet access', async () => {
        const wrapper = await createWrapper({}, ['order.viewer', 'loyalty.viewer']);
        expect(wrapper.findAll('.sw-dashboard-metrics__card')).toHaveLength(3);
        expect(wrapper.find('.sw-dashboard-metrics__card--walletTopUps').exists()).toBe(false);
    });

    it('preserves unavailable wallet data', async () => {
        const wrapper = await createWrapper({
            summary: { ...summary, today: { ...summary.today, walletTopUpAmount: null, walletTopUpCount: null } },
        });
        const wallet = wrapper.get('.sw-dashboard-metrics__card--walletTopUps');
        expect(wallet.get('.sw-dashboard-metrics__value').text()).toContain('—');
        expect(wallet.find('.sw-dashboard-metrics__detail').exists()).toBe(false);
        expect(wallet.find('.sw-dashboard-metrics__delta').exists()).toBe(false);
    });

    it('hides business metrics without order access', async () => {
        const wrapper = await createWrapper({}, false);
        expect(wrapper.findAll('.sw-dashboard-metrics__card')).toHaveLength(0);
    });
});
