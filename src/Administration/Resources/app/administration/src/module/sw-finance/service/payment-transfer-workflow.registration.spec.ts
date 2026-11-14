/** @sw-package framework */
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';

describe('finance workflow extension', () => {
    it('contributes a transfer template through the existing designer service', async () => {
        const decorate = jest.spyOn(Shopwell.Application, 'addServiceProviderDecorator');
        await import('./payment-transfer-workflow.registration');
        const call = decorate.mock.calls.find(([name]) => name === 'workflowTemplateService');
        const decorator = call?.[1];
        expect(decorator).toBeDefined();
        if (!decorator) throw new Error('Finance workflow decorator was not registered');
        type Template = {
            name: string;
            available: (types: WorkflowNodeTypeSpecification[]) => boolean;
            create: () => WorkflowDocument;
        };
        const registry = { register: jest.fn<void, [Template]>() };
        expect(decorator(registry)).toBe(registry);
        const template = registry.register.mock.calls[0][0];
        expect(template.name).toBe('core:payment-transfer');
        expect(template.available([])).toBe(false);
        const config = template.create().nodes[0].config as { formFields: unknown[] };
        expect(config.formFields).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ key: 'recipient', type: 'text', required: true }),
                expect.objectContaining({ key: 'paymentAccount', type: 'text', required: true }),
            ]),
        );
        decorate.mockRestore();
    });
});
