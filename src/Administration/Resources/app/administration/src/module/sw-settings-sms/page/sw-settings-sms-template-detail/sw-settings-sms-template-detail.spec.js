/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

const providers = [
    {
        technicalName: 'aliyun',
        labelSnippet: 'sms.gateway.aliyun',
        supportedRegions: ['CN'],
        supportsTemplateMode: true,
        credentialDefinitions: [],
    },
];

async function createWrapper({ templateId = null, storedTemplate = null, gateways = [] } = {}) {
    const templateRepository = {
        // a row is created with the id it will be stored under, the same way the entity factory does
        create: jest.fn(() => ({ id: 'created-id' })),
        search: jest.fn(() => Promise.resolve({ first: () => storedTemplate })),
        save: jest.fn(() => Promise.resolve()),
    };

    const gatewayRepository = {
        search: jest.fn(() =>
            Promise.resolve({
                getIds: () => gateways.map((gateway) => gateway.id),
                get: (id) => gateways.find((gateway) => gateway.id === id),
            }),
        ),
    };

    const routerPush = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-sms-template-detail', { sync: true }), {
        props: {
            templateId,
        },
        global: {
            mocks: {
                $router: {
                    push: routerPush,
                },
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => (entity === 'sms_gateway' ? gatewayRepository : templateRepository),
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
                            <slot name="language-switch"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-container': await wrapTestComponent('sw-container'),
                'sw-extension-component-section': await wrapTestComponent('sw-extension-component-section'),
                'sw-skeleton': true,
                'sw-language-switch': true,
                'sw-settings-sms-section-tabs': true,
                'mt-select': true,
                'sw-settings-sms-template-mapping-grid': true,
                'mt-text-field': true,
                'mt-number-field': true,
                'mt-textarea': true,
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

    return { wrapper, templateRepository, gatewayRepository, routerPush };
}

describe('src/module/sw-settings-sms/page/sw-settings-sms-template-detail', () => {
    let wrapper;
    let templateRepository;
    let routerPush;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should start a new template as a verification code', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.isNew).toBe(true);
        expect(wrapper.vm.template.scene).toBe('otp');
        expect(wrapper.vm.template.enabled).toBe(true);
        expect(wrapper.vm.paramsSchemaText).toBe('');
        expect(wrapper.vm.sceneOptions.map((option) => option.value)).toEqual(['otp', 'transaction', 'marketing']);
    });

    it('should load the template with its bodies and its mappings', async () => {
        ({ wrapper, templateRepository } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: {
                id: 'template-1',
                technicalName: 'customer.otp.login',
                scene: 'otp',
                content: 'Your code is {{ code }}',
                paramsSchema: [{ name: 'code', required: true }],
                enabled: true,
                mappings: null,
            },
        }));

        const criteria = templateRepository.search.mock.calls[0][0];

        expect(criteria.associations.map((association) => association.association)).toEqual([
            'translations',
            'mappings',
        ]);
        expect(wrapper.vm.template.technicalName).toBe('customer.otp.login');
        expect(wrapper.vm.paramsSchemaText).toContain('"name": "code"');
    });

    it('should store the parameter contract as json', async () => {
        ({ wrapper, templateRepository } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: {
                id: 'template-1',
                technicalName: 'customer.otp.login',
                scene: 'otp',
                paramsSchema: null,
                enabled: true,
            },
        }));

        wrapper.vm.paramsSchemaText = '[{"name":"code","required":true}]';

        await wrapper.vm.onSave();

        expect(templateRepository.save).toHaveBeenCalledWith(
            expect.objectContaining({
                technicalName: 'customer.otp.login',
                paramsSchema: [{ name: 'code', required: true }],
            }),
        );
    });

    it('should refuse a parameter contract that is not json', async () => {
        ({ wrapper, templateRepository } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: {
                id: 'template-1',
                technicalName: 'customer.otp.login',
                scene: 'otp',
                paramsSchema: null,
                enabled: true,
            },
        }));

        wrapper.vm.paramsSchemaText = 'not json';

        await wrapper.vm.onSave();

        expect(templateRepository.save).not.toHaveBeenCalled();
    });

    it('should navigate to the template it created', async () => {
        ({ wrapper, routerPush } = await createWrapper());

        await wrapper.vm.onSave();

        expect(routerPush).toHaveBeenCalledWith({
            name: 'sw.settings.sms.template.detail',
            params: { id: 'created-id' },
        });
    });

    it('should read the template again when the administration switches language', async () => {
        ({ wrapper, templateRepository } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: {
                id: 'template-1',
                technicalName: 'customer.otp.login',
                scene: 'otp',
                paramsSchema: null,
                enabled: true,
            },
        }));

        await wrapper.vm.onLanguageChange();

        expect(templateRepository.search).toHaveBeenCalledTimes(2);
    });

    it('should not read anything for a language switch while the template is new', async () => {
        ({ wrapper, templateRepository } = await createWrapper());

        await wrapper.vm.onLanguageChange();

        expect(templateRepository.search).not.toHaveBeenCalled();
    });
});
