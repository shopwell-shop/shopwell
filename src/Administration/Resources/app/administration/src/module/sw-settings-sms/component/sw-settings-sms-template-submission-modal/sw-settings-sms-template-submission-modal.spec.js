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
    pendingSubmit = null,
} = {}) {
    const auditService = {
        submit: jest.fn(() => {
            if (submitFails) {
                return Promise.reject(new Error('Network Error'));
            }

            return pendingSubmit ?? Promise.resolve(submitAnswer);
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
                    template: `<div class="mt-modal-root"><button class="dismiss-modal" @click="$emit('change', false)">Dismiss</button><slot/></div>`,
                    props: ['isOpen'],
                },
                'mt-modal': {
                    template: '<div class="mt-modal"><slot></slot><slot name="footer"></slot></div>',
                    props: ['title', 'width'],
                },
                'mt-modal-close': true,
                'mt-modal-action': {
                    emits: ['click'],
                    props: ['disabled', 'isLoading'],
                    template:
                        '<button class="mt-modal-action" :disabled="disabled" :data-loading="isLoading" @click="$emit(\'click\')"></button>',
                },
                'mt-select': {
                    name: 'mt-select',
                    props: [
                        'modelValue',
                        'name',
                        'options',
                        'disabled',
                    ],
                    template: `<select :name="name" :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)"><option v-for="option in options" :value="option.value">{{ option.label }}</option></select>`,
                },
                'mt-text-field': {
                    props: [
                        'modelValue',
                        'name',
                        'options',
                        'disabled',
                    ],
                    template: `<input :name="name" :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
                'mt-textarea': {
                    props: [
                        'modelValue',
                        'name',
                        'options',
                        'disabled',
                    ],
                    template: `<input :name="name" :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
                'mt-number-field': {
                    props: [
                        'modelValue',
                        'name',
                        'options',
                        'disabled',
                    ],
                    template: `<input :name="name" :value="modelValue" :disabled="disabled" @input="$emit('update:modelValue', $event.target.value)"/>`,
                },
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

        expect(wrapper.findAll('input, select').map((field) => field.attributes('name'))).toEqual([
            'templateName',
            'templateContent',
            'templateType',
            'remark',
        ]);
        expect(wrapper.findAll('.sw-settings-sms-template-submission-modal__field')).toHaveLength(4);
    });

    it('should fill in what the core already knows about the template', async () => {
        ({ wrapper } = await createWrapper());

        expect(
            Object.fromEntries(
                wrapper.findAll('input, select').map((field) => [field.attributes('name'), field.element.value]),
            ),
        ).toEqual({
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

        expect(wrapper.get('[name="templateContent"]').element.value).toBe('Your code is {{ code }}');
    });

    it('should offer the options of a select field under their snippet keys', async () => {
        ({ wrapper } = await createWrapper());

        expect(wrapper.getComponent({ name: 'mt-select' }).props('options')).toEqual([
            { value: 'otp', label: 'sms.submission.example.templateType.code' },
            { value: 'transaction', label: 'sms.submission.example.templateType.notice' },
        ]);
    });

    it('should not call the server while a required field is empty', async () => {
        ({ wrapper, auditService } = await createWrapper());
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        await wrapper.get('[name="templateName"]').setValue('   ');
        await wrapper.get('.mt-modal-action').trigger('click');
        await flushPromises();

        expect(notification).toHaveBeenCalled();
        expect(auditService.submit).not.toHaveBeenCalled();
    });

    it('should submit the values of the form and report the answer', async () => {
        ({ wrapper, auditService } = await createWrapper());

        await wrapper.get('[name="remark"]').setValue('checked by hand');
        await wrapper.get('.mt-modal-action').trigger('click');
        await flushPromises();

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
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        await wrapper.get('.mt-modal-action').trigger('click');
        await flushPromises();

        expect(notification).toHaveBeenCalledWith(
            expect.objectContaining({ message: 'The provider cannot report a template.' }),
        );
        expect(wrapper.emitted('submitted')).toBeUndefined();
        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('should show the message of a request that never reached the server', async () => {
        ({ wrapper } = await createWrapper({ submitFails: true }));
        const notification = jest.spyOn(Shopwell.Store.get('notification'), 'createNotification');

        await wrapper.get('.mt-modal-action').trigger('click');
        await flushPromises();

        expect(notification).toHaveBeenCalledWith(expect.objectContaining({ message: 'Network Error' }));
        expect(wrapper.emitted('submitted')).toBeUndefined();
    });

    it('should keep the form locked while the call runs', async () => {
        let finish;
        const pendingSubmit = new Promise((resolve) => {
            finish = resolve;
        });
        ({ wrapper } = await createWrapper({ pendingSubmit }));

        await wrapper.get('.mt-modal-action').trigger('click');
        expect(wrapper.get('.mt-modal-action').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[name="templateName"]').attributes('disabled')).toBeDefined();
        await wrapper.get('.dismiss-modal').trigger('click');

        // a second click must not close a modal whose report is in flight
        expect(wrapper.emitted('modal-close')).toBeUndefined();
        finish({ status: 'pending', templateCode: null, errorCode: null, errorMessage: null });
        await flushPromises();
    });

    it('should close when the modal is dismissed', async () => {
        ({ wrapper } = await createWrapper());

        await wrapper.get('.dismiss-modal').trigger('click');

        expect(wrapper.emitted('modal-close')).toEqual([[]]);
    });
});
