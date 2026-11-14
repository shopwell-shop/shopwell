/** @sw-package framework */
import type { WorkflowDocument } from './workflow.types';

/** @private */
export function createWorkflowDraft(): WorkflowDocument {
    return { schemaVersion: 1, policy: 'core:structured', nodes: [], connections: [] };
}
