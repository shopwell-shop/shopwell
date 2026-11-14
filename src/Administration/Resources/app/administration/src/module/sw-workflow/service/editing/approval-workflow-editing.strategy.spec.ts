/** @sw-package framework */
import '../extension/approval-workflow.registration';
import '../../service';
import { createStructuredWorkflowDraft } from '../../util/workflow-template.util';
import { createApprovalWorkflow } from '../../util/approval-workflow-template.util';
const type = {
    name: 'core:approval',
    version: 1,
    kind: 'activity' as const,
    inputPorts: ['in'],
    outputPorts: ['approved', 'rejected'],
};
describe('approval editing defaults', () => {
    it('initializes the same single-user settings for inserted nodes and the default template', () => {
        const strategy = Shopwell.Service('workflowNodeEditingService').get(type.name, 1);
        const inserted = strategy.insert(createStructuredWorkflowDraft(), type, 'start-end', 'approval');
        const config = {
            assignee: { source: 'core:user', config: { userId: null } },
            approval: { mode: 'core:single', config: {} },
        };
        expect(inserted.nodes.find((node) => node.id === 'approval')?.config).toEqual(config);
        expect(createApprovalWorkflow([type]).nodes.find((node) => node.id === 'approval')?.config).toEqual(config);
        expect(inserted.connections.filter((edge) => edge.source === 'approval').map((edge) => edge.sourcePort)).toEqual([
            'approved',
            'rejected',
        ]);
    });
});
