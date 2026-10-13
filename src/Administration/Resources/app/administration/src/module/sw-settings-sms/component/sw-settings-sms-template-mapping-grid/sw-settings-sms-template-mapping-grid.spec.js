/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import EntityCollection from '@shopwell-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';

const providers = [
    {
        technicalName: 'aliyun',
        labelSnippet: 'sms.gateway.aliyun',
        supportedRegions: ['CN'],
        supportsTemplateMode: true,
        credentialDefinitions: [],
    },
    {
        technicalName: 'null',
        labelSnippet: 'sms.gateway.null',
        supportedRegions: [],
        supportsTemplateMode: false,
        credentialDefinitions: [],
    },
];

const gateways = [
    { id: 'gateway-1', technicalName: 'aliyun', label: 'main' },
];

async function createWrapper({ mappings = [], templateId = 'template-1' } = {}) {
    const created = [];
    const repository = {
        create: jest.fn(() => {
            const entity = { id: `mapping-${created.length + 1}` };
            created.push(entity);

            return entity;
        }),
    };

    // The template detail keeps the item in its reactive state, so the grid has to see the same
    // thing here - a plain array would swallow the added row instead of reporting it back.
    const template = reactive({
        id: templateId,
        technicalName: 'customer.otp.login',
        mappings: new EntityCollection('/api', 'sms_template_mapping', {}, null, mappings, mappings.length),
    });

    const wrapper = mount(await wrapTestComponent('sw-settings-sms-template-mapping-grid', { sync: true }), {
        props: {
            template,
            providers,
            gateways,
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => repository,
                },
            },
            stubs: {
                'sw-single-select': true,
                'mt-text-field': true,
                'mt-button': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, template, repository };
}

describe('src/module/sw-settings-sms/component/sw-settings-sms-template-mapping-grid', () => {
    let wrapper;
    let template;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should list the rows of the template', async () => {
        ({ wrapper } = await createWrapper({
            mappings: [
                {
                    id: 'mapping-1',
                    templateId: 'template-1',
                    technicalName: 'aliyun',
                    gatewayId: null,
                    templateCode: 'SMS_1',
                    signName: 'Shop',
                    auditStatus: 'approved',
                },
            ],
        }));

        expect(wrapper.vm.mappings).toHaveLength(1);
        expect(wrapper.vm.mappings[0].templateCode).toBe('SMS_1');
    });

    it('should add a row that holds for the first provider of the server', async () => {
        ({ wrapper, template } = await createWrapper());

        wrapper.vm.onAddMapping();

        expect(template.mappings).toHaveLength(1);
        expect(wrapper.vm.mappings[0]).toEqual(
            expect.objectContaining({
                id: 'mapping-1',
                templateId: 'template-1',
                technicalName: 'aliyun',
                auditStatus: 'pending',
            }),
        );
    });

    it('should add a row without an account, so it holds for every account of the provider', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.onAddMapping();

        expect(wrapper.vm.mappings[0].gatewayId).toBeUndefined();
    });

    it('should remove a row', async () => {
        ({ wrapper, template } = await createWrapper({
            mappings: [
                {
                    id: 'mapping-1',
                    templateId: 'template-1',
                    technicalName: 'aliyun',
                    gatewayId: null,
                    templateCode: 'SMS_1',
                    signName: null,
                    auditStatus: 'pending',
                },
            ],
        }));

        wrapper.vm.onRemoveMapping(wrapper.vm.mappings[0]);

        expect(template.mappings).toHaveLength(0);
    });

    it('should ask for a template id only from a provider that reports templates', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.needsTemplateCode({ technicalName: 'aliyun' })).toBe(true);
        expect(wrapper.vm.needsTemplateCode({ technicalName: 'null' })).toBe(false);
        expect(wrapper.vm.needsTemplateCode({ technicalName: 'unknown' })).toBe(false);
    });

    it('should offer every provider of the server and label the accounts with their provider', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.providerOptions).toEqual([
            expect.objectContaining({ value: 'aliyun' }),
            expect.objectContaining({ value: 'null' }),
        ]);
        expect(wrapper.vm.gatewayOptions).toHaveLength(1);
        expect(wrapper.vm.gatewayOptions[0].value).toBe('gateway-1');
        expect(wrapper.vm.gatewayOptions[0].label).toContain('main');
        expect(wrapper.vm.auditStatusOptions.map((option) => option.value)).toEqual([
            'pending',
            'approved',
            'rejected',
            'suspended',
        ]);
    });
});
