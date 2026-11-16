/** @sw-package framework */
import type { WorkflowTemplate } from 'src/core/workflow/workflow-studio.types';
import en from '../snippet/en.json';
import zh from '../snippet/zh.json';

describe('finance workflow extension', () => {
    it('contributes a transfer template through the existing designer service', async () => {
        const decorate = jest.spyOn(Shopwell.Application, 'addServiceProviderDecorator');
        await import('./payment-transfer-workflow.registration');
        const call = decorate.mock.calls.find(([name]) => name === 'workflowTemplateService');
        const decorator = call?.[1];
        expect(decorator).toBeDefined();
        if (!decorator) throw new Error('Finance workflow decorator was not registered');
        const registry = { register: jest.fn<() => void, [WorkflowTemplate]>() };
        expect(decorator(registry)).toBe(registry);
        const template = registry.register.mock.calls[0][0];
        expect(template.name).toBe('core:payment-transfer');
        expect(template.available([])).toBe(false);
        const config = template.create([], { translate: (key) => key }).nodes[0].config as { formFields: unknown[] };
        expect(config.formFields).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ key: 'recipient', type: 'text', required: true }),
                expect.objectContaining({ key: 'paymentAccount', type: 'text', required: true }),
            ]),
        );
        for (const snippets of [en, zh]) {
            const labels: Record<string, string> = snippets['sw-finance'].transfer.workflowTemplate;
            const document = template.create([], { translate: (key) => labels[key.split('.').pop()!] });
            expect(document.nodes.find((node) => node.id === 'finance')!.config).toMatchObject({ label: labels.finance });
            expect(document.nodes[0].config).toHaveProperty(
                'formFields',
                expect.arrayContaining([expect.objectContaining({ key: 'reason', label: labels.reason })]),
            );
        }
        decorate.mockRestore();
    });
});
