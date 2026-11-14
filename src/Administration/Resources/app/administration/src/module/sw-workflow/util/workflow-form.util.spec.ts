/** @sw-package framework */
import { reactive } from 'vue';
import { workflowFormFields } from './workflow-form.util';

describe('workflow form fields', () => {
    it('only supplies the reason default when the schema is absent', () => {
        expect(workflowFormFields({})).toEqual([{ key: 'reason', label: '申请事由', type: 'text', required: false }]);
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
