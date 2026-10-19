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
                    can: () => true,
                },
                smsGatewayProviderService: {
                    list: jest.fn(() => Promise.resolve(providerList)),
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
                'sw-data-grid': true,
                'mt-text-field': true,
                'mt-number-field': true,
                'mt-textarea': true,
                'mt-banner': {
                    template: '<div class="mt-banner"><slot></slot></div>',
                    props: ['variant', 'title'],
                },
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

    it('should build the ledger from the mappings of the template', async () => {
        ({ wrapper } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([
                {
                    id: 'mapping-1',
                    templateId: 'template-1',
                    technicalName: 'reports-itself',
                    gatewayId: 'gateway-1',
                    templateCode: 'SMS_1',
                    auditStatus: 'approved',
                    customFields: { sms_last_synced_at: '2026-10-19 10:00:00.000' },
                },
                {
                    id: 'mapping-2',
                    templateId: 'template-1',
                    technicalName: 'free-text',
                    gatewayId: null,
                    templateCode: null,
                    auditStatus: 'pending',
                    customFields: null,
                },
            ]),
        }));

        expect(wrapper.vm.auditLedger).toEqual([
            {
                mappingId: 'mapping-1',
                technicalName: 'reports-itself',
                providerLabel: 'sms.gateway.reportsItself',
                templateCode: 'SMS_1',
                auditStatus: 'approved',
                auditStatusLabel: 'sms.approval.status.approved',
                lastSyncedAt: '2026-10-19 10:00:00.000',
            },
            {
                mappingId: 'mapping-2',
                technicalName: 'free-text',
                providerLabel: 'sms.gateway.freeText',
                templateCode: null,
                auditStatus: 'pending',
                auditStatusLabel: 'sms.approval.status.pending',
                lastSyncedAt: null,
            },
        ]);
    });

    it('should keep the language of a message out of the ledger', async () => {
        ({ wrapper } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([
                { id: 'mapping-1', technicalName: 'reports-itself', auditStatus: 'pending' },
            ]),
        }));

        expect(wrapper.vm.auditLedgerColumns.map((column) => column.property)).toEqual([
            'providerLabel',
            'templateCode',
            'auditStatusLabel',
            'lastSyncedAt',
        ]);
    });

    it('should point out the providers that require approval but are not mapped at all', async () => {
        ({ wrapper } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([
                { id: 'mapping-1', technicalName: 'reports-itself', auditStatus: 'pending' },
            ]),
        }));

        expect(wrapper.vm.providersMissingMapping.map((provider) => provider.technicalName)).toEqual([
            'console-only',
        ]);
        expect(wrapper.vm.missingProviderLabel).toBe('sms.gateway.consoleOnly');
    });

    it('should point out nothing while every provider that requires approval is mapped', async () => {
        ({ wrapper } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([
                { id: 'mapping-1', technicalName: 'reports-itself', auditStatus: 'pending' },
                { id: 'mapping-2', technicalName: 'console-only', auditStatus: 'pending' },
            ]),
        }));

        expect(wrapper.vm.providersMissingMapping).toEqual([]);
        expect(wrapper.vm.missingProviderLabel).toBe('');
    });

    it('should name the default language of the shop in the note next to the mapping', async () => {
        ({ wrapper, languageRepository } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([]),
        }));

        const criteria = languageRepository.search.mock.calls[0][0];

        expect(criteria.filters[0].field).toBe('id');
        expect(criteria.associations.map((association) => association.association)).toEqual(['locale']);
        expect(wrapper.vm.defaultLanguageName).toBe('Deutsch (DE)');
    });

    it('should leave the note without a language when the default one cannot be read', async () => {
        ({ wrapper } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([]),
            language: null,
        }));

        expect(wrapper.vm.defaultLanguageName).toBe('');
    });

    it('should read the template again when the grid took one of the audit steps', async () => {
        ({ wrapper, templateRepository } = await createWrapper({
            templateId: 'template-1',
            storedTemplate: storedTemplateWithMappings([]),
        }));

        await wrapper.vm.onMappingChanged();

        expect(templateRepository.search).toHaveBeenCalledTimes(2);
    });
});
