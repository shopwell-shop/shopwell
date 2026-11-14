/** @sw-package framework */
import type { CanvasDocument } from './canvas-document.types';

/** @public */
export type CanvasUiTarget = { nodeId?: string; connectionId?: string };
/** @public */
export type CanvasSelection = { nodeId: string | null; connectionId: string | null };
/** @public */
export type CanvasEditorState<TDocument extends CanvasDocument> = {
    document: TDocument;
    selection: CanvasSelection;
    canEdit: boolean;
    canUndo: boolean;
    canRedo: boolean;
};
/** @public */
export type CanvasInteraction = CanvasUiTarget & {
    position?: { x: number; y: number };
    defaultPrevented: boolean;
    preventDefault(): void;
};
/** @public */
export type CanvasEditorEvents<TDocument extends CanvasDocument> = {
    'node.click': CanvasInteraction;
    'node.contextmenu': CanvasInteraction;
    'connection.click': CanvasInteraction;
    'connection.insert': CanvasInteraction;
    'connection.contextmenu': CanvasInteraction;
    'canvas.click': CanvasInteraction;
    'canvas.contextmenu': CanvasInteraction;
    'selection.changed': CanvasSelection;
    'document.changed': TDocument;
    'command.beforeExecute': { name: string; payload: unknown; preventDefault(): void; defaultPrevented: boolean };
    'command.executed': { name: string };
    'session.changed': undefined;
    'session.destroyed': undefined;
};
/** @public */
export type CanvasActionPosition = 'toolbar' | 'node-menu' | 'edge-menu' | 'node-picker';
/** @public */
export type CanvasComponentPosition = 'canvas-overlay' | 'node-decoration' | 'panel' | 'dialog';
/** @public */
export interface CanvasCommand<TDocument extends CanvasDocument> {
    name: string;
    /** View commands must return an unchanged document, including in editable sessions. */
    mutatesDocument?: boolean;
    canExecute?: (state: CanvasEditorState<TDocument>, payload: unknown) => boolean;
    execute: (document: TDocument, payload: unknown, context: CanvasEditorContext<TDocument>) => TDocument;
}
/** @public */
export interface CanvasUiAction<TDocument extends CanvasDocument> {
    name: string;
    position: CanvasActionPosition;
    labelKey: string;
    icon?: string;
    order?: number;
    command: string;
    payload?:
        | string
        | number
        | boolean
        | null
        | Record<string, unknown>
        | unknown[]
        | ((state: CanvasEditorState<TDocument>, target: CanvasUiTarget) => unknown);
    when?: (state: CanvasEditorState<TDocument>, target: CanvasUiTarget) => boolean;
}
/** @public */
export interface CanvasUiComponent<TDocument extends CanvasDocument> {
    name: string;
    position: CanvasComponentPosition;
    component: string;
    titleKey?: string;
    order?: number;
    /** Hiding an open dialog preserves its props until it is closed or unregistered. */
    when?: (state: CanvasEditorState<TDocument>, target: CanvasUiTarget) => boolean;
}
/**
 * Every document returned by this facade is a detached JSON snapshot.
 * Canvas engine stores and mutable editor internals are deliberately absent.
 * @public
 */
export interface CanvasEditorContext<TDocument extends CanvasDocument> {
    getState: () => CanvasEditorState<TDocument>;
    commands: {
        execute: (name: string, payload?: unknown) => boolean;
        canExecute: (name: string, payload?: unknown) => boolean;
    };
    selection: {
        selectNode: (id: string | null) => void;
        selectConnection: (id: string | null) => void;
    };
    ui: {
        openDialog: (name: string, props?: Record<string, unknown>) => void;
        closeDialog: () => void;
    };
}
/** @public */
export interface CanvasExtensionContext<TDocument extends CanvasDocument> extends CanvasEditorContext<TDocument> {
    commands: CanvasEditorContext<TDocument>['commands'] & { register(command: CanvasCommand<TDocument>): () => void };
    ui: CanvasEditorContext<TDocument>['ui'] & {
        registerAction(action: CanvasUiAction<TDocument>): () => void;
        registerComponent(component: CanvasUiComponent<TDocument>): () => void;
    };
    events: {
        subscribe<K extends keyof CanvasEditorEvents<TDocument>>(
            name: K,
            listener: (event: CanvasEditorEvents<TDocument>[K]) => void,
            priority?: number,
        ): () => void;
    };
}
/** @public */
export interface CanvasExtension<TDocument extends CanvasDocument> {
    name: string;
    setup(context: CanvasExtensionContext<TDocument>): void | (() => void);
}
/** @public */
export type CanvasSessionOptions = {
    canEdit?: () => boolean;
    onError?: (error: Error) => void;
};
