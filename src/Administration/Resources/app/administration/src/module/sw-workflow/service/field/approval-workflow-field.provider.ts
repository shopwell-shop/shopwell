/** @sw-package framework */
import { WORKFLOW_NODE_TYPES, WORKFLOW_INPUT_TYPES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowFieldProvider } from 'src/core/workflow/workflow-studio.types';

/** Only the approval extension supplies its implicit default explanation field. @private */
const approvalWorkflowFieldProvider: WorkflowFieldProvider = (document) => {
    const start = document.nodes.find((node) => node.type === WORKFLOW_NODE_TYPES.START && node.typeVersion === 1);

    if (
        !start ||
        'formFields' in start.config ||
        !('inputType' in start.config) ||
        start.config.inputType !== WORKFLOW_INPUT_TYPES.APPROVAL_REQUEST
    ) {
        return [];
    }

    return [{ path: 'request.reason', label: { snippet: 'sw-workflow.inputs.reason' }, type: 'text' }];
};

/** @private */
export default approvalWorkflowFieldProvider;
