/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const credentialProvider = {
    technicalName: 'with-credentials',
    labelSnippet: 'sms.gateway.withCredentials',
    supportedRegions: ['CN'],
    supportsTemplateMode: true,
    credentialDefinitions: [
        { name: 'accessKeyId', type: 'string', required: true, labelSnippet: 'sms.credential.withCredentials.accessKeyId' },
        {
            name: 'accessKeySecret',
            type: 'secret',
            required: true,
            labelSnippet: 'sms.credential.withCredentials.accessKeySecret',
        },
    ],
};

const freeTextProvider = {
    technicalName: 'free-text',
    labelSnippet: 'sms.gateway.null',
    supportedRegions: [],
    supportsTemplateMode: false,
    credentialDefinitions: [],
};

const formFieldRendererStub = {
    name: 'sw-form-field-renderer',
    props: {
        type: { type: String, default: null },
        config: { type: Object, default: null },
        value: { default: null },
        disabled: { default: undefined },
    },
    template: `<div class="sw-form-field-renderer"><input :value="value" :disabled="disabled" @input="$emit('update:value', $event.target.value)"/></div>`,
};

async function createWrapper({ providers = [credentialProvider], gatewayId = null, storedGateway = null } = {}) {
    const repository = {
        // a row is created with the id it will be stored under, the same way the entity factory does
        create: jest.fn(() => ({ id: 'created-id' })),
        get: jest.fn(() => Promise.resolve(storedGateway)),
        save: jest.fn(() => Promise.resolve()),
    };

    const routerPush = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-sms-gateway-detail', { sync: true }), {
        props: {
            gatewayId,
        },
        global: {
            mocks: {
                $router: {
                    push: routerPush,
                },
            },
            provide: {
                repositoryFactory: {
                    create: () => repository,
                },
                acl: {
                    can: () => true,
                },
                smsGatewayProviderService: {
                    list: jest.fn(() => Promise.resolve(providers)),
                },
            },
            stubs: {
                'sw-page': {
                    template: `
                        <div>
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
                'sw-skeleton': true,
                'sw-settings-sms-section-tabs': true,
                'mt-select': {
                    name: 'mt-select',
                    props: ['modelValue', 'disabled', 'options'],
                    template: `<select :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)"><option v-for="option in options" :value="option.value">{{ option.label }}</option></select>`,
                },
                'sw-entity-single-select': true,
                'mt-text-field': {
                    props: ['modelValue', 'disabled', 'options'],
                    template: `<input :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
                'mt-number-field': {
                    props: ['modelValue', 'disabled', 'options'],
                    template: `<input :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
                'mt-textarea': {
                    props: ['modelValue', 'disabled', 'options'],
                    template: `<input :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
                'sw-form-field-renderer': formFieldRendererStub,
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                    props: ['title', 'subtitle', 'positionIdentifier'],
                },
                'mt-switch': {
                    props: ['modelValue', 'disabled'],
                    template: `<input type="checkbox" :checked="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.checked)"/>`,
                },
                'mt-button': { props: ['disabled', 'isLoading'], template: '<button :disabled="disabled"><slot/></button>' },
                'mt-icon': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, repository, routerPush };
}

describe('src/module/sw-settings-sms/page/sw-settings-sms-gateway-detail', () => {
    let wrapper;
    let repository;
    let routerPush;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should start a new gateway on the first provider of the server', async () => {
        ({ wrapper } = await createWrapper({ providers: [credentialProvider, freeTextProvider] }));

        expect(wrapper.get('.sw-settings-sms-gateway-detail__provider').attributes('disabled')).toBeUndefined();
        expect(wrapper.get('.sw-settings-sms-gateway-detail__provider').element.value).toBe('with-credentials');
        expect(wrapper.get('.sw-settings-sms-gateway-detail__enabled').element.checked).toBe(true);
        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(2);
        expect(wrapper.findAll('.sw-form-field-renderer input').map((input) => input.element.value)).toEqual(['', '']);
    });

    it('should render one form field per credential the provider declares', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(2);
        expect(wrapper.findAllComponents({ name: 'sw-form-field-renderer' })[0].props('type')).toBe('text');
        expect(wrapper.findAllComponents({ name: 'sw-form-field-renderer' })[1].props('type')).toBe('password');
    });

    it('should show the values that were stored for the fields the provider still declares', async () => {
        ({ wrapper } = await createWrapper({
            gatewayId: 'gateway-1',
            storedGateway: {
                id: 'gateway-1',
                technicalName: 'with-credentials',
                enabled: true,
                priority: 3,
                credentials: { accessKeyId: 'the-id', accessKeySecret: 'the-secret', dropped: 'x' },
                options: { signName: 'Shop' },
            },
        }));

        expect(wrapper.findAll('.sw-form-field-renderer input').map((input) => input.element.value)).toEqual([
            'the-id',
            'the-secret',
        ]);
        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(2);
        expect(wrapper.get('.sw-settings-sms-gateway-detail__options').element.value).toContain('"signName"');
    });

    it('should reset the credentials when another provider is picked', async () => {
        ({ wrapper } = await createWrapper({ providers: [credentialProvider, freeTextProvider] }));

        await wrapper.get('.sw-settings-sms-gateway-detail__credential-accessKeyId input').setValue('the-id');
        expect(wrapper.get('.sw-settings-sms-gateway-detail__credential-accessKeyId input').element.value).toBe('the-id');

        // what the provider that was picked before needed does not belong to the new one
        await wrapper.get('.sw-settings-sms-gateway-detail__provider').setValue('free-text');

        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(0);
    });

    it('should not save while a required credential is empty', async () => {
        ({ wrapper, repository } = await createWrapper());
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        await wrapper.get('.sw-settings-sms-gateway-detail__save-action').trigger('click');
        await flushPromises();

        expect(notification).toHaveBeenCalled();
        expect(repository.save).not.toHaveBeenCalled();
    });

    it('should store the credentials in the shape the provider declares', async () => {
        ({ wrapper, repository } = await createWrapper());

        await wrapper.get('.sw-settings-sms-gateway-detail__credential-accessKeyId input').setValue('the-id');
        await wrapper.get('.sw-settings-sms-gateway-detail__credential-accessKeySecret input').setValue('the-secret');

        await wrapper.get('.sw-settings-sms-gateway-detail__save-action').trigger('click');
        await flushPromises();

        expect(repository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                technicalName: 'with-credentials',
                credentials: { accessKeyId: 'the-id', accessKeySecret: 'the-secret' },
                options: null,
            }),
        );
    });

    it('should navigate to the row it created', async () => {
        ({ wrapper, routerPush } = await createWrapper());

        await wrapper.get('.sw-settings-sms-gateway-detail__credential-accessKeyId input').setValue('the-id');
        await wrapper.get('.sw-settings-sms-gateway-detail__credential-accessKeySecret input').setValue('the-secret');

        await wrapper.get('.sw-settings-sms-gateway-detail__save-action').trigger('click');
        await flushPromises();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.settings.sms.gateway.detail',
            params: { id: 'created-id' },
        });
    });

    it('should read the connection options as json and refuse a broken one', async () => {
        ({ wrapper, repository } = await createWrapper({
            gatewayId: 'gateway-1',
            storedGateway: {
                id: 'gateway-1',
                technicalName: 'with-credentials',
                enabled: true,
                priority: 0,
                credentials: { accessKeyId: 'id', accessKeySecret: 'secret' },
                options: null,
            },
        }));

        await wrapper.get('.sw-settings-sms-gateway-detail__options').setValue('{"signName":"Shop"}');
        await wrapper.get('.sw-settings-sms-gateway-detail__save-action').trigger('click');
        await flushPromises();

        expect(repository.save).toHaveBeenCalledWith(expect.objectContaining({ options: { signName: 'Shop' } }));

        repository.save.mockClear();
        await wrapper.get('.sw-settings-sms-gateway-detail__options').setValue('not json');

        await wrapper.get('.sw-settings-sms-gateway-detail__save-action').trigger('click');
        await flushPromises();

        expect(repository.save).not.toHaveBeenCalled();
    });

    it('should keep a gateway without credentials free of an empty json object', async () => {
        ({ wrapper, repository } = await createWrapper({ providers: [freeTextProvider] }));

        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(0);

        await wrapper.get('.sw-settings-sms-gateway-detail__save-action').trigger('click');
        await flushPromises();

        expect(repository.save).toHaveBeenCalledWith(expect.objectContaining({ credentials: null }));
    });

    it('should start a gateway that does not take an unknown country', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.get('.sw-settings-sms-gateway-detail__accept-unknown-region').element.checked).toBe(false);
    });

    it('should show the routing flag that was stored for the gateway', async () => {
        ({ wrapper } = await createWrapper({
            gatewayId: 'gateway-1',
            storedGateway: {
                id: 'gateway-1',
                technicalName: 'with-credentials',
                enabled: true,
                priority: 0,
                credentials: { accessKeyId: 'id', accessKeySecret: 'secret' },
                options: null,
                customFields: { sms_accept_unknown_region: true },
            },
        }));

        expect(wrapper.get('.sw-settings-sms-gateway-detail__accept-unknown-region').element.checked).toBe(true);
    });

    it('should write the routing flag into the custom fields of the row', async () => {
        ({ wrapper, repository } = await createWrapper({
            gatewayId: 'gateway-1',
            storedGateway: {
                id: 'gateway-1',
                technicalName: 'with-credentials',
                enabled: true,
                priority: 0,
                credentials: { accessKeyId: 'id', accessKeySecret: 'secret' },
                options: null,
                customFields: { sms_accept_unknown_region: false, another_flag: 'kept' },
            },
        }));

        await wrapper.get('.sw-settings-sms-gateway-detail__accept-unknown-region').setValue(true);
        await wrapper.get('.sw-settings-sms-gateway-detail__save-action').trigger('click');
        await flushPromises();

        // the flag belongs to the row, not to the options the provider is called with
        expect(repository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                options: null,
                customFields: { sms_accept_unknown_region: true, another_flag: 'kept' },
            }),
        );
    });
});
