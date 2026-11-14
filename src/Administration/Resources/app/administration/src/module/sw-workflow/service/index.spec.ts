/** @sw-package framework */
import './extension/approval-workflow.registration';
import './index';

describe('request form extension service', () => {
    it('exposes configured fields and the default form to business modules', () => {
        const service = Shopwell.Service('workflowRequestFormService');
        expect(service.getFields({}).map((field) => field.key)).toEqual(['reason']);
        expect(service.getFields({ formFields: [] })).toEqual([]);
        expect(
            service.getFields({ formFields: [{ key: 'amount', label: 'Amount', type: 'amount', required: true }] }),
        ).toEqual([{ key: 'amount', label: 'Amount', type: 'amount', required: true }]);
    });
});
