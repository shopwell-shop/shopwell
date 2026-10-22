/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

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
    {
        name: 'templateType',
        type: 'select',
        required: true,
        labelSnippet: 'sms.submission.example.templateType',
        prefill: 'scene',
        options: [
            { value: 'otp', labelSnippet: 'sms.submission.example.templateType.code' },
            { value: 'transaction', labelSnippet: 'sms.submission.example.templateType.notice' },
        ],
    },
    {
        name: 'remark',
        type: 'string',
        required: false,
        labelSnippet: 'sms.submission.example.remark',
        default: 'none',
    },
];

const template = {
    id: 'template-1',
    technicalName: 'customer.otp.login',
    scene: 'otp',
    content: 'Your code is {{ code }}',
    translations: [
        { languageId: 'language-other', content: 'Ihr Code lautet {{ code }}' },
        { languageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b', content: 'Der Code ist {{ code }}' },
    ],
};

async function createWrapper({
    fields = submissionFields,
    templateEntity = template,
    submitAnswer = { status: 'pending', templateCode: 'SMS_1', errorCode: null, errorMessage: null },
    submitFails = false,
} = {}) {
    const auditService = {
        submit: jest.fn(() => {
            if (submitFails) {
                return Promise.reject(new Error('Network Error'));
            }

            return Promise.resolve(submitAnswer);
        }),
    };

    const wrapper = mount(await wrapTestComponent('sw-settings-sms-template-submission-modal', { sync: true }), {
        props: {
            mapping: { id: 'mapping-1', technicalName: 'example' },
            template: templateEntity,
            submissionFields: fields,
        },
        global: {
            provide: {
                smsTemplateAuditService: auditService,
            },
            stubs: {
                'mt-modal-root': {
                    template: '<div class="mt-modal-root"><slot></slot></div>',
                    props: ['isOpen'],
                },
                'mt-modal': {
                    template: '<div class="mt-modal"><slot></slot><slot name="footer"></slot></div>',
                    props: ['title', 'width'],
                },
                'mt-modal-close': true,
                'mt-modal-action': {
                    template: '<button class="mt-modal-action" @click="$emit(\'click\')"></button>',
                },
                'mt-select': true,
                'mt-text-field': true,
                'mt-textarea': true,
                'mt-number-field': true,
            },
        },
    });

    await flushPromises();

    return { wrapper, auditService };
}

describe('src/module/sw-settings-sms/component/sw-settings-sms-template-submission-modal', () => {
    let wrapper;
    let auditService;

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount();
        }
    });

    it('should render exactly the fields the provider declares', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.fields.map((field) => field.name)).toEqual([
            'templateName',
            'templateContent',
            'templateType',
            'remark',
        ]);
        expect(wrapper.findAll('.sw-settings-sms-template-submission-modal__field')).toHaveLength(4);
    });

    it('should fill in what the core already knows about the template', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.fieldValues).toEqual({
            templateName: 'customer.otp.login',
            // the body of the default language, not the one the administration is switched to
            templateContent: 'Der Code ist {{ code }}',
            templateType: 'otp',
            remark: 'none',
        });
    });

    it('should fall back to the body the page shows when the default language is not loaded', async () => {
        ({ wrapper } = await createWrapper({
            templateEntity: {
                id: 'template-1',
                technicalName: 'customer.otp.login',
                scene: 'otp',
                content: 'Your code is {{ code }}',
            },
        }));

        expect(wrapper.vm.fieldValues.templateContent).toBe('Your code is {{ code }}');
    });

    it('should offer the options of a select field under their snippet keys', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.vm.optionsOf(submissionFields[2])).toEqual([
            { value: 'otp', label: 'sms.submission.example.templateType.code' },
            { value: 'transaction', label: 'sms.submission.example.templateType.notice' },
        ]);
    });

    it('should not call the server while a required field is empty', async () => {
        ({ wrapper, auditService } = await createWrapper());
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        wrapper.vm.fieldValues.templateName = '   ';
        await wrapper.vm.onSubmit();

        expect(notification).toHaveBeenCalled();
        expect(auditService.submit).not.toHaveBeenCalled();
    });

    it('should submit the values of the form and report the answer', async () => {
        ({ wrapper, auditService } = await createWrapper());

        wrapper.vm.fieldValues.remark = 'checked by hand';
        await wrapper.vm.onSubmit();

        expect(auditService.submit).toHaveBeenCalledWith('mapping-1', {
            templateName: 'customer.otp.login',
            templateContent: 'Der Code ist {{ code }}',
            templateType: 'otp',
            remark: 'checked by hand',
        });
        expect(wrapper.emitted('submitted')).toEqual([
            [{ status: 'pending', templateCode: 'SMS_1', errorCode: null, errorMessage: null }],
        ]);
    });

    it('should show the message of a refused submission and stay open', async () => {
        ({ wrapper } = await createWrapper({
            submitAnswer: {
                status: null,
                templateCode: null,
                errorCode: 'SMS__TEMPLATE_SUBMISSION_UNSUPPORTED',
                errorMessage: 'The provider cannot report a template.',
            },
        }));
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        await wrapper.vm.onSubmit();

        expect(notification).toHaveBeenCalledWith({ message: 'The provider cannot report a template.' });
        expect(wrapper.emitted('submitted')).toBeUndefined();
        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('should show the message of a request that never reached the server', async () => {
        ({ wrapper } = await createWrapper({ submitFails: true }));
        const notification = jest.spyOn(wrapper.vm, 'createNotificationError');

        await wrapper.vm.onSubmit();

        expect(notification).toHaveBeenCalledWith({ message: 'Network Error' });
        expect(wrapper.emitted('submitted')).toBeUndefined();
    });

    it('should keep the form locked while the call runs', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.isSubmitting = true;
        wrapper.vm.onClose();

        // a second click must not close a modal whose report is in flight
        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('should close when the modal is dismissed', async () => {
        ({ wrapper } = await createWrapper());

        wrapper.vm.onModalChange(false);

        expect(wrapper.emitted('modal-close')).toEqual([[]]);
    });
});
