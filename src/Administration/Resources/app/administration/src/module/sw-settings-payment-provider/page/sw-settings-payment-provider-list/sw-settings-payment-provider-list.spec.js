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

async function createWrapper({
    providers = registryProviders,
    canView = true,
    canEdit = true,
    saveFails = false,
    pending = false,
} = {}) {
    const service = {
        // the page mutates what it loads (the switch writes the state back), so every test gets its own copy
        listProviders: jest.fn(() => {
            if (pending) {
                return new Promise(() => {});
            }

            return Promise.resolve(JSON.parse(JSON.stringify(providers)));
        }),
        saveProvider: jest.fn(() => (saveFails ? Promise.reject(new Error('save failed')) : Promise.resolve())),
    };

    const acl = {
        can: jest.fn((privilege) => {
            if (privilege === 'payment_provider_config.viewer') {
                return canView;
            }

            if (privilege === 'payment_provider_config.editor') {
                return canEdit;
            }

            return false;
        }),
    };

    const wrapper = mount(await wrapTestComponent('sw-settings-payment-provider-list', { sync: true }), {
        global: {
            stubs: {
                'sw-page': { template: '<div><slot name="smart-bar-header"></slot><slot name="content"></slot></div>' },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                /**
                 * The double of the container keeps the grid it was asked for, so the test can see how
                 * the link and the switch are lined up on the card.
                 */
                'sw-container': {
                    props: [
                        'columns',
                        'gap',
                        'justify',
                        'align',
                    ],
                    template: `
                        <div
                            class="sw-container-stub"
                            :data-columns="columns"
                            :data-justify="justify"
                        ><slot></slot></div>`,
                },
                'sw-skeleton': true,
                'sw-extension-component-section': true,
                'mt-icon': true,
                'mt-empty-state': { template: '<div class="mt-empty-state-stub"></div>' },
                /**
                 * The double of the card renders the title and the header slot, so a card that never
                 * received a name is visible in the test.
                 */
                'mt-card': {
                    props: ['title'],
                    template: `
                        <div class="mt-card-stub" :data-title="title">
                            <slot name="headerRight"></slot>
                            <slot></slot>
                        </div>`,
                },
                'mt-switch': {
                    props: ['modelValue', 'label', 'disabled'],
                    template: `
                        <button
                            class="mt-switch-stub"
                            :data-label="label"
                            :data-enabled="String(modelValue)"
                            :disabled="disabled"
                            @click="$emit('update:modelValue', !modelValue)"
                        ></button>`,
                },
                'sw-internal-link': {
                    props: ['routerLink', 'disabled'],
                    template: `
                        <a
                            class="sw-internal-link-stub"
                            :data-route="routerLink && routerLink.name"
                            :data-id="routerLink && routerLink.params && routerLink.params.id"
                            :data-disabled="String(Boolean(disabled))"
                        ><slot></slot></a>`,
                },
                /**
                 * The confirmation is a dialog of its own, the double reports the text it was given and
                 * offers both answers.
                 */
                'sw-confirm-modal': {
                    props: ['title', 'text'],
                    template: `
                        <div class="sw-confirm-modal-stub" :data-title="title" :data-text="text">
                            <button class="sw-confirm-modal-stub__confirm" @click="$emit('confirm')"></button>
                            <button class="sw-confirm-modal-stub__cancel" @click="$emit('cancel')"></button>
                        </div>`,
                },
            },
            provide: {
                paymentGatewayService: service,
                acl,
            },
            mocks: {
                $te: () => false,
            },
        },
    });

    // the notification mixin needs a store, the failure path is what is under test here
    wrapper.vm.createNotificationError = jest.fn();

    await flushPromises();

    return { wrapper, service, acl };
}

