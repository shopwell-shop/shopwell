/** @sw-package framework */
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import StructuredWorkflowEditingStrategy from './structured-workflow-editing.strategy';

/** @private */
export default class ApprovalWorkflowEditingStrategy extends StructuredWorkflowEditingStrategy {
    insert(
        document: WorkflowDocument,
        type: WorkflowNodeTypeSpecification,
        connectionId: string,
        id: string,
    ): WorkflowDocument {
        const next = super.insert(document, type, connectionId, id);
        const node = next.nodes.find((item) => item.id === id);
        if (node)
            node.config = {
                assignee: { source: 'core:user', config: { userId: null } },
                approval: { mode: 'core:single', config: {} },
            };
        return next;
    }
}
