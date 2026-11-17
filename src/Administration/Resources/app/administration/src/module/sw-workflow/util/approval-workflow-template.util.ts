/** @sw-package framework */
import { WORKFLOW_NODE_TYPES, WORKFLOW_INPUT_TYPES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import type { WorkflowTemplateContext } from 'src/core/workflow/workflow-studio.types';
import ApprovalWorkflowEditingStrategy from '../service/editing/approval-workflow-editing.strategy';
import { createStructuredWorkflowDraft } from './workflow-template.util';

/** @private */
export function createApprovalWorkflow(
    types: WorkflowNodeTypeSpecification[],
    context: WorkflowTemplateContext,
): WorkflowDocument {
    const draft = createStructuredWorkflowDraft();
    const approval = types.find((type) => type.name === WORKFLOW_NODE_TYPES.APPROVAL && type.version === 1);

    if (approval) {
        draft.nodes[0].config = {
            inputType: WORKFLOW_INPUT_TYPES.APPROVAL_REQUEST,
            formFields: [
                { key: 'reason', label: context.translate('sw-workflow.inputs.reason'), type: 'text', required: false },
            ],
        };
    }

    return approval ? new ApprovalWorkflowEditingStrategy().insert(draft, approval, 'start-end', 'approval') : draft;
}
