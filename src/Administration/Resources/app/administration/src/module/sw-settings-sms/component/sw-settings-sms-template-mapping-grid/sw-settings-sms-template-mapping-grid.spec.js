/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import EntityCollection from '@shopwell-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';

const submissionFields = [
    {
        name: 'templateName',
        type: 'string',
        required: true,
        labelSnippet: 'sms.submission.example.templateName',
        prefill: 'templateName',
    },
    {
        name: 'templateContent',
        type: 'text',
        required: true,
        labelSnippet: 'sms.submission.example.templateContent',
        prefill: 'content',
    },
];

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
            submissionFields,
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

const gateways = [
    { id: 'gateway-1', technicalName: 'reports-itself', label: 'main' },
];

const approvalStatuses = [
    { value: 'pending', labelSnippet: 'sms.approval.status.pending' },
    { value: 'approved', labelSnippet: 'sms.approval.status.approved' },
    { value: 'rejected', labelSnippet: 'sms.approval.status.rejected' },
    { value: 'suspended', labelSnippet: 'sms.approval.status.suspended' },
    { value: 'unknown', labelSnippet: 'sms.approval.status.unknown' },
];

function mapping(overrides = {}) {
    return {
        id: 'mapping-1',
        templateId: 'template-1',
        technicalName: 'reports-itself',
        gatewayId: 'gateway-1',
        templateCode: 'SMS_1',
        signName: 'Shop',
        auditStatus: 'pending',
        ...overrides,
    };
}

