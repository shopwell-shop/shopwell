/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import type { PaymentActivity } from '../../payment.types';

let activeWrapper: VueWrapper | null = null;

const activities: PaymentActivity[] = [
    {
        id: 'transaction-1',
        kind: 'transaction',
        at: '2026-01-02 12:00',
        title: '交易 TX-1',
        detail: 'TX-1 · 渠道单号-1',
        statusLabel: '成功',
        statusVariant: 'positive',
        link: null,
    },
    {
        id: 'notification-1',
        kind: 'notification',
        at: '2026-01-02 12:01',
        title: '异步通知',
        detail: '10.0.0.1',
        statusLabel: '已处理',
        statusVariant: 'positive',
        link: null,
    },
    {
        id: 'refund-1',
        kind: 'refund',
        at: '2026-01-04 08:00',
        title: '退款 R-1',
        detail: '¥1.00',
        statusLabel: '退款中',
        statusVariant: 'attention',
        link: { name: 'sw.payment.refundDetail', params: { id: 'refund-1' } },
    },
];

async function createWrapper(): Promise<VueWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-payment-order-activity', { sync: true }), {
        props: {
            activities,
        },
        global: {
            stubs: {
                'mt-empty-state': true,
                'mt-icon': {
                    props: ['name'],
                    template: '<i :class="`icon-${name}`"></i>',
                },
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="badge" :data-variant="variant"><slot></slot></span>',
                },
                'router-link': {
                    props: ['to'],
                    template: '<a class="router-link" :data-to="JSON.stringify(to)"><slot></slot></a>',
                },
            },
        },
    });

    activeWrapper = wrapper;

    await flushPromises();

    return wrapper;
}

/**
 * The entries as the reader sees them, top to bottom.
 */
function entries(wrapper: VueWrapper): string[] {
    return wrapper.findAll('.sw-payment-order-activity__title').map((title) => title.text());
}

function filterLabels(wrapper: VueWrapper): string[] {
    return wrapper.findAll('.sw-payment-order-activity__filter').map((filter) => filter.text().replace(/\s+/g, ' ').trim());
}

describe('module/sw-payment/component/sw-payment-order-activity', () => {
    afterEach(() => {
        activeWrapper?.unmount();
        activeWrapper = null;
    });

    it('should list every entry in the order the page sorted them', async () => {
        const wrapper = await createWrapper();

        expect(entries(wrapper)).toEqual(['交易 TX-1', '异步通知', '退款 R-1']);
    });

    it('should render the detail, the status and the moment of an entry', async () => {
        const wrapper = await createWrapper();

        const first = wrapper.findAll('.sw-payment-order-activity__entry')[0];

        expect(first.find('.sw-payment-order-activity__detail').text()).toBe('TX-1 · 渠道单号-1');
        expect(first.find('.badge').text()).toBe('成功');
        expect(first.find('.badge').attributes('data-variant')).toBe('positive');
        expect(first.find('.sw-payment-order-activity__meta').text()).toContain('2026-01-02 12:00');
    });

    /**
     * A payment that never produced a webhook must not offer a filter that always comes up empty.
     */
    it('should only count the kinds that occurred', async () => {
        const wrapper = await createWrapper();

        expect(filterLabels(wrapper)).toEqual([
            'sw-payment.order.activity.filterAll 3',
            'sw-payment.order.activity.kind.transaction 1',
            'sw-payment.order.activity.kind.notification 1',
            'sw-payment.order.activity.kind.refund 1',
        ]);
    });

    it('should narrow the list to one kind and come back to all of them', async () => {
        const wrapper = await createWrapper();

        const filters = wrapper.findAll('.sw-payment-order-activity__filter');

        await filters[3].trigger('click');
        expect(entries(wrapper)).toEqual(['退款 R-1']);

        await filters[0].trigger('click');
        expect(entries(wrapper)).toHaveLength(3);
    });

    it('should mark the filter the reader picked', async () => {
        const wrapper = await createWrapper();

        await wrapper.findAll('.sw-payment-order-activity__filter')[2].trigger('click');

        expect(wrapper.findAll('.sw-payment-order-activity__filter')[2].classes()).toContain('is--active');
        expect(wrapper.findAll('.sw-payment-order-activity__filter')[0].classes()).not.toContain('is--active');
    });

    it('should point every kind at its own icon', async () => {
        const wrapper = await createWrapper();

        const icons = wrapper.findAll('.sw-payment-order-activity__icon');

        expect(icons[0].classes()).toContain('is--transaction');
        expect(icons[0].find('.icon-regular-credit-card').exists()).toBe(true);
        expect(icons[1].find('.icon-regular-bell').exists()).toBe(true);
        expect(icons[2].find('.icon-regular-reply').exists()).toBe(true);
    });

    /**
     * Only a refund has a page of its own; the rest of the timeline has nothing to open.
     */
    it('should link the entries that have a record behind them', async () => {
        const wrapper = await createWrapper();

        const links = wrapper.findAll('.sw-payment-order-activity__link');

        expect(links).toHaveLength(1);
        expect(links[0].attributes('data-to')).toBe('{"name":"sw.payment.refundDetail","params":{"id":"refund-1"}}');
        expect(links[0].text()).toBe('sw-payment.order.activity.linkRecord');
    });

    it('should say so when the payment has no records at all', async () => {
        const wrapper = mount(await wrapTestComponent('sw-payment-order-activity', { sync: true }), {
            props: {
                activities: [],
            },
            global: {
                stubs: {
                    'mt-empty-state': { template: '<div class="empty-state"></div>' },
                    'mt-icon': true,
                    'mt-badge': true,
                    'router-link': true,
                },
            },
        });

        activeWrapper = wrapper;

        await flushPromises();

        expect(wrapper.find('.empty-state').exists()).toBe(true);
        expect(wrapper.find('.sw-payment-order-activity__list').exists()).toBe(false);
        expect(filterLabels(wrapper)).toEqual(['sw-payment.order.activity.filterAll 0']);
    });
});
