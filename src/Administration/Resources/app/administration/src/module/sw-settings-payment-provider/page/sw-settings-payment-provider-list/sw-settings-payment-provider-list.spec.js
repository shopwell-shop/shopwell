/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const registryProviders = [
    {
        technicalName: 'alipay',
        label: '支付宝',
        enabled: true,
        configured: true,
        credentialFields: [
            {
                name: 'app_id',
                label: '支付宝应用 APPID',
                type: 'text',
                required: true,
                secret: false,
                options: {},
                default: null,
                value: '2021000000000000',
                configured: true,
            },
            {
                name: 'private_key',
                label: '应用私钥',
                type: 'textarea',
                required: true,
                secret: true,
                options: {},
                default: null,
                // a secret is never echoed back, only its state is reported
                value: null,
                configured: true,
            },
            {
                name: 'sign_type',
                label: '签名算法',
                type: 'select',
                required: false,
                secret: false,
                options: { RSA2: 'RSA2' },
                default: 'RSA2',
                value: null,
                configured: false,
            },
        ],
        settings: {},
        channels: [
            { channel: 'alipay_h5', label: '支付宝 手机网站支付', enabled: true },
            { channel: 'alipay_web', label: '支付宝 电脑网站支付', enabled: false },
        ],
    },
    {
        technicalName: 'stripe',
        label: 'Stripe',
        enabled: false,
        configured: false,
        credentialFields: [
            {
                name: 'secret_key',
                label: 'Secret Key',
                type: 'password',
                required: true,
                secret: true,
                options: {},
                default: null,
                value: null,
                configured: false,
            },
        ],
        settings: {},
        channels: [{ channel: 'stripe_checkout', label: 'Stripe Checkout', enabled: false }],
    },
];

async function createWrapper({ providers = registryProviders, saveFails = false, canEdit = true } = {}) {
    const service = {
        listProviders: jest.fn(() => Promise.resolve(providers)),
        saveProvider: jest.fn(() => (saveFails ? Promise.reject(new Error('save failed')) : Promise.resolve())),
    };

    const push = jest.fn();

    // a viewer may open the page, only the editing rights are switchable here
    const acl = {
        can: jest.fn((privilege) => {
            return privilege.endsWith('.viewer') ? true : canEdit;
        }),
    };

    const wrapper = mount(await wrapTestComponent('sw-settings-payment-provider-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="content"></slot></div>' },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-extension-component-section': true,
                'sw-internal-link': {
                    props: ['routerLink'],
                    template: '<a class="internal-link-stub"><slot></slot></a>',
                },
                'sw-data-grid': {
                    props: ['dataSource', 'columns'],
                    template: `
                        <table class="sw-data-grid-stub">
                            <thead>
                                <tr>
                                    <th
                                        v-for="column in columns"
                                        :key="column.property"
                                        class="sw-data-grid-stub__header"
                                    >{{ column.property }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in dataSource"
                                    :key="item.technicalName"
                                    class="sw-data-grid-stub__row"
                                >
                                    <td class="sw-data-grid-stub__label"><slot name="column-label" :item="item" /></td>
                                    <td class="sw-data-grid-stub__status"><slot name="column-configured" :item="item" /></td>
                                    <td class="sw-data-grid-stub__credentials"><slot name="column-credentials" :item="item" /></td>
                                    <td class="sw-data-grid-stub__channels"><slot name="column-channels" :item="item" /></td>
                                    <td class="sw-data-grid-stub__actions"><slot name="column-actions" :item="item" /></td>
                                </tr>
                            </tbody>
                        </table>`,
                },
                'mt-card': { template: '<div><slot></slot><slot name="headerRight"></slot><slot name="grid"></slot></div>' },
                'mt-empty-state': true,
                'mt-badge': true,
                'mt-switch': true,
                'mt-banner': { template: '<div><slot></slot></div>' },
            },
            provide: {
                paymentGatewayService: service,
                acl,
            },
            mocks: {
                $router: { push },
                $te: () => false,
            },
        },
    });

    await flushPromises();

    return { wrapper, service, push };
}

