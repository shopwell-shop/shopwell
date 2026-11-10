/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import EntityCollection from '@shopwell-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';

const providers = [
    {
        technicalName: 'reports-itself',
        labelSnippet: 'sms.gateway.reportsItself',
        supportedRegions: ['CN'],
        supportsTemplateMode: true,
        credentialDefinitions: [],
        audit: {
            requiresApproval: true,
            canSubmit: true,
            canReadStatus: true,
            submissionFields: [],
        },
    },
    {
        technicalName: 'console-only',
        labelSnippet: 'sms.gateway.consoleOnly',
        supportedRegions: ['CN'],
        supportsTemplateMode: true,
        credentialDefinitions: [],
        audit: {
            requiresApproval: true,
            canSubmit: false,
            canReadStatus: false,
            submissionFields: [],
        },
    },
    {
        technicalName: 'free-text',
        labelSnippet: 'sms.gateway.freeText',
        supportedRegions: [],
        supportsTemplateMode: false,
        credentialDefinitions: [],
        audit: {
            requiresApproval: false,
            canSubmit: false,
            canReadStatus: false,
            submissionFields: [],
        },
    },
];

const defaultLanguage = {
    id: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
    name: 'Deutsch',
    locale: { name: 'Deutsch (DE)', code: 'de-DE' },
};

