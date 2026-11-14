/** @sw-package framework */
import type {
    CanvasEditorState,
    CanvasEditorEvents,
    CanvasCommand,
    CanvasUiAction,
    CanvasUiComponent,
    CanvasEditorContext,
    CanvasExtensionContext,
    CanvasExtension,
    CanvasActionPosition,
    CanvasComponentPosition,
    CanvasInteraction,
    CanvasSelection,
    CanvasSessionOptions,
    CanvasUiTarget,
} from '../canvas-editor/canvas-extension.types';
import type { WorkflowConnection, WorkflowDocument, WorkflowNode } from './workflow.types';

/** @public */
export type WorkflowEditorState = CanvasEditorState<WorkflowDocument>;

/** @public */
export type WorkflowEditorEvents = CanvasEditorEvents<WorkflowDocument>;

/** @public */
export type WorkflowCommand = CanvasCommand<WorkflowDocument>;

/** @public */
export type WorkflowUiAction = CanvasUiAction<WorkflowDocument>;

/** @public */
export type WorkflowUiComponent = CanvasUiComponent<WorkflowDocument>;

/** @public */
export type WorkflowEditorContext = CanvasEditorContext<WorkflowDocument>;

/** @public */
export type WorkflowExtensionContext = CanvasExtensionContext<WorkflowDocument>;

/** @public */
export type WorkflowExtension = CanvasExtension<WorkflowDocument>;

/** @public */
export type WorkflowActionPosition = CanvasActionPosition;

/** @public */
export type WorkflowComponentPosition = CanvasComponentPosition;

/** @public */
export type WorkflowInteraction = CanvasInteraction;

/** @public */
export type WorkflowSelection = CanvasSelection;

/** @public */
export type WorkflowSessionOptions = CanvasSessionOptions;

/** @public */
export type WorkflowUiTarget = CanvasUiTarget;

/** @public */
export type WorkflowContributionProps = {
    context: WorkflowEditorContext;
    target: WorkflowUiTarget;
    node: WorkflowNode | null;
    connection: WorkflowConnection | null;
};
