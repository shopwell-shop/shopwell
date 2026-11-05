/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
const wrappers: ReturnType<typeof mount>[] = [];
const providers = [
    {
        technicalName: 'alipay',
        label: '支付宝',
        credentialFields: [
            { name: 'app_id', label: 'APPID', type: 'text' },
            { name: 'private_key', label: '私钥', type: 'textarea', secret: true },
            { name: 'public_key', label: '公钥', type: 'textarea' },
            { name: 'sandbox', label: 'Sandbox', type: 'bool', default: '1' },
        ],
        products: [
            {
                product: 'alipay_h5',
                label: '手机网站支付',
                paramFields: [
                    { name: 'app_id', type: 'text', label: '产品 APPID' },
                    { name: 'mode', type: 'text', default: 'live' },
                ],
            },
        ],
    },
];
const account = {
    id: 'account-1',
    name: 'Merchant',
    providerTechnicalName: 'alipay',
    active: true,
    enabledProducts: ['alipay_h5'],
    ownerAppId: null,
    isDefault: false,
    credentials: { app_id: 'merchant-app', private_key: null, public_key: 'public-key' },
    productParams: { alipay_h5: { app_id: 'product-app' } },
    settings: {},
};
const stubs = {
    'sw-page': {
        template:
            '<div><slot name="smart-bar-header"></slot><slot name="smart-bar-actions"></slot><slot name="content"></slot></div>',
    },
    'sw-card-view': { template: '<div><slot></slot></div>' },
    'mt-card': { props: ['title'], template: '<section><h3>{{ title }}</h3><slot></slot></section>' },
    'mt-button': { template: '<button><slot></slot></button>' },
    'mt-banner': { template: '<aside><slot></slot></aside>' },
    'sw-skeleton': true,
    'router-link': { props: ['to'], template: '<a :data-route="to.name" :data-id="to.params?.id"><slot></slot></a>' },
    'mt-text-field': {
        props: ['modelValue', 'disabled'],
        emits: ['update:modelValue'],
        template: `<input :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)">`,
    },
    'mt-select': {
        props: ['modelValue', 'disabled', 'options'],
        emits: ['update:modelValue'],
        template: `<select :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)"><option value=""></option><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>`,
    },
    'mt-switch': {
        props: ['modelValue', 'disabled'],
        emits: ['update:modelValue'],
        template: `<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.checked)">`,
    },
    'sw-payment-config-field': {
        props: ['field', 'modelValue', 'disabled'],
        emits: ['update:modelValue'],
        template: `<input :data-field="field.name" :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)">`,
    },
};
async function createWrapper({
    accountId = 'account-1' as string | null,
    editable = true,
    fails = false,
    isDefault = false,
    name = '',
} = {}) {
    const service = {
        listAccountDefinitions: jest.fn(() => Promise.resolve(structuredClone(providers))),
        getAccount: jest.fn(() =>
            Promise.resolve(
                structuredClone({ ...account, isDefault, name: name || (isDefault ? 'alipay platform' : account.name) }),
            ),
        ),
        saveAccount: jest.fn((_id: string | null, payload: Record<string, unknown>) =>
            fails ? Promise.reject(new Error('save failed')) : Promise.resolve({ ...account, ...payload }),
        ),
    };
    const replace = jest.fn();
    const wrapper = mount(await wrapTestComponent('sw-settings-payment-provider-detail', { sync: true }), {
        props: { accountId },
        global: {
            stubs,
            provide: { paymentGatewayService: service, acl: { can: () => editable } },
            mocks: {
                $te: () => false,
                $router: { replace },
                $t: (key: string, params?: { provider: string }) => (params ? `${params.provider}默认支付账户` : key),
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();
    return { wrapper, service, replace };
}
const save = async (wrapper: ReturnType<typeof mount>) => {
    await wrapper.find('.sw-payment-account-detail__save').trigger('click');
    await flushPromises();
};
describe('payment account editor', () => {
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));
    it('loads an account independently of app grants and shows key textareas together', async () => {
        const { wrapper, service } = await createWrapper();
        expect(service.getAccount).toHaveBeenCalledWith('account-1');
        expect(wrapper.find('[data-field="private_key"]').element).toHaveProperty('value', '');
        expect(wrapper.find('.sw-payment-account-detail__keys').findAll('[data-field]')).toHaveLength(2);
        expect(wrapper.find('.sw-payment-account-detail__provider').attributes('disabled')).toBeDefined();
        expect(wrapper.find('details').exists()).toBe(false);
    });
    it('shows the provider identifier for a generated account name without changing stored values', async () => {
        const { wrapper, service } = await createWrapper({ isDefault: true });
        expect(wrapper.find('.sw-payment-account-detail__name').element).toHaveProperty('value', 'alipay');
        expect(wrapper.html()).not.toContain('alipay platform');
        await save(wrapper);
        expect(service.saveAccount).toHaveBeenCalledWith('account-1', expect.objectContaining({ name: 'alipay platform' }));
    });
    it('preserves and edits a custom default account name', async () => {
        const { wrapper, service } = await createWrapper({ isDefault: true, name: '旗舰店' });
        expect(wrapper.find('.sw-payment-account-detail__name').element).toHaveProperty('value', '旗舰店');
        expect(wrapper.find('.sw-payment-account-detail__name').attributes('disabled')).toBeUndefined();
        await wrapper.find('.sw-payment-account-detail__name').setValue('旗舰店二号');
        await save(wrapper);
        expect(service.saveAccount).toHaveBeenCalledWith('account-1', expect.objectContaining({ name: '旗舰店二号' }));
    });
    it('creates an account with explicit product activation and merchant configuration', async () => {
        const { wrapper, service, replace } = await createWrapper({ accountId: null });
        expect(service.getAccount).not.toHaveBeenCalled();
        await wrapper.find('.sw-payment-account-detail__name').setValue(' Branch account ');
        await wrapper.find('.sw-payment-account-detail__provider').setValue('alipay');
        await wrapper.find('[data-field="app_id"]').setValue('branch-app');
        await wrapper.find('.sw-payment-account-detail__active').setValue(true);
        const toggles = wrapper.findAll('input[type="checkbox"]');
        await toggles[1].setValue(true);
        await save(wrapper);
        expect(service.saveAccount).toHaveBeenCalledWith(
            null,
            expect.objectContaining({
                name: 'Branch account',
                providerTechnicalName: 'alipay',
                active: true,
                enabledProducts: ['alipay_h5'],
                credentials: { app_id: 'branch-app', private_key: '', public_key: '', sandbox: '1' },
            }),
        );
        expect(replace).toHaveBeenCalledWith({ name: 'sw.settings.payment.provider.detail', params: { id: 'account-1' } });
    });
    it('applies declared defaults only when creating an account', async () => {
        const created = await createWrapper({ accountId: null });
        await created.wrapper.find('.sw-payment-account-detail__provider').setValue('alipay');
        expect(created.wrapper.find('[data-field="sandbox"]').element).toHaveProperty('value', '1');
        expect(created.wrapper.find('[data-field="mode"]').element).toHaveProperty('value', 'live');
        const existing = await createWrapper();
        expect(existing.wrapper.find('[data-field="sandbox"]').element).toHaveProperty('value', '');
        expect(existing.wrapper.find('[data-field="mode"]').element).toHaveProperty('value', '');
    });
    it('updates account configuration while leaving stored secret fields blank', async () => {
        const { wrapper, service } = await createWrapper();
        const fields = wrapper.findAll('[data-field="app_id"]');
        await fields[1].setValue('new-product-app');
        await wrapper.findAll('input[type="checkbox"]')[1].setValue(false);
        await save(wrapper);
        expect(service.saveAccount).toHaveBeenCalledWith(
            'account-1',
            expect.objectContaining({
                enabledProducts: [],
                credentials: { app_id: 'merchant-app', private_key: '', public_key: 'public-key', sandbox: '' },
                productParams: { alipay_h5: { app_id: 'new-product-app', mode: '' } },
            }),
        );
    });
    it('retains edits after a failed save', async () => {
        const { wrapper, service } = await createWrapper({ fails: true });
        await wrapper.find('.sw-payment-account-detail__name').setValue('Retry branch');
        await save(wrapper);
        expect(service.saveAccount).toHaveBeenCalledTimes(1);
        expect(wrapper.find('.sw-payment-account-detail__name').element).toHaveProperty('value', 'Retry branch');
        expect(wrapper.find('aside').text()).toBe('sw-settings-payment-provider.accounts.saveError');
    });
    it('allows viewers to read while disabling changes', async () => {
        const { wrapper } = await createWrapper({ editable: false });
        expect(wrapper.find('.sw-payment-account-detail__save').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-field="app_id"]').attributes('disabled')).toBeDefined();
    });
});
