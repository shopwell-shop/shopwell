/** @sw-package framework */
import type { WorkflowDocument } from 'src/core/workflow/workflow.types';

/** @private */
export function createStructuredWorkflowDraft(): WorkflowDocument {
    return {
        schemaVersion: 1,
        policy: 'core:structured',
        nodes: [
            { id: 'start', type: 'core:start', typeVersion: 1, config: {} },
            { id: 'end', type: 'core:end', typeVersion: 1, config: {} },
        ],
        connections: [{ id: 'start-end', source: 'start', sourcePort: 'out', target: 'end', targetPort: 'in' }],
    };
}
