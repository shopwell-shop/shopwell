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
            {
                channel: 'alipay_h5',
                label: '支付宝 手机网站支付',
                flow: 'redirect',
                currencies: ['CNY'],
                devices: ['wap'],
                runtimeParams: {},
                paramFields: [],
                enabled: true,
                configured: true,
                settings: {},
            },
            {
                channel: 'alipay_mini',
                label: '支付宝 小程序支付',
                flow: 'sdk',
                currencies: ['CNY'],
                devices: ['mini'],
                runtimeParams: { buyer_id: true },
                paramFields: [
                    {
                        name: 'app_id',
                        label: '小程序 APPID',
                        type: 'text',
                        required: true,
                        secret: false,
                        options: {},
                        default: null,
                        value: 'wx-mini-1',
                        configured: true,
                    },
                ],
                enabled: false,
                configured: true,
                settings: {},
            },
        ],
    },
];

async function createWrapper({ providers = registryProviders, providerId = 'alipay', saveFails = false } = {}) {
    const service = {
        listProviders: jest.fn(() => Promise.resolve(providers)),
        saveProvider: jest.fn(() => (saveFails ? Promise.reject(new Error('save failed')) : Promise.resolve())),
        saveProviderChannel: jest.fn(() => Promise.resolve()),
        resetProvider: jest.fn(() => Promise.resolve()),
        resetProviderChannel: jest.fn(() => Promise.resolve()),
    };

    const push = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-payment-provider-detail', { sync: true }), {
        props: { providerId },
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': { template: '<div><slot></slot></div>' },
                'sw-container': { template: '<div><slot></slot></div>' },
                'sw-skeleton': true,
                'sw-extension-component-section': true,
                'sw-payment-config-field': {
                    props: ['field', 'modelValue'],
                    template: '<div class="sw-payment-config-field-stub">{{ field.name }}</div>',
                },
                /**
                 * The double of the card reports the title it was given, so the test can tell which
                 * cards the page renders and in which order.
                 */
                'mt-card': {
                    props: ['title', 'subtitle'],
                    template: `
                        <div class="mt-card-stub" :data-title="title">
                            <slot></slot>
                            <slot name="headerRight"></slot>
                        </div>`,
                },
                'mt-button': true,
                'mt-badge': true,
                'mt-icon': true,
                'mt-switch': true,
                'mt-banner': true,
                'mt-empty-state': true,
                'mt-modal-root': { template: '<div><slot></slot></div>' },
                'mt-modal': { template: '<div><slot></slot><slot name="footer"></slot></div>' },
                'mt-modal-close': true,
                'mt-modal-action': true,
                'sw-discard-changes-modal': true,
            },
            provide: {
                paymentGatewayService: service,
                acl: { can: () => true },
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

describe('module/sw-settings-payment-provider/page/sw-settings-payment-provider-detail', () => {
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

    it('should load the provider of the route and prefill the fields that are not secret', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.provider.technicalName).toBe('alipay');
        expect(wrapper.vm.provider.credentialInput.app_id).toBe('2021000000000000');
        // a secret is never echoed back, the input starts empty
        expect(wrapper.vm.provider.credentialInput.private_key).toBe('');
    });

    it('should go back to the list when the interface of the route is unknown', async () => {
        const { wrapper, push } = await createWrapper({ providerId: 'unknown' });

        expect(wrapper.vm.provider).toBeNull();
        expect(push).toHaveBeenCalledWith({ name: 'sw.settings.payment.provider.index' });
    });

    it('should mark an enabled interface with missing required fields as invalid', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.provider.credentialInput.app_id = '';

        expect(wrapper.vm.isProviderInvalid()).toBe(true);
    });

    it('should count a configured secret as filled', async () => {
        const { wrapper } = await createWrapper();

        // the private key is configured and its input stays empty, that is not a missing field
        expect(wrapper.vm.isProviderInvalid()).toBe(false);
    });

    it('should refuse to save an enabled interface with missing fields', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.provider.credentialInput.app_id = '';

        await wrapper.vm.onSave();

        expect(service.saveProvider).not.toHaveBeenCalled();
    });

    it('should only save the interface when something changed', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.provider.credentialInput.app_id = '2021000000000001';

        await wrapper.vm.onSave();

        expect(service.saveProvider).toHaveBeenCalledTimes(1);
        expect(service.saveProvider).toHaveBeenCalledWith('alipay', {
            enabled: true,
            credentials: expect.objectContaining({ app_id: '2021000000000001' }),
            settings: {},
        });
        expect(service.saveProviderChannel).not.toHaveBeenCalled();
    });

    it('should only save the channel that changed', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.provider.channels[1].paramInput.app_id = 'wx-mini-2';

        await wrapper.vm.onSave();

        expect(service.saveProvider).not.toHaveBeenCalled();
        expect(service.saveProviderChannel).toHaveBeenCalledTimes(1);
        expect(service.saveProviderChannel).toHaveBeenCalledWith('alipay', 'alipay_mini', {
            enabled: false,
            params: expect.objectContaining({ app_id: 'wx-mini-2' }),
            settings: {},
        });
    });

    it('should reset the credentials of the interface', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.onOpenResetProvider();
        expect(wrapper.vm.resetTarget.type).toBe('provider');

        await wrapper.vm.onConfirmReset();

        expect(service.resetProvider).toHaveBeenCalledWith('alipay');
        expect(wrapper.vm.resetTarget).toBeNull();
    });

    it('should reset the parameters of a channel', async () => {
        const { wrapper, service } = await createWrapper();

        wrapper.vm.onOpenResetChannel(wrapper.vm.provider.channels[1]);
        expect(wrapper.vm.resetTarget.type).toBe('channel');

        await wrapper.vm.onConfirmReset();

        expect(service.resetProviderChannel).toHaveBeenCalledWith('alipay', 'alipay_mini');
        expect(wrapper.vm.resetTarget).toBeNull();
    });

    it('should render only the three cards of the page and no overview card', async () => {
        const { wrapper } = await createWrapper();

        const titles = wrapper.findAll('.mt-card-stub').map((card) => card.attributes('data-title'));

        expect(titles).toEqual([
            'sw-settings-payment-provider.provider.cardTitle',
            'sw-settings-payment-provider.channels.title',
            'sw-settings-payment-provider.danger.title',
        ]);
        // the overview card is gone, and with it the only place the technical name was printed on
        // this page - the list card keeps it in its header right
        expect(wrapper.find('.sw-settings-payment-provider-detail__summary').exists()).toBe(false);
        expect(wrapper.text()).not.toMatch(/\balipay\b/);
        expect(wrapper.vm.channelCount).toBe(2);
    });

    it('should render one table row per channel and fold the parameters by default', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.findAll('.sw-settings-payment-provider-detail__channel-row')).toHaveLength(2);
        expect(wrapper.findAll('.sw-settings-payment-provider-detail__channel-row--params')).toHaveLength(0);
        expect(wrapper.findAll('.sw-payment-config-field-stub')).toHaveLength(3);

        wrapper.vm.provider.channels.forEach((channel) => {
            expect(wrapper.vm.isChannelExpanded(channel)).toBe(false);
        });
    });

    it('should reveal the parameter fields of a channel when its row is expanded', async () => {
        const { wrapper } = await createWrapper();

        const mini = wrapper.vm.provider.channels[1];
        wrapper.vm.toggleChannel(mini);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChannelExpanded(mini)).toBe(true);

        const paramsRows = wrapper.findAll('.sw-settings-payment-provider-detail__channel-row--params');
        expect(paramsRows).toHaveLength(1);

        // the interface has three credential fields, the expanded channel adds its own one
        expect(wrapper.findAll('.sw-payment-config-field-stub')).toHaveLength(4);
    });

    it('should tell that a channel without parameter fields needs no further parameters', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.toggleChannel(wrapper.vm.provider.channels[0]);
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-settings-payment-provider-detail__channel-params-empty').text()).toBe(
            'sw-settings-payment-provider.channel.noParams',
        );
    });

    /**
     * The runtime parameters are no column of their own: they are what the app has to hand in when it
     * pays, so they are named inside the folded parameters instead of being repeated on every row.
     * The configuration state is no column either: whether a channel is configured is what the folded
     * parameters are there to change, and the switch says whether the channel is offered at all.
     */
    it('should render exactly the five channel columns and no runtime parameter column', async () => {
        const { wrapper } = await createWrapper();

        const headers = wrapper.findAll('.sw-settings-payment-provider-detail__channel-head th');

        expect(headers).toHaveLength(5);
        expect(headers.map((header) => header.text())).toEqual([
            'sw-settings-payment-provider.channel.columnLabel',
            'sw-settings-payment-provider.channel.columnFlow',
            'sw-settings-payment-provider.channel.columnCurrencies',
            'sw-settings-payment-provider.channel.columnDevices',
            'sw-settings-payment-provider.channel.enabled',
        ]);
    });

    /**
     * Every cell of a row has to belong to one of the declared columns, so a row that kept a cell of
     * the removed column would be one cell wider than the head.
     */
    it('should render as many cells per channel row as the head declares', async () => {
        const { wrapper } = await createWrapper();

        const headers = wrapper.findAll('.sw-settings-payment-provider-detail__channel-head th');
        const cells = wrapper.findAll('.sw-settings-payment-provider-detail__channel-row').at(0).findAll('td');

        expect(cells).toHaveLength(headers.length);
    });

    it('should span the folded parameters row over every declared column', async () => {
        const { wrapper } = await createWrapper();

        const headers = wrapper.findAll('.sw-settings-payment-provider-detail__channel-head th');

        wrapper.vm.toggleChannel(wrapper.vm.provider.channels[0]);
        await wrapper.vm.$nextTick();

        const paramsCell = wrapper.find('.sw-settings-payment-provider-detail__channel-cell--params');

        expect(Number(paramsCell.attributes('colspan'))).toBe(headers.length);
    });

    /**
     * The name cell is the control: the row has no toggle column of its own anymore, because that one
     * was the first thing a narrow window cut off.
     */
    it('should fold a channel out when its name cell is clicked', async () => {
        const { wrapper } = await createWrapper();

        const cell = wrapper.findAll('.sw-settings-payment-provider-detail__channel-cell--channel').at(1);

        expect(cell.attributes('aria-expanded')).toBe('false');

        await cell.trigger('click');

        expect(wrapper.vm.isChannelExpanded(wrapper.vm.provider.channels[1])).toBe(true);
        expect(wrapper.findAll('.sw-settings-payment-provider-detail__channel-row--params')).toHaveLength(1);
        expect(
            wrapper.findAll('.sw-settings-payment-provider-detail__channel-cell--channel').at(1).attributes('aria-expanded'),
        ).toBe('true');
    });

    it('should also fold a channel out with the keyboard', async () => {
        const { wrapper } = await createWrapper();

        const cell = wrapper.findAll('.sw-settings-payment-provider-detail__channel-cell--channel').at(0);

        await cell.trigger('keydown.enter');

        expect(wrapper.vm.isChannelExpanded(wrapper.vm.provider.channels[0])).toBe(true);
    });

    /**
     * Clearing a channel is only an action when there is something to clear, so the button lives in
     * the parameters it clears - and a channel that never stored a value offers none.
     */
    it('should offer clearing the parameters inside the folded parameters of a configured channel', async () => {
        const { wrapper } = await createWrapper();

        const mini = wrapper.vm.provider.channels[1];
        wrapper.vm.toggleChannel(mini);
        await wrapper.vm.$nextTick();

        const paramsRow = wrapper.find('.sw-settings-payment-provider-detail__channel-row--params');

        expect(paramsRow.find('.sw-settings-payment-provider-detail__channel-reset').exists()).toBe(true);
        expect(wrapper.vm.hasStoredParams(mini)).toBe(true);
    });

    it('should not offer clearing a channel that stored no parameter', async () => {
        const { wrapper } = await createWrapper();

        const h5 = wrapper.vm.provider.channels[0];
        wrapper.vm.toggleChannel(h5);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.hasStoredParams(h5)).toBe(false);
        expect(wrapper.find('.sw-settings-payment-provider-detail__channel-reset').exists()).toBe(false);
    });

    it('should fold a channel again when its row is expanded a second time', async () => {
        const { wrapper } = await createWrapper();

        const mini = wrapper.vm.provider.channels[1];
        wrapper.vm.toggleChannel(mini);
        wrapper.vm.toggleChannel(mini);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChannelExpanded(mini)).toBe(false);
        expect(wrapper.findAll('.sw-settings-payment-provider-detail__channel-row--params')).toHaveLength(0);
    });

    it('should offer the danger zone to clear the credentials of the interface', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-settings-payment-provider-detail__danger-card').exists()).toBe(true);
        expect(wrapper.find('.sw-settings-payment-provider-detail__danger-action').exists()).toBe(true);
    });

    it('should only be dirty when the interface or one of its channels changed', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.isDirty).toBe(false);

        wrapper.vm.provider.channels[0].enabled = false;
        expect(wrapper.vm.isDirty).toBe(true);

        wrapper.vm.provider.channels[0].enabled = true;
        expect(wrapper.vm.isDirty).toBe(false);

        wrapper.vm.provider.credentialInput.app_id = '2021000000000001';
        expect(wrapper.vm.isDirty).toBe(true);
    });

    it('should leave the page without asking when nothing changed', async () => {
        const { wrapper } = await createWrapper();

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, { name: 'sw.settings.payment.provider.index' }, {}, next);

        expect(next).toHaveBeenCalledWith();
        expect(wrapper.vm.isDisplayingLeavePageWarning).toBe(false);
    });

    /**
     * The navigation is suspended, not cancelled - the guard hands the decision to the modal and
     * keeps `next` until the operator has answered.
     */
    it('should suspend the navigation and warn about the unsaved changes', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.provider.credentialInput.app_id = '2021000000000001';

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, { name: 'sw.settings.payment.provider.index' }, {}, next);
        await wrapper.vm.$nextTick();

        expect(next).not.toHaveBeenCalled();
        expect(wrapper.vm.isDisplayingLeavePageWarning).toBe(true);
        expect(wrapper.vm.nextRoute).toBe(next);
        expect(wrapper.find('sw-discard-changes-modal-stub').exists()).toBe(true);
    });

    it('should resume the suspended navigation after the changes were discarded', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.provider.credentialInput.app_id = '2021000000000001';

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, { name: 'sw.settings.payment.provider.index' }, {}, next);

        wrapper.vm.onLeaveModalConfirm();
        await wrapper.vm.$nextTick();

        // the navigation resumes instead of being pushed again, which would run into the guard anew
        expect(next).toHaveBeenCalledWith();
        expect(next).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.isDisplayingLeavePageWarning).toBe(false);
        expect(wrapper.vm.nextRoute).toBeNull();
    });

    it('should abort the suspended navigation when the operator keeps editing', async () => {
        const { wrapper } = await createWrapper();

        wrapper.vm.provider.credentialInput.app_id = '2021000000000001';

        const next = jest.fn();
        wrapper.vm.$options.beforeRouteLeave.call(wrapper.vm, { name: 'sw.settings.payment.provider.index' }, {}, next);

        wrapper.vm.onLeaveModalClose();
        await wrapper.vm.$nextTick();

        expect(next).toHaveBeenCalledWith(false);
        expect(wrapper.vm.isDisplayingLeavePageWarning).toBe(false);
        expect(wrapper.vm.nextRoute).toBeNull();
    });
});
