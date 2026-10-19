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
        confirm: jest.fn(() => Promise.resolve(answered)),
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
                'sw-data-grid': true,
                'sw-context-menu-item': true,
                'sw-settings-sms-template-submission-modal': true,
                'mt-select': true,
                'mt-empty-state': true,
                'mt-text-field': true,
                'mt-textarea': true,
                'mt-button': true,
                'mt-modal-root': {
                    template: '<div class="mt-modal-root"><slot></slot></div>',
                    props: ['isOpen'],
                },
                'mt-modal': {
                    template: '<div class="mt-modal"><slot></slot><slot name="footer"></slot></div>',
                    props: ['title', 'width'],
                },
                'mt-modal-close': true,
                'mt-modal-action': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, template, repository, auditService, providerService };
}

describe('src/module/sw-settings-sms/component/sw-settings-sms-template-mapping-grid', () => {
    let wrapper;
    let template;
    let auditService;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should list the rows of the template', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping({ auditStatus: 'approved' })] }));

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
                technicalName: 'reports-itself',
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
        ({ wrapper, template } = await createWrapper({ mappings: [mapping()] }));

        wrapper.vm.onRemoveMapping(wrapper.vm.mappings[0]);

        expect(template.mappings).toHaveLength(0);
    });

    it('should ask for a template id only from a provider that reports templates', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.needsTemplateCode({ technicalName: 'reports-itself' })).toBe(true);
        expect(wrapper.vm.needsTemplateCode({ technicalName: 'free-text' })).toBe(false);
        expect(wrapper.vm.needsTemplateCode({ technicalName: 'unknown' })).toBe(false);
    });

    it('should offer every provider of the server and label the accounts with their provider', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.providerOptions.map((option) => option.value)).toEqual([
            'reports-itself',
            'console-only',
            'free-text',
        ]);
        expect(wrapper.vm.gatewayOptions).toHaveLength(1);
        expect(wrapper.vm.gatewayOptions[0].value).toBe('gateway-1');
        expect(wrapper.vm.gatewayOptions[0].label).toContain('main');
    });

    it('should show the audit state the server answered instead of offering a list of states', async () => {
        ({ wrapper } = await createWrapper({
            mappings: [mapping({ auditStatus: 'rejected' })],
        }));

        expect(wrapper.vm.auditStatusLabel('rejected')).toBe('sms.approval.status.rejected');
        expect(wrapper.vm.auditStatusLabel(null)).toBe('—');
        // nothing to type the state with any more
        expect(wrapper.vm.auditStatusOptions).toBeUndefined();
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

        const row = wrapper.vm.mappings[0];

        expect(wrapper.vm.statusCodeOf(row)).toBe('2');
        expect(wrapper.vm.statusMessageOf(row)).toBe('The body names a forbidden word.');
        expect(wrapper.vm.lastSyncedAtOf(row)).toBe('2026-10-19 10:00:00.000');
    });

    it('should show nothing for a row the provider never answered for', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping({ customFields: null })] }));

        const row = wrapper.vm.mappings[0];

        expect(wrapper.vm.statusCodeOf(row)).toBeNull();
        expect(wrapper.vm.statusMessageOf(row)).toBeNull();
        expect(wrapper.vm.lastSyncedAtOf(row)).toBeNull();
    });

    it('should offer the three steps only where the provider of the row declares them', async () => {
        ({ wrapper } = await createWrapper());

        const reported = { technicalName: 'reports-itself' };
        const consoleOnly = { technicalName: 'console-only' };
        const freeText = { technicalName: 'free-text' };

        expect(wrapper.vm.canSubmit(reported)).toBe(true);
        expect(wrapper.vm.canReadStatus(reported)).toBe(true);
        // a state the shop can read itself must not be overridable by hand
        expect(wrapper.vm.canConfirmStatus(reported)).toBe(false);

        expect(wrapper.vm.canSubmit(consoleOnly)).toBe(false);
        expect(wrapper.vm.canReadStatus(consoleOnly)).toBe(false);
        expect(wrapper.vm.canConfirmStatus(consoleOnly)).toBe(true);

        // nothing to audit where no template has to be approved
        expect(wrapper.vm.canConfirmStatus(freeText)).toBe(false);
    });

    it('should offer nothing for a provider the server did not describe', async () => {
        ({ wrapper } = await createWrapper());

        const unknown = { technicalName: 'an-extension-that-was-removed' };

        expect(wrapper.vm.canSubmit(unknown)).toBe(false);
        expect(wrapper.vm.canReadStatus(unknown)).toBe(false);
        expect(wrapper.vm.canConfirmStatus(unknown)).toBe(false);
    });

    it('should take the fields of the submission form from the provider of the row', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.onOpenSubmission(mapping());

        expect(wrapper.vm.submissionTarget.id).toBe('mapping-1');
        expect(wrapper.vm.submissionFields.map((field) => field.name)).toEqual(['templateName', 'templateContent']);
    });

    it('should close the submission and read the template again after a report', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.onOpenSubmission(mapping());
        wrapper.vm.onSubmissionFinished();

        expect(wrapper.vm.submissionTarget).toBeNull();
        expect(wrapper.emitted('reload')).toEqual([[]]);
    });

    it('should read the state back and reload the row', async () => {
        ({ wrapper, auditService } = await createWrapper({ mappings: [mapping()] }));

        await wrapper.vm.onRefresh(wrapper.vm.mappings[0]);

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
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        await wrapper.vm.onRefresh(wrapper.vm.mappings[0]);

        expect(notification).toHaveBeenCalledWith({
            message: 'The provider cannot be asked for the state of a template.',
        });
        expect(wrapper.emitted('reload')).toEqual([[]]);
    });

    it('should show the message of a request that never reached the server', async () => {
        ({ wrapper } = await createWrapper({ mappings: [mapping()], serviceThrows: true }));
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        await wrapper.vm.onRefresh(wrapper.vm.mappings[0]);

        expect(notification).toHaveBeenCalledWith({ message: 'Network Error' });
        expect(wrapper.vm.busyMappingId).toBeNull();
    });

    it('should offer the states an operator may record, with the labels of the server', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.onOpenConfirmation(mapping());
        await flushPromises();

        expect(wrapper.vm.confirmationStatusOptions).toEqual([
            { value: 'approved', label: 'sms.approval.status.approved' },
            { value: 'rejected', label: 'sms.approval.status.rejected' },
            { value: 'suspended', label: 'sms.approval.status.suspended' },
        ]);
    });

    it('should keep the state the row already has when it may be recorded', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.onOpenConfirmation(mapping({ auditStatus: 'suspended' }));
        expect(wrapper.vm.confirmationStatus).toBe('suspended');

        wrapper.vm.onOpenConfirmation(mapping({ auditStatus: 'pending' }));
        expect(wrapper.vm.confirmationStatus).toBe('');
    });

    it('should not record a state without a note', async () => {
        ({ wrapper, auditService } = await createWrapper());
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        wrapper.vm.onOpenConfirmation(mapping({ auditStatus: 'approved' }));
        wrapper.vm.onConfirm();

        expect(notification).toHaveBeenCalled();
        expect(auditService.confirm).not.toHaveBeenCalled();
    });

    it('should record the state with its note and reload the row', async () => {
        ({ wrapper, auditService } = await createWrapper());
        await flushPromises();

        wrapper.vm.onOpenConfirmation(mapping({ auditStatus: 'approved' }));
        wrapper.vm.confirmationNote = 'read at the console';
        await wrapper.vm.onConfirm();
        await flushPromises();

        expect(auditService.confirm).toHaveBeenCalledWith('mapping-1', 'approved', 'read at the console');
        expect(wrapper.vm.confirmationTarget).toBeNull();
        expect(wrapper.emitted('reload')).toEqual([[]]);
    });

    it('should keep the confirmation open while the recorded state was refused', async () => {
        ({ wrapper } = await createWrapper({
            answered: {
                status: null,
                templateCode: null,
                errorCode: 'SMS__TEMPLATE_AUDIT_CONFIRMATION_INVALID',
                errorMessage: 'A note is required.',
            },
        }));

        wrapper.vm.onOpenConfirmation(mapping({ auditStatus: 'approved' }));
        wrapper.vm.confirmationNote = 'x';
        await wrapper.vm.onConfirm();

        expect(wrapper.vm.confirmationTarget).not.toBeNull();
        expect(wrapper.emitted('reload')).toEqual([[]]);
    });

    it('should not close the confirmation while the state is still being recorded', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.onOpenConfirmation(mapping({ auditStatus: 'approved' }));
        wrapper.vm.isConfirming = true;
        wrapper.vm.onCloseConfirmation();

        expect(wrapper.vm.confirmationTarget).not.toBeNull();
    });

    it('should close the confirmation when it is dismissed', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.onOpenConfirmation(mapping({ auditStatus: 'approved' }));
        wrapper.vm.onConfirmationModalChange(false);

        expect(wrapper.vm.confirmationTarget).toBeNull();
        expect(wrapper.vm.confirmationStatus).toBe('');
        expect(wrapper.vm.confirmationNote).toBe('');
    });
});
