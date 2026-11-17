/** @sw-package framework */
import WorkflowTemplateService from 'src/module/sw-workflow/service/workflow-template.service';
import WorkflowReleaseActionService from 'src/module/sw-workflow/service/workflow-release-action.service';
import initialize from './payment-transfer-workflow.init';
import en from 'src/module/sw-finance/snippet/en.json';
import zh from 'src/module/sw-finance/snippet/zh.json';

describe('finance workflow application startup', () => {
    it('contributes through registries already resolved by workflow module initialization', () => {
        const registry = new WorkflowTemplateService();
        const actions = new WorkflowReleaseActionService();

        /** @private */
        Shopwell.Service().register('workflowTemplateService', () => registry);
        /** @private */
        Shopwell.Service().register('workflowReleaseActionService', () => actions);
        Shopwell.Service('workflowTemplateService');
        Shopwell.Service('workflowReleaseActionService');

        initialize();

        expect(registry.all()).toHaveLength(1);
        expect(actions.get('payment:transfer')).toBe('sw-finance-transfer-workflow-start');
        const template = registry.get('core:payment-transfer')!;
        expect(template.name).toBe('core:payment-transfer');
        expect(template.available([])).toBe(false);
        for (const snippets of [en, zh]) {
            const labels: Record<string, string> = snippets['sw-finance'].transfer.workflowTemplate;
            const document = template.create([], { translate: (key) => labels[key.split('.').pop()!] });
            expect(document.nodes.find((node) => node.id === 'department')!.config).toMatchObject({
                label: labels.department,
                assignee: { source: 'core:positions', config: { positionIds: [] } },
            });
            expect(document.nodes[0].config).toHaveProperty(
                'formFields',
                expect.arrayContaining([expect.objectContaining({ key: 'reason', label: labels.reason })]),
            );
        }
    });
});