async function createWrapper({
    templateId = null,
    storedTemplate = null,
    gateways = [],
    providerList = providers,
    language = defaultLanguage,
    can = () => true,
} = {}) {
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

    const languageRepository = {
        search: jest.fn(() => Promise.resolve({ first: () => language })),
    };

    const routerPush = jest.fn();

    const wrapper = mount(await wrapTestComponent('sw-settings-sms-template-detail', { sync: true }), {
        props: {
            templateId,
        },
        global: {
            mocks: {
                $t: (key, params) => (params ? `${key} ${JSON.stringify(params)}` : key),
                $router: {
                    push: routerPush,
                },
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'sms_gateway') {
                            return gatewayRepository;
                        }

                        if (entity === 'language') {
                            return languageRepository;
                        }

                        return templateRepository;
                    },
                },
                acl: {
                    can,
                },
                smsGatewayProviderService: {
                    list: jest.fn(() => Promise.resolve(providerList)),
                },
            },
            stubs: {
                'sw-settings-sms-page': await wrapTestComponent('sw-settings-sms-page'),
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
                'sw-language-switch': {
                    template: `<button class="change-language" @click="$emit('on-change')">Change language</button>`,
                },
                'sw-settings-sms-section-tabs': true,
                'mt-select': {
                    name: 'mt-select',
                    props: ['modelValue', 'disabled', 'options'],
                    template: `<select :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)"><option v-for="option in options" :value="option.value">{{ option.label }}</option></select>`,
                },
                'sw-settings-sms-template-mapping-grid': {
                    name: 'sw-settings-sms-template-mapping-grid',
                    props: [
                        'template',
                        'providers',
                        'gateways',
                        'disabled',
                    ],
                    template: `<button class="reload-mapping" @click="$emit('reload')">Reload</button>`,
                },
                'sw-data-grid': {
                    name: 'sw-data-grid',
                    props: ['dataSource', 'columns'],
                    template: '<div class="ledger"/>',
                },
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
                'mt-banner': {
                    template: '<div class="mt-banner"><slot></slot></div>',
                    props: ['variant', 'title'],
                },
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

    return { wrapper, templateRepository, gatewayRepository, languageRepository, routerPush };
}

function storedTemplateWithMappings(mappings = []) {
    return {
        id: 'template-1',
        technicalName: 'customer.otp.login',
        scene: 'otp',
        content: 'Your code is {{ code }}',
        enabled: true,
        mappings: new EntityCollection('/api', 'sms_template_mapping', {}, null, mappings, mappings.length),
    };
}

describe('src/module/sw-settings-sms/page/sw-settings-sms-template-detail', () => {
    let wrapper;
    let templateRepository;
    let languageRepository;
    let routerPush;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should load templates for roles without gateway account access', async () => {
        let gatewayRepository;
        ({ wrapper, gatewayRepository } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([]),
            can: (privilege) => privilege.startsWith('sms_template.'),
        }));
        expect(gatewayRepository.search).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-settings-sms-template-detail__technical-name').exists()).toBe(true);
    });

    it('should start a new template as a verification code', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.find('.sw-settings-sms-template-detail__mapping-save-first').exists()).toBe(true);
        expect(wrapper.get('.sw-settings-sms-template-detail__scene').element.value).toBe('otp');
        expect(wrapper.get('.sw-settings-sms-template-detail__enabled').element.checked).toBe(true);
        expect(wrapper.get('.sw-settings-sms-template-detail__params-schema').element.value).toBe('');
        expect(
            wrapper
                .getComponent('.sw-settings-sms-template-detail__scene')
                .props('options')
                .map((option) => option.value),
        ).toEqual(['otp', 'transaction', 'marketing']);
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
        expect(wrapper.get('.sw-settings-sms-template-detail__technical-name').element.value).toBe('customer.otp.login');
        expect(wrapper.get('.sw-settings-sms-template-detail__params-schema').element.value).toContain('"name": "code"');
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

        await wrapper.get('.sw-settings-sms-template-detail__params-schema').setValue('[{"name":"code","required":true}]');

        await wrapper.get('.sw-settings-sms-template-detail__save-action').trigger('click');
        await flushPromises();

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

        await wrapper.get('.sw-settings-sms-template-detail__params-schema').setValue('not json');

        await wrapper.get('.sw-settings-sms-template-detail__save-action').trigger('click');
        await flushPromises();

        expect(templateRepository.save).not.toHaveBeenCalled();
    });

    it('should navigate to the template it created', async () => {
        ({ wrapper, routerPush } = await createWrapper());

        await wrapper.get('.sw-settings-sms-template-detail__save-action').trigger('click');
        await flushPromises();

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

        await wrapper.get('.change-language').trigger('click');
        await flushPromises();

        expect(templateRepository.search).toHaveBeenCalledTimes(2);
    });

    it('should not read anything for a language switch while the template is new', async () => {
        ({ wrapper, templateRepository } = await createWrapper());

        await wrapper.get('.change-language').trigger('click');
        await flushPromises();

        expect(templateRepository.search).not.toHaveBeenCalled();
    });

    it('should present mappings and audit status in one editable grid', async () => {
        const mappings = [
            { id: 'mapping-1', technicalName: 'reports-itself', auditStatus: 'approved', templateCode: 'SMS_1' },
            { id: 'mapping-2', technicalName: 'free-text', auditStatus: 'pending', templateCode: null },
        ];
        ({ wrapper } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings(mappings),
        }));

        const grid = wrapper.getComponent({ name: 'sw-settings-sms-template-mapping-grid' });
        expect(Array.from(grid.props('template').mappings)).toEqual(mappings);
        expect(grid.props('providers')).toEqual(providers);
        expect(wrapper.findComponent({ name: 'sw-data-grid' }).exists()).toBe(false);
    });

    it('should wait until the template is saved before warning about missing mappings', async () => {
        ({ wrapper } = await createWrapper());
        expect(wrapper.find('.sw-settings-sms-template-detail__audit-missing-provider').exists()).toBe(false);
    });

    it('should point out the providers that require approval but are not mapped at all', async () => {
        ({ wrapper } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([
                { id: 'mapping-1', technicalName: 'reports-itself', auditStatus: 'pending' },
            ]),
        }));

        expect(wrapper.get('.sw-settings-sms-template-detail__audit-missing-provider').text()).toContain(
            'sms.gateway.consoleOnly',
        );
        expect(wrapper.get('.sw-settings-sms-template-detail__audit-missing-provider').text()).not.toContain(
            'sms.gateway.reportsItself',
        );
    });

    it('should point out nothing while every provider that requires approval is mapped', async () => {
        ({ wrapper } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([
                { id: 'mapping-1', technicalName: 'reports-itself', auditStatus: 'pending' },
                { id: 'mapping-2', technicalName: 'console-only', auditStatus: 'pending' },
            ]),
        }));

        expect(wrapper.find('.sw-settings-sms-template-detail__audit-missing-provider').exists()).toBe(false);
    });

    it('should name the default language of the shop in the note next to the mapping', async () => {
        ({ wrapper, languageRepository } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([]),
        }));

        const criteria = languageRepository.search.mock.calls[0][0];

        expect(criteria.filters[0].field).toBe('id');
        expect(criteria.associations.map((association) => association.association)).toEqual(['locale']);
        expect(wrapper.get('.sw-settings-sms-template-detail__audit-language-note').text()).toContain('Deutsch (DE)');
    });

    it('should leave the note without a language when the default one cannot be read', async () => {
        ({ wrapper } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([]),
            language: null,
        }));

        expect(wrapper.find('.sw-settings-sms-template-detail__audit-language-note').exists()).toBe(false);
    });

    it('should read the template again when the grid took one of the audit steps', async () => {
        ({ wrapper, templateRepository } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([]),
        }));

        await wrapper.get('.reload-mapping').trigger('click');
        await flushPromises();

        expect(templateRepository.search).toHaveBeenCalledTimes(2);
    });
});