describe('module/sw-settings-payment-provider/page/sw-settings-payment-provider-list', () => {
    beforeEach(() => {
        global.activeAclRoles = ['payment_provider_config.viewer', 'payment_provider_config.editor'];
    });

    it('should load the providers from the server', async () => {
        const { wrapper, service } = await createWrapper();

        expect(service.listProviders).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.providers).toHaveLength(2);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('should show the loading state while the interfaces are on their way', async () => {
        const { wrapper } = await createWrapper({ pending: true });

        expect(wrapper.vm.isLoading).toBe(true);
        expect(wrapper.findAll('sw-skeleton-stub')).toHaveLength(2);
        // only the placeholder card is there, no interface card carries a name yet
        expect(wrapper.findAll('.mt-card-stub[data-title]')).toHaveLength(0);
    });

    it('should render one card per interface and no table', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.findAll('.mt-card-stub')).toHaveLength(2);
        expect(wrapper.find('.sw-data-grid-stub').exists()).toBe(false);
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('should name a card by the interface and keep the technical name as its second line', async () => {
        const { wrapper } = await createWrapper();

        const cards = wrapper.findAll('.mt-card-stub');

        expect(cards.at(0).attributes('data-title')).toBe('支付宝');
        expect(cards.at(0).find('.sw-settings-payment-provider-list__technical-name').text()).toBe('alipay');
        expect(cards.at(1).attributes('data-title')).toBe('Stripe');
        expect(cards.at(1).find('.sw-settings-payment-provider-list__technical-name').text()).toBe('stripe');
    });

    it('should only render the configuration link and the switch as the content of a card', async () => {
        const { wrapper } = await createWrapper();

        const content = wrapper.findAll('.mt-card-stub').at(0).find('.sw-container-stub');

        expect(content.findAll('.sw-internal-link-stub')).toHaveLength(1);
        expect(content.findAll('.mt-switch-stub')).toHaveLength(1);
        // no counts, no badges, nothing else - the card is the name, the link and the switch
        expect(content.text()).toBe('global.default.configure');
    });

    it('should line the link and the switch up on the right of the card', async () => {
        const { wrapper } = await createWrapper();

        const content = wrapper.findAll('.mt-card-stub').at(0).find('.sw-container-stub');

        // the left column is empty, `justify="end"` moves the link to its end, so the link and the
        // switch sit next to each other on the right - the layout of the payment method card
        expect(content.attributes('data-columns')).toBe('1fr auto');
        expect(content.attributes('data-justify')).toBe('end');
        expect(content.element.firstElementChild.classList.contains('sw-internal-link-stub')).toBe(true);
        expect(content.element.lastElementChild.classList.contains('mt-switch-stub')).toBe(true);
    });

    it('should sort the providers by their technical name', async () => {
        const { wrapper } = await createWrapper({
            providers: [registryProviders[1], registryProviders[0]],
        });

        expect(wrapper.vm.sortedProviders.map((provider) => provider.technicalName)).toEqual(['alipay', 'stripe']);
        expect(wrapper.findAll('.mt-card-stub').at(0).attributes('data-title')).toBe('支付宝');
    });

    it('should link a card to the configuration of its interface', async () => {
        const { wrapper } = await createWrapper();

        const link = wrapper.findAll('.mt-card-stub').at(0).find('.sw-internal-link-stub');

        expect(link.exists()).toBe(true);
        // the interface is addressed by its technical name, not by an id
        expect(link.attributes('data-route')).toBe('sw.settings.payment.provider.detail');
        expect(link.attributes('data-id')).toBe('alipay');
        expect(link.attributes('data-disabled')).toBe('false');
        expect(link.text()).toBe('global.default.configure');
    });

    it('should disable the link without the viewer privilege', async () => {
        const { wrapper } = await createWrapper({ canView: false });

        expect(wrapper.findAll('.sw-internal-link-stub').at(0).attributes('data-disabled')).toBe('true');
    });

    it('should show the enabled state of an interface as a switch', async () => {
        const { wrapper } = await createWrapper();

        const cards = wrapper.findAll('.mt-card-stub');

        expect(cards.at(0).find('.mt-switch-stub').attributes('data-enabled')).toBe('true');
        expect(cards.at(1).find('.mt-switch-stub').attributes('data-enabled')).toBe('false');
        expect(cards.at(0).find('.mt-switch-stub').attributes('data-label')).toBe(
            'sw-settings-payment-provider.provider.enabled',
        );
    });

    it('should open the confirmation instead of writing when the switch is clicked', async () => {
        const { wrapper, service } = await createWrapper();

        expect(wrapper.find('.sw-confirm-modal-stub').exists()).toBe(false);

        await wrapper.findAll('.mt-switch-stub').at(1).trigger('click');

        expect(wrapper.find('.sw-confirm-modal-stub').exists()).toBe(true);
        // nothing is written before the merchant has answered the dialog
        expect(service.saveProvider).not.toHaveBeenCalled();
        expect(wrapper.vm.providers.find((provider) => provider.technicalName === 'stripe').enabled).toBe(false);
    });

    it('should announce enabling and disabling with their own text', async () => {
        const { wrapper } = await createWrapper();

        // stripe is off, so the click offers to switch it on
        await wrapper.findAll('.mt-switch-stub').at(1).trigger('click');

        const enableModal = wrapper.find('.sw-confirm-modal-stub');

        expect(enableModal.attributes('data-title')).toBe('sw-settings-payment-provider.list.enableModalTitle');
        expect(enableModal.attributes('data-text')).toBe('sw-settings-payment-provider.list.enableModalText');

        await wrapper.find('.sw-confirm-modal-stub__cancel').trigger('click');
        await flushPromises();
        // alipay is on, so the click offers to switch it off
        await wrapper.findAll('.mt-switch-stub').at(0).trigger('click');

        const disableModal = wrapper.find('.sw-confirm-modal-stub');

        expect(disableModal.attributes('data-title')).toBe('sw-settings-payment-provider.list.disableModalTitle');
        expect(disableModal.attributes('data-text')).toBe('sw-settings-payment-provider.list.disableModalText');
    });

    it('should store the enabled state through the service once the confirmation is accepted', async () => {
        const { wrapper, service } = await createWrapper();

        await wrapper.findAll('.mt-switch-stub').at(1).trigger('click');
        await wrapper.find('.sw-confirm-modal-stub__confirm').trigger('click');
        await flushPromises();

        expect(service.saveProvider).toHaveBeenCalledWith('stripe', { enabled: true });
        expect(wrapper.vm.providers.find((provider) => provider.technicalName === 'stripe').enabled).toBe(true);
        expect(wrapper.find('.sw-confirm-modal-stub').exists()).toBe(false);
    });

    it('should write nothing and keep the switch in place when the confirmation is cancelled', async () => {
        const { wrapper, service } = await createWrapper();

        const keyBefore = wrapper.vm.toggleRenderKey;

        await wrapper.findAll('.mt-switch-stub').at(1).trigger('click');
        await wrapper.find('.sw-confirm-modal-stub__cancel').trigger('click');
        await flushPromises();

        expect(service.saveProvider).not.toHaveBeenCalled();
        // the switch is bound one way, the stored state never moved, so it stays where it was
        expect(wrapper.vm.providers.find((provider) => provider.technicalName === 'stripe').enabled).toBe(false);
        expect(wrapper.findAll('.mt-switch-stub').at(1).attributes('data-enabled')).toBe('false');
        expect(wrapper.find('.sw-confirm-modal-stub').exists()).toBe(false);
        // and the switch is rebuilt, because the browser flipped the native box on the click and
        // Vue has no changed value to patch back otherwise
        expect(wrapper.vm.toggleRenderKey).toBe(keyBefore + 1);
    });

    it('should undo the switch when the interface cannot be saved', async () => {
        const { wrapper, service } = await createWrapper({ saveFails: true });

        await wrapper.findAll('.mt-switch-stub').at(1).trigger('click');
        await wrapper.find('.sw-confirm-modal-stub__confirm').trigger('click');
        await flushPromises();

        expect(service.saveProvider).toHaveBeenCalledWith('stripe', { enabled: true });
        // the switch moved before the request, so the failure has to move it back
        expect(wrapper.vm.providers.find((provider) => provider.technicalName === 'stripe').enabled).toBe(false);
        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should disable the switch without the editor privilege', async () => {
        const { wrapper } = await createWrapper({ canEdit: false });

        expect(wrapper.findAll('.mt-switch-stub').at(0).attributes('disabled')).toBeDefined();
    });

    it('should show the empty state when the installation ships no interface', async () => {
        const { wrapper } = await createWrapper({ providers: [] });

        expect(wrapper.findAll('.mt-card-stub')).toHaveLength(0);
        expect(wrapper.find('.mt-empty-state-stub').exists()).toBe(true);
    });
});