async function createWrapper({
    mappings = [],
    templateId = 'template-1',
    answered = { status: 'approved', templateCode: 'SMS_1', errorCode: null, errorMessage: null },
    serviceThrows = false,
    pendingConfirm = null,
} = {}) {
    const created = [];
    const repository = {
        create: jest.fn(() => {
            const entity = { id: `mapping-${created.length + 1}` };
            created.push(entity);

            return entity;
        }),
    };

    const auditService = {
        submit: jest.fn(() => Promise.resolve(answered)),
        refresh: jest.fn(() => {
            if (serviceThrows) {
                return Promise.reject(new Error('Network Error'));
            }

            return Promise.resolve(answered);
        }),
        confirm: jest.fn(() => pendingConfirm ?? Promise.resolve(answered)),
    };

    const providerService = {
        list: jest.fn(() => Promise.resolve(providers)),
        approvalStatuses: jest.fn(() => Promise.resolve(approvalStatuses)),
    };

    // The template detail keeps the item in its reactive state, so the grid has to see the same
    // thing here - a plain array would swallow the added row instead of reporting it back.
    const template = reactive({
        id: templateId,
        technicalName: 'customer.otp.login',
        scene: 'otp',
        content: 'Your code is {{ code }}',
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
                smsGatewayProviderService: providerService,
                smsTemplateAuditService: auditService,
            },
            stubs: {
                'sw-data-grid': {
                    name: 'sw-data-grid',
                    props: ['dataSource', 'columns'],
                    template: `<div><article v-for="item in dataSource" :key="item.id" :data-row="item.id">
                        <slot v-for="(_, name) in $slots" :name="name" :item="item"/>
                    </article></div>`,
                },
                'sw-context-menu-item': { props: ['disabled'], template: '<button :disabled="disabled"><slot/></button>' },
                'sw-settings-sms-template-submission-modal': {
                    name: 'sw-settings-sms-template-submission-modal',
                    props: ['mapping', 'submissionFields'],
                    template: `<div><button class="finish-submission" @click="$emit('submitted')">Submitted</button></div>`,
                },
                'mt-select': {
                    name: 'mt-select',
                    props: [
                        'modelValue',
                        'options',
                        'name',
                        'disabled',
                    ],
                    template: `<select :name="name" :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)">
                        <option value="">Choose</option><option v-for="option in options" :value="option.value">{{ option.label }}</option></select>`,
                },
                'mt-empty-state': true,
                'mt-text-field': {
                    props: [
                        'modelValue',
                        'name',
                        'required',
                        'disabled',
                    ],
                    template: `<input :name="name" :value="modelValue" :required="required" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
                'mt-textarea': {
                    props: ['modelValue', 'name', 'disabled'],
                    template: `<textarea :name="name" :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
                'mt-button': { props: ['disabled'], template: '<button :disabled="disabled"><slot/></button>' },
                'mt-modal-root': {
                    template: `<div class="mt-modal-root"><button class="dismiss-modal" @click="$emit('change', false)">Dismiss</button><slot/></div>`,
                    props: ['isOpen'],
                },
                'mt-modal': {
                    template: '<div class="mt-modal"><slot></slot><slot name="footer"></slot></div>',
                    props: ['title', 'width'],
                },
                'mt-modal-close': true,
                'mt-modal-action': {
                    props: ['disabled', 'isLoading'],
                    template:
                        '<button class="confirm-audit" :disabled="disabled" :data-loading="isLoading"><slot/></button>',
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, template, repository, auditService, providerService };
}

function row(wrapper, id = 'mapping-1') {
    return wrapper.get(`[data-row="${id}"]`);
}

async function openConfirmation(wrapper) {
    await row(wrapper).get('.sw-settings-sms-template-mapping-grid__confirm').trigger('click');
    await flushPromises();
}

async function confirm(wrapper) {
    await wrapper.get('.confirm-audit').trigger('click');
    await flushPromises();
}

describe('src/module/sw-settings-sms/component/sw-settings-sms-template-mapping-grid', () => {
    let wrapper;
    let template;
    let auditService;

    afterEach(() => {
        wrapper?.unmount();
        jest.restoreAllMocks();
    });

    it('should list the rows of the template', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping({ auditStatus: 'approved' })] }));
        expect(wrapper.findAll('[data-row]')).toHaveLength(1);
        expect(row(wrapper).get('[name="templateCode"]').element.value).toBe('SMS_1');
    });

    it('should add a row that holds for the first provider of the server', async () => {
        ({ wrapper, template } = await createWrapper());
        await wrapper.get('.sw-settings-sms-template-mapping-grid__add').trigger('click');
        expect(template.mappings).toHaveLength(1);
        expect(template.mappings.first()).toEqual(
            expect.objectContaining({
                id: 'mapping-1',
                templateId: 'template-1',
                technicalName: 'reports-itself',
                auditStatus: 'pending',
            }),
        );
        expect(row(wrapper).get('[name="technicalName"]').element.value).toBe('reports-itself');
    });

    it('should add a row without an account, so it holds for every account of the provider', async () => {
        ({ wrapper, template } = await createWrapper());
        await wrapper.get('.sw-settings-sms-template-mapping-grid__add').trigger('click');
        expect(template.mappings.first().gatewayId).toBeUndefined();
    });

    it('should remove a row', async () => {
        ({ wrapper, template } = await createWrapper({ mappings: [mapping()] }));
        await row(wrapper).get('.sw-settings-sms-template-mapping-grid__remove').trigger('click');
        expect(template.mappings).toHaveLength(0);
        expect(wrapper.findAll('[data-row]')).toHaveLength(0);
    });

    it.each([
        ['reports-itself', true],
        ['free-text', false],
        ['unknown', false],
    ])('should require a template code for %s (%s)', async (technicalName, required) => {
        ({ wrapper } = await createWrapper({ mappings: [mapping({ technicalName })] }));
        expect(row(wrapper).get('[name="templateCode"]').attributes('required') !== undefined).toBe(required);
    });

    it('should offer every provider of the server and label the accounts with their provider', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping()] }));
        const providerOptions = row(wrapper).getComponent('[name="technicalName"]').props('options');
        const gatewayOptions = row(wrapper).getComponent('[name="gatewayId"]').props('options');
        expect(providerOptions.map((option) => option.value)).toEqual(['reports-itself', 'console-only', 'free-text']);
        expect(gatewayOptions).toHaveLength(1);
        expect(gatewayOptions[0].value).toBe('gateway-1');
        expect(gatewayOptions[0].label).toContain('main');
    });

    it.each([
        ['rejected', 'sms.approval.status.rejected'],
        [null, '—'],
    ])('should render the answered state %s without an editable state field', async (auditStatus, label) => {
        ({ wrapper } = await createWrapper({ mappings: [mapping({ auditStatus })] }));
        expect(row(wrapper).get('.sw-settings-sms-template-mapping-grid__audit-status').text()).toBe(label);
        expect(row(wrapper).find('[name="auditStatus"]').exists()).toBe(false);
    });

    it('should show the answer of the provider next to the state', async () => {
        ({ wrapper } = await createWrapper({
            mappings: [
                mapping({
                    auditStatus: 'rejected',
                    customFields: {
                        sms_provider_status_code: '2',
                        sms_provider_status_message: 'The body names a forbidden word.',
                        sms_last_synced_at: '2026-10-19 10:00:00.000',
                        sms_provider_template_name: 'login',
                    },
                }),
            ],
        }));
        expect(row(wrapper).get('.sw-settings-sms-template-mapping-grid__audit-code').text()).toContain('2');
        expect(row(wrapper).get('.sw-settings-sms-template-mapping-grid__audit-message').text()).toBe(
            'The body names a forbidden word.',
        );
        expect(row(wrapper).get('.sw-settings-sms-template-mapping-grid__audit-last-synced').text()).toContain(
            '2026-10-19 10:00:00.000',
        );
    });

    it('should show nothing for a row the provider never answered for', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping({ customFields: null })] }));
        expect(row(wrapper).find('.sw-settings-sms-template-mapping-grid__audit-code').exists()).toBe(false);
        expect(row(wrapper).find('.sw-settings-sms-template-mapping-grid__audit-message').exists()).toBe(false);
        expect(row(wrapper).find('.sw-settings-sms-template-mapping-grid__audit-last-synced').exists()).toBe(false);
    });

    it.each([
        ['reports-itself', ['submit', 'refresh']],
        ['console-only', ['confirm']],
        ['free-text', []],
        ['an-extension-that-was-removed', []],
    ])('should offer only the audit steps declared by %s', async (technicalName, actions) => {
        ({ wrapper } = await createWrapper({ mappings: [mapping({ technicalName })] }));
        expect(row(wrapper).find('.sw-settings-sms-template-mapping-grid__submit').exists()).toBe(
            actions.includes('submit'),
        );
        expect(row(wrapper).find('.sw-settings-sms-template-mapping-grid__refresh').exists()).toBe(
            actions.includes('refresh'),
        );
        expect(row(wrapper).find('.sw-settings-sms-template-mapping-grid__confirm').exists()).toBe(
            actions.includes('confirm'),
        );
    });

    it('should take the fields of the submission form from the provider of the row', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping()] }));
        await row(wrapper).get('.sw-settings-sms-template-mapping-grid__submit').trigger('click');
        const modal = wrapper.getComponent({ name: 'sw-settings-sms-template-submission-modal' });
        expect(modal.props('mapping').id).toBe('mapping-1');
        expect(modal.props('submissionFields').map((field) => field.name)).toEqual(['templateName', 'templateContent']);
    });

    it('should close the submission and read the template again after a report', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping()] }));
        await row(wrapper).get('.sw-settings-sms-template-mapping-grid__submit').trigger('click');
        await wrapper.get('.finish-submission').trigger('click');
        expect(wrapper.find('.finish-submission').exists()).toBe(false);
        expect(wrapper.emitted('reload')).toEqual([[]]);
    });

    it('should read the state back and reload the row', async () => {
        ({ wrapper, auditService } = await createWrapper({ mappings: [mapping()] }));
        await row(wrapper).get('.sw-settings-sms-template-mapping-grid__refresh').trigger('click');
        await flushPromises();
        expect(auditService.refresh).toHaveBeenCalledWith('mapping-1');
        expect(wrapper.emitted('reload')).toEqual([[]]);
    });

    it('should show the message of a refusal and still reload the row', async () => {
        ({ wrapper } = await createWrapper({
            mappings: [mapping()],
            answered: {
                status: null,
                templateCode: null,
                errorCode: 'SMS__TEMPLATE_STATUS_READ_UNSUPPORTED',
                errorMessage: 'The provider cannot be asked for the state of a template.',
            },
        }));
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        await row(wrapper).get('.sw-settings-sms-template-mapping-grid__refresh').trigger('click');
        await flushPromises();
        expect(notification).toHaveBeenCalledWith(
            expect.objectContaining({ message: 'The provider cannot be asked for the state of a template.' }),
        );
        expect(wrapper.emitted('reload')).toEqual([[]]);
    });

    it('should show the message of a request that never reached the server', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping()], serviceThrows: true }));
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        await row(wrapper).get('.sw-settings-sms-template-mapping-grid__refresh').trigger('click');
        await flushPromises();
        expect(notification).toHaveBeenCalledWith(expect.objectContaining({ message: 'Network Error' }));
        expect(row(wrapper).get('.sw-settings-sms-template-mapping-grid__refresh').attributes('disabled')).toBeUndefined();
    });

    it('should offer the states an operator may record, with the labels of the server', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping({ technicalName: 'console-only' })] }));
        await openConfirmation(wrapper);
        expect(wrapper.getComponent('.sw-settings-sms-template-mapping-grid__confirmation-status').props('options')).toEqual(
            [
                { value: 'approved', label: 'sms.approval.status.approved' },
                { value: 'rejected', label: 'sms.approval.status.rejected' },
                { value: 'suspended', label: 'sms.approval.status.suspended' },
            ],
        );
    });

    it.each([
        ['suspended', 'suspended'],
        ['pending', ''],
    ])('should initialize the confirmation of %s as %s', async (auditStatus, expected) => {
        ({ wrapper } = await createWrapper({ mappings: [mapping({ technicalName: 'console-only', auditStatus })] }));
        await openConfirmation(wrapper);
        expect(wrapper.get('[name="confirmationStatus"]').element.value).toBe(expected);
    });

    it('should not record a state without a note', async () => {
        ({ wrapper, auditService } = await createWrapper({
            mappings: [mapping({ technicalName: 'console-only', auditStatus: 'approved' })],
        }));
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');
        await openConfirmation(wrapper);
        await confirm(wrapper);
        expect(notification).toHaveBeenCalledWith(expect.objectContaining({ variant: 'error' }));
        expect(auditService.confirm).not.toHaveBeenCalled();
    });

    it('should record the state with its note and reload the row', async () => {
        ({ wrapper, auditService } = await createWrapper({
            mappings: [mapping({ technicalName: 'console-only', auditStatus: 'approved' })],
        }));
        await openConfirmation(wrapper);
        await wrapper.get('[name="confirmationNote"]').setValue('read at the console');
        await confirm(wrapper);
        expect(auditService.confirm).toHaveBeenCalledWith('mapping-1', 'approved', 'read at the console');
        expect(wrapper.find('.confirm-audit').exists()).toBe(false);
        expect(wrapper.emitted('reload')).toEqual([[]]);
    });

    it('should keep the confirmation open while the recorded state was refused', async () => {
        ({ wrapper } = await createWrapper({
            mappings: [mapping({ technicalName: 'console-only', auditStatus: 'approved' })],
            answered: {
                status: null,
                templateCode: null,
                errorCode: 'SMS__TEMPLATE_AUDIT_CONFIRMATION_INVALID',
                errorMessage: 'A note is required.',
            },
        }));
        await openConfirmation(wrapper);
        await wrapper.get('[name="confirmationNote"]').setValue('x');
        await confirm(wrapper);
        expect(wrapper.find('.confirm-audit').exists()).toBe(true);
        expect(wrapper.emitted('reload')).toEqual([[]]);
    });

    it('should not close the confirmation while the state is still being recorded', async () => {
        let finish;
        const pendingConfirm = new Promise((resolve) => {
            finish = resolve;
        });
        ({ wrapper } = await createWrapper({
            pendingConfirm,
            mappings: [mapping({ technicalName: 'console-only', auditStatus: 'approved' })],
        }));
        await openConfirmation(wrapper);
        await wrapper.get('[name="confirmationNote"]').setValue('checked');
        await wrapper.get('.confirm-audit').trigger('click');
        expect(wrapper.get('.confirm-audit').attributes('disabled')).toBeDefined();
        await wrapper.get('.dismiss-modal').trigger('click');
        expect(wrapper.find('.confirm-audit').exists()).toBe(true);
        finish({ status: 'approved', templateCode: null, errorCode: null, errorMessage: null });
        await flushPromises();
    });

    it('should close and clear the confirmation when it is dismissed', async () => {
        ({ wrapper } = await createWrapper({
            mappings: [mapping({ technicalName: 'console-only', auditStatus: 'approved' })],
        }));
        await openConfirmation(wrapper);
        await wrapper.get('[name="confirmationNote"]').setValue('not saved');
        await wrapper.get('.dismiss-modal').trigger('click');
        expect(wrapper.find('.confirm-audit').exists()).toBe(false);
        await openConfirmation(wrapper);
        expect(wrapper.get('[name="confirmationNote"]').element.value).toBe('');
    });
});