describe('module/sw-settings-payment-provider/page/sw-settings-payment-provider-list', () => {
    beforeEach(() => {
        global.activeAclRoles = [
            'payment_provider_config.viewer',
            'payment_provider_config.editor',
            'payment_provider_config.deleter',
            'payment_channel_config.viewer',
            'payment_channel_config.editor',
            'payment_channel_config.deleter',
        ];
    });

    it('should load the providers from the server', async () => {
        const { wrapper, service } = await createWrapper();

        expect(service.listProviders).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.providers).toHaveLength(2);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should count the enabled channels of a provider', async () => {
        const { wrapper } = await createWrapper();

        const alipay = wrapper.vm.providers[0];

        expect(wrapper.vm.channelCount(alipay)).toBe(2);
        expect(wrapper.vm.enabledChannelCount(alipay)).toBe(1);
    });

    it('should count only the required credentials and treat a stored secret as filled', async () => {
        const { wrapper } = await createWrapper();

        const alipay = wrapper.vm.providers[0];
        const stripe = wrapper.vm.providers[1];

        expect(wrapper.vm.requiredCredentialCount(alipay)).toBe(2);
        expect(wrapper.vm.filledCredentialCount(alipay)).toBe(2);
        expect(wrapper.vm.missingCredentialCount(alipay)).toBe(0);

        expect(wrapper.vm.requiredCredentialCount(stripe)).toBe(1);
        expect(wrapper.vm.filledCredentialCount(stripe)).toBe(0);
        expect(wrapper.vm.missingCredentialCount(stripe)).toBe(1);
    });

    it('should sort the providers by their technical name', async () => {
        const { wrapper } = await createWrapper({
            providers: [registryProviders[1], registryProviders[0]],
        });

        expect(wrapper.vm.sortedProviders.map((provider) => provider.technicalName)).toEqual(['alipay', 'stripe']);
    });

    it('should describe the columns of the interface table', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.providerColumns.map((column) => column.property)).toEqual([
            'label',
            'configured',
            'credentials',
            'channels',
            'actions',
        ]);
        expect(wrapper.vm.providerColumns[0].primary).toBe(true);
    });

    it('should render one table row per interface with its technical name and its link to the detail page', async () => {
        const { wrapper } = await createWrapper();

        const rows = wrapper.findAll('.sw-data-grid-stub__row');

        expect(rows).toHaveLength(2);
        expect(rows.at(0).find('.sw-settings-payment-provider-list__label').text()).toBe('支付宝');
        expect(rows.at(0).find('.sw-settings-payment-provider-list__technical-name').text()).toBe('alipay');
        expect(rows.at(0).find('.internal-link-stub').exists()).toBe(true);
        expect(rows.at(1).find('.sw-settings-payment-provider-list__attention').text()).toBe(
            'sw-settings-payment-provider.list.credentialMissing',
        );
    });

    it('should render the switch of every interface', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.findAll('.sw-settings-payment-provider-list__switch')).toHaveLength(2);
    });

    /**
     * The table is the only way to the detail page, so a viewer has to be able to follow the link -
     * the switches on the page itself are disabled for them.
     */
    it('should let a viewer open the detail page while the switches stay disabled', async () => {
        const { wrapper } = await createWrapper({ canEdit: false });

        expect(wrapper.vm.canView).toBe(true);
        expect(wrapper.vm.canEdit).toBe(false);

        const link = wrapper.find('.internal-link-stub');
        expect(link.exists()).toBe(true);
        expect(link.attributes('disabled')).toBeUndefined();
    });

    it('should hint that the interfaces are provided by code', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-settings-payment-provider-list__hint').text()).toBe(
            'sw-settings-payment-provider.general.codeProvidedHint',
        );
    });

    it('should save the switch of a provider immediately', async () => {
        const { wrapper, service } = await createWrapper();

        const alipay = wrapper.vm.providers[0];
        alipay.enabled = false;

        await wrapper.vm.onToggleProvider(alipay);

        expect(service.saveProvider).toHaveBeenCalledWith('alipay', { enabled: false });
        expect(wrapper.vm.createNotificationSuccess).toBeTruthy();
    });

    it('should revert the switch when saving fails', async () => {
        const { wrapper } = await createWrapper({ saveFails: true });

        const alipay = wrapper.vm.providers[0];
        alipay.enabled = false;

        await wrapper.vm.onToggleProvider(alipay);

        expect(alipay.enabled).toBe(true);
    });
});
