/** @sw-package framework */
import { reactive } from 'vue';
import { workflowFormFields } from './workflow-form.util';

describe('workflow form fields', () => {
    it('reads only explicit authored fields', () => {
        expect(workflowFormFields({})).toEqual([]);
        expect(workflowFormFields({ formFields: [] })).toEqual([]);
    });
    it('clones reactive form definitions and choices without mutating the draft', () => {
        const config = reactive({
            formFields: [
                {
                    key: 'category',
                    label: 'Category',
                    type: 'select',
                    required: true,
                    options: [{ value: 'travel', label: 'Travel' }],
                },
            ],
        });
        const fields = workflowFormFields(config);
        fields[0].label = 'Edited';
        fields[0].options![0].label = 'Edited';
        expect(config.formFields[0].label).toBe('Category');
        expect(config.formFields[0].options[0].label).toBe('Travel');
    });
});

describe('authored form labels', () => {
    it('preserves authored labels and choices after the interface language changes', () => {
        const config = { formFields: [{ key: 'reason', label: 'Edited reason', type: 'text', required: false }] };
        expect(workflowFormFields(config)[0].label).toBe('Edited reason');
    });
});
