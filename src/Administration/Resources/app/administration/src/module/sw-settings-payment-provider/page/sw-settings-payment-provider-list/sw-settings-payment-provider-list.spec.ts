/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import type { PaymentAccountView } from 'src/module/sw-payment/service/payment-gateway.api.service';

const wrappers: ReturnType<typeof mount>[] = [];
const account: PaymentAccountView = {
    id: 'account-1',
    name: 'Merchant',
    providerTechnicalName: 'alipay',
    active: true,
    enabledProducts: ['alipay_h5'],
    ownerAppId: null,
    isDefault: false,
    credentials: {},
    productParams: {},
    settings: {},
};
const initialAccounts = [
    account,
    { ...account, id: 'default-account', name: 'alipay platform', active: false, isDefault: true },
];
const gridStub = {
    props: [
        'dataSource',
        'columns',
        'fullPage',
        'showSelection',
    ],
    emits: ['column-sort'],
    template: `<div class="grid"><button class="sort-name" @click="$emit('column-sort', {dataIndex: 'name'})">Sort</button><div v-for="item in dataSource" :key="item.id" class="grid-row" :data-id="item.id"><div v-for="column in columns" :key="column.property"><slot :name="'column-' + column.property" :item="item"></slot></div><slot name="actions" :item="item"></slot></div><slot name="pagination"></slot></div>`,
};
async function createWrapper({ editable = true, fails = false, accounts = initialAccounts } = {}) {
    const service = {
        listAccounts: jest.fn(() => Promise.resolve(structuredClone(accounts))),
        listAccountDefinitions: jest.fn(() => Promise.resolve([{ technicalName: 'alipay', label: '支付宝' }])),
        deleteAccount: jest.fn(() => (fails ? Promise.reject(new Error('in use')) : Promise.resolve())),
    };
    const push = jest.fn();
    const wrapper = mount(await wrapTestComponent('sw-settings-payment-provider-list', { sync: true }), {
        global: {
            provide: { paymentGatewayService: service, acl: { can: () => editable } },
            mocks: { $te: () => false, $router: { push } },
            stubs: {
                'sw-page': {
                    template:
                        '<div><slot name="search-bar"></slot><slot name="smart-bar-header"></slot><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
                },
                'mt-search': {
                    props: ['modelValue'],
                    emits: ['update:modelValue'],
                    template: `<input class="search" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)">`,
                },
                'sw-data-grid': gridStub,
                'sw-pagination': {
                    props: ['page', 'limit', 'total'],
                    emits: ['page-change'],
                    template: `<div class="pagination" :data-page="page" :data-total="total"><button class="next-page" @click="$emit('page-change', {page: page + 1, limit})">Next</button></div>`,
                },
                'sw-context-menu-item': {
                    props: ['routerLink', 'disabled'],
                    template: '<button :disabled="disabled" :data-id="routerLink?.params.id"><slot></slot></button>',
                },
                'router-link': {
                    props: ['to'],
                    template: '<a :data-route="to.name" :data-id="to.params.id"><slot></slot></a>',
                },
                'mt-button': { template: '<button><slot></slot></button>' },
                'mt-badge': { props: ['variant'], template: '<span :data-variant="variant"><slot></slot></span>' },
                'mt-banner': { template: '<aside><slot></slot></aside>' },
                'mt-empty-state': {
                    props: ['headline'],
                    template: '<div class="empty">{{ headline }}<slot name="button"></slot></div>',
                },
                'mt-modal-root': { template: '<div class="delete-modal"><slot></slot></div>' },
                'mt-modal': { template: '<div><slot></slot><slot name="footer"></slot></div>' },
                'mt-modal-close': { template: '<button class="cancel-delete"><slot></slot></button>' },
                'mt-modal-action': { template: '<button><slot></slot></button>' },
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();
    return { wrapper, service, push };
}
const rows = (wrapper: ReturnType<typeof mount>) => wrapper.findAll('.grid-row');
const confirmDelete = async (wrapper: ReturnType<typeof mount>) => {
    await wrapper.find('.sw-payment-account-list__confirm-delete').trigger('click');
    await flushPromises();
};
describe('payment account list', () => {
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));
    it('uses the standard full-page grid and links account names to their details', async () => {
        const { wrapper, service, push } = await createWrapper();
        expect(service.listAccounts).toHaveBeenCalledTimes(1);
        expect(wrapper.findComponent(gridStub).props()).toEqual(
            expect.objectContaining({ fullPage: true, showSelection: false }),
        );
        expect(rows(wrapper)).toHaveLength(2);
        expect(wrapper.find('[data-id="account-1"] a').text()).toBe('Merchant');
        expect(wrapper.find('[data-id="default-account"] a').text()).toBe('alipay');
        expect(wrapper.text()).not.toContain('alipay platform');
        await wrapper.find('.sw-payment-account-list__create').trigger('click');
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.provider.create' });
    });
    it('shows status badges and row context actions instead of permanent delete buttons', async () => {
        const { wrapper } = await createWrapper();
        const custom = wrapper.find('.grid-row[data-id="account-1"]');
        const seeded = wrapper.find('.grid-row[data-id="default-account"]');
        expect(custom.find('.sw-payment-account-list__status').attributes('data-variant')).toBe('positive');
        expect(seeded.find('.sw-payment-account-list__status').attributes('data-variant')).toBe('neutral');
        expect(custom.find('.sw-payment-account-list__edit').attributes('data-id')).toBe('account-1');
        expect(seeded.find('.sw-payment-account-list__delete').exists()).toBe(false);
    });
    it('searches account names and provider names through the list search', async () => {
        const { wrapper } = await createWrapper();
        await wrapper.find('.search').setValue('merchant');
        expect(rows(wrapper)).toHaveLength(1);
        await wrapper.find('.search').setValue('支付宝');
        expect(rows(wrapper)).toHaveLength(2);
        await wrapper.find('.search').setValue('absent');
        expect(wrapper.find('.empty').text()).toBe('sw-empty-state.messageNoResultTitle');
    });
    it('sorts account names through the grid header', async () => {
        const { wrapper } = await createWrapper();
        expect(rows(wrapper)[0].attributes('data-id')).toBe('default-account');
        await wrapper.find('.sort-name').trigger('click');
        expect(rows(wrapper)[0].attributes('data-id')).toBe('account-1');
    });
    it('paginates account results and resets the page when searching', async () => {
        const accounts = Array.from({ length: 26 }, (_, index) => ({
            ...account,
            id: `account-${index}`,
            name: `Account ${index}`,
        }));
        const { wrapper } = await createWrapper({ accounts });
        expect(rows(wrapper)).toHaveLength(25);
        await wrapper.find('.next-page').trigger('click');
        expect(rows(wrapper)).toHaveLength(1);
        await wrapper.find('.search').setValue('Account 0');
        expect(wrapper.find('.pagination').attributes('data-page')).toBe('1');
        expect(rows(wrapper)[0].attributes('data-id')).toBe('account-0');
    });
    it('requests confirmation before deleting an unused account', async () => {
        const { wrapper, service } = await createWrapper();
        await wrapper.find('.sw-payment-account-list__delete').trigger('click');
        expect(service.deleteAccount).not.toHaveBeenCalled();
        await confirmDelete(wrapper);
        expect(service.deleteAccount).toHaveBeenCalledWith('account-1');
        expect(rows(wrapper)).toHaveLength(1);
        expect(wrapper.find('.delete-modal').exists()).toBe(false);
    });
    it('does not delete when the confirmation is cancelled', async () => {
        const { wrapper, service } = await createWrapper();
        await wrapper.find('.sw-payment-account-list__delete').trigger('click');
        await wrapper.find('.cancel-delete').trigger('click');
        expect(service.deleteAccount).not.toHaveBeenCalled();
        expect(wrapper.find('.delete-modal').exists()).toBe(false);
    });
    it('keeps used accounts visible and explains they should be disabled after rejected deletion', async () => {
        const { wrapper } = await createWrapper({ fails: true });
        await wrapper.find('.sw-payment-account-list__delete').trigger('click');
        await confirmDelete(wrapper);
        expect(rows(wrapper)).toHaveLength(2);
        expect(wrapper.find('aside').text()).toBe('sw-settings-payment-provider.accounts.deleteError');
    });
    it('respects create, edit and delete privileges', async () => {
        const { wrapper } = await createWrapper({ editable: false });
        expect(wrapper.find('.sw-payment-account-list__create').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-payment-account-list__edit').attributes('disabled')).toBeDefined();
        expect(wrapper.find('.sw-payment-account-list__delete').attributes('disabled')).toBeDefined();
    });
    it('shows an empty payment account list', async () => {
        const { wrapper } = await createWrapper({ accounts: [] });
        expect(wrapper.find('.empty').text()).toContain('sw-settings-payment-provider.accounts.empty');
    });
});
