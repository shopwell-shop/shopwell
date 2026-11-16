/** @sw-package framework */
import type { WorkflowExtension } from './workflow-extension.types';
import type { WorkflowDocument, WorkflowNode, WorkflowNodeTypeSpecification, WorkflowViolation } from './workflow.types';

/** Coordinates in the canvas document space, independent of its renderer. @public */
export type WorkflowPoint = { x: number; y: number };

/** @public */
export type WorkflowNodeSize = { width: number; height: number };

/** @public */
export type WorkflowLayoutContext = {
    nodeSizes?: Record<string, WorkflowNodeSize>;
    outputPorts?: Record<string, string[]>;
};

/** @public */
export type WorkflowLayout = (
    document: WorkflowDocument,
    types: WorkflowNodeTypeSpecification[],
    context?: WorkflowLayoutContext,
) => Record<string, WorkflowPoint>;

/** @public */
export type WorkflowBranchLane = { index: number; count: number };

/** @public */
export type WorkflowConnectionRoute = {
    path: string;
    points: WorkflowPoint[];
    label: WorkflowPoint;
    insert: WorkflowPoint;
};

/** @public */
export type WorkflowConnectionRouter = (
    source: WorkflowPoint,
    target: WorkflowPoint,
    branch?: WorkflowBranchLane,
) => WorkflowConnectionRoute;

/** Visuals supplement the server's executable type specification. @public */
export interface WorkflowNodePresentation {
    icon: string;
    labelKey?: string;
    tone: 'neutral' | 'blue' | 'purple' | 'green';
    summaryKey?: string;
    portLabelKeys?: Record<string, string>;
    settingsComponent?: string;
    nodeComponent?: string;
    resolvePorts?: (
        node: WorkflowNode,
        type: WorkflowNodeTypeSpecification,
    ) => { inputPorts: string[]; outputPorts: string[]; labels?: Record<string, string> };
}

/** @public */
export interface WorkflowNodeEditingStrategy {
    updateConfig?: (document: WorkflowDocument, nodeId: string, patch: Record<string, unknown>) => WorkflowDocument;
    canInsert(document: WorkflowDocument, type: WorkflowNodeTypeSpecification, connectionId: string): boolean;
    insert(
        document: WorkflowDocument,
        type: WorkflowNodeTypeSpecification,
        connectionId: string,
        id: string,
    ): WorkflowDocument;
    canRemove(document: WorkflowDocument, id: string): boolean;
    remove(document: WorkflowDocument, id: string): WorkflowDocument;
}

/** Translation is resolved when authoring a new document, never while reading saved labels. @public */
export interface WorkflowTemplateContext {
    translate: (key: string) => string;
}

/** @public */
export interface WorkflowTemplate {
    name: string;
    titleKey: string;
    descriptionKey: string;
    icon: string;
    available: (types: WorkflowNodeTypeSpecification[]) => boolean;
    create: (types: WorkflowNodeTypeSpecification[], context: WorkflowTemplateContext) => WorkflowDocument;
}

/** A business label may be authored text or a translated snippet. @public */
export type WorkflowFieldLabel = string | { snippet: string };

/** @public */
export type WorkflowField = {
    path: string;
    label: WorkflowFieldLabel;
} & (
    | { type: 'text' | 'amount' | 'number' | 'date' }
    | { type: 'select'; options: { value: string; label: WorkflowFieldLabel }[] }
);

/** Describes available inputs without fetching or executing business data. @public */
export type WorkflowFieldProvider = (document: WorkflowDocument, node: WorkflowNode) => WorkflowField[];

/** Editor feedback supplements authoritative server validation. @public */
export interface WorkflowStudioDiagnostic {
    name: string;
    check(document: WorkflowDocument, types: WorkflowNodeTypeSpecification[]): WorkflowViolation[];
}

/** One independently owned capability bundle installed during module/plugin initialization. @public */
export interface WorkflowStudioExtension {
    name: string;
    nodes?: {
        type: string;
        version: number;
        presentation: WorkflowNodePresentation;
        editing?: WorkflowNodeEditingStrategy;
    }[];
    templates?: WorkflowTemplate[];
    layouts?: {
        name: string;
        layout: WorkflowLayout;
        policies?: string[];
        route?: WorkflowConnectionRouter;
    }[];
    fieldProviders?: { name: string; provide: WorkflowFieldProvider }[];
    diagnostics?: WorkflowStudioDiagnostic[];
    editor?: WorkflowExtension;
}
