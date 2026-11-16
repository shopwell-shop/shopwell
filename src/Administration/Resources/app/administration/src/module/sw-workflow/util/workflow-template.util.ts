/** @sw-package framework */
import { WORKFLOW_NODE_TYPES, WORKFLOW_NODE_PORTS } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowDocument } from 'src/core/workflow/workflow.types';

/** @private */
export function createStructuredWorkflowDraft(): WorkflowDocument {
    return {
        schemaVersion: 1,
        policy: 'core:structured',
        nodes: [
            { id: 'start', type: WORKFLOW_NODE_TYPES.START, typeVersion: 1, config: {} },
            { id: 'end', type: WORKFLOW_NODE_TYPES.END, typeVersion: 1, config: {} },
        ],
        connections: [
            {
                id: 'start-end',
                source: 'start',
                sourcePort: WORKFLOW_NODE_PORTS.OUT,
                target: 'end',
                targetPort: WORKFLOW_NODE_PORTS.IN,
            },
        ],
    };
}
