/** @sw-package framework */
import type { CanvasConnection, CanvasDocument, CanvasNode } from '../canvas-editor/canvas-document.types';

/** @public */
export interface WorkflowNode extends CanvasNode {
    type: string;
    typeVersion: number;
    config: Record<string, unknown> | [];
}

/** @public */
export interface WorkflowConnection extends CanvasConnection {
    sourcePort: string;
    targetPort: string;
}

/** @public */
export interface WorkflowDocument extends CanvasDocument {
    schemaVersion: 1;
    policy: string;
    nodes: WorkflowNode[];
    connections: WorkflowConnection[];
}

/** @public */
export interface WorkflowNodeTypeSpecification {
    name: string;
    version: number;
    kind: 'start' | 'activity' | 'end';
    inputPorts: string[];
    outputPorts: string[];
}

/** @public */
export interface WorkflowViolation {
    code: string;
    path: string;
    nodeId: string | null;
    messageKey?: string;
}
