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
    template: '<div class="sw-form-field-renderer"></div>',
    methods: {
        setValue(value) {
            this.$emit('update:value', value);
        },
    },
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
                'mt-select': true,
                'sw-entity-single-select': true,
                'mt-text-field': true,
                'mt-number-field': true,
                'mt-textarea': true,
                'sw-form-field-renderer': formFieldRendererStub,
                'mt-card': {
                    template: '<div class="mt-card"><slot></slot></div>',
                    props: ['title', 'subtitle', 'positionIdentifier'],
                },
                'mt-switch': true,
                'mt-button': true,
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

        expect(wrapper.vm.isNew).toBe(true);
        expect(wrapper.vm.gateway.technicalName).toBe('with-credentials');
        expect(wrapper.vm.gateway.enabled).toBe(true);
        expect(wrapper.vm.credentialFields.map((field) => field.name)).toEqual(['accessKeyId', 'accessKeySecret']);
        expect(wrapper.vm.credentialValues).toEqual({ accessKeyId: '', accessKeySecret: '' });
    });

    it('should render one form field per credential the provider declares', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.findAll('.sw-form-field-renderer')).toHaveLength(2);
        expect(wrapper.vm.formFieldType(credentialProvider.credentialDefinitions[0])).toBe('text');
        expect(wrapper.vm.formFieldType(credentialProvider.credentialDefinitions[1])).toBe('password');
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

        expect(wrapper.vm.credentialValues).toEqual({ accessKeyId: 'the-id', accessKeySecret: 'the-secret' });
        expect(wrapper.vm.credentialFields).toHaveLength(2);
        expect(wrapper.vm.optionsText).toContain('"signName"');
    });

    it('should reset the credentials when another provider is picked', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.setCredentialValue(wrapper.vm.credentialFields[0], 'the-id');
        expect(wrapper.vm.credentialValues.accessKeyId).toBe('the-id');

        // what the provider that was picked before needed does not belong to the new one
        wrapper.vm.credentialValues = { accessKeyId: 'the-id', accessKeySecret: '' };
        wrapper.vm.gateway.technicalName = 'null';
        wrapper.vm.onProviderChange();

        expect(wrapper.vm.credentialValues).toEqual({});
    });

    it('should not save while a required credential is empty', async () => {
        ({ wrapper, repository } = await createWrapper());
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        await wrapper.vm.onSave();

        expect(notification).toHaveBeenCalled();
        expect(repository.save).not.toHaveBeenCalled();
    });

    it('should store the credentials in the shape the provider declares', async () => {
        ({ wrapper, repository } = await createWrapper());

        wrapper.vm.setCredentialValue(wrapper.vm.credentialFields[0], 'the-id');
        wrapper.vm.setCredentialValue(wrapper.vm.credentialFields[1], 'the-secret');

        await wrapper.vm.onSave();

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

        wrapper.vm.setCredentialValue(wrapper.vm.credentialFields[0], 'the-id');
        wrapper.vm.setCredentialValue(wrapper.vm.credentialFields[1], 'the-secret');

        await wrapper.vm.onSave();

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

        wrapper.vm.optionsText = '{"signName":"Shop"}';
        await wrapper.vm.onSave();

        expect(repository.save).toHaveBeenCalledWith(expect.objectContaining({ options: { signName: 'Shop' } }));

        repository.save.mockClear();
        wrapper.vm.optionsText = 'not json';

        await wrapper.vm.onSave();

        expect(repository.save).not.toHaveBeenCalled();
    });

    it('should keep a gateway without credentials free of an empty json object', async () => {
        ({ wrapper, repository } = await createWrapper({ providers: [freeTextProvider] }));

        expect(wrapper.vm.credentialFields).toHaveLength(0);

        await wrapper.vm.onSave();

        expect(repository.save).toHaveBeenCalledWith(expect.objectContaining({ credentials: null }));
    });

    it('should start a gateway that does not take an unknown country', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.acceptUnknownRegion).toBe(false);
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

        expect(wrapper.vm.acceptUnknownRegion).toBe(true);
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

        wrapper.vm.acceptUnknownRegion = true;
        await wrapper.vm.onSave();

        // the flag belongs to the row, not to the options the provider is called with
        expect(repository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                options: null,
                customFields: { sms_accept_unknown_region: true, another_flag: 'kept' },
            }),
        );
    });
});
