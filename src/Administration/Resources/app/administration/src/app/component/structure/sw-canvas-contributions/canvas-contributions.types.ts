/** @sw-package framework */
import type { CanvasDocument } from 'src/core/canvas-editor/canvas-document.types';
import type {
    CanvasActionPosition,
    CanvasComponentPosition,
    CanvasEditorContext,
    CanvasEditorState,
    CanvasUiAction,
    CanvasUiComponent,
    CanvasUiTarget,
} from 'src/core/canvas-editor/canvas-extension.types';

type Action = Pick<CanvasUiAction<CanvasDocument>, 'name' | 'labelKey' | 'icon'>;
type Component = Pick<CanvasUiComponent<CanvasDocument>, 'name' | 'component' | 'titleKey'>;

/** The UI host reads contributions without owning a document or registering extensions. @public */
export interface CanvasContributionHost {
    getState(): CanvasEditorState<CanvasDocument>;
    actions(position: CanvasActionPosition, target?: CanvasUiTarget): Action[];
    components(position: CanvasComponentPosition, target?: CanvasUiTarget): Component[];
    canRunAction(name: string, target?: CanvasUiTarget): boolean;
    runAction(name: string, target?: CanvasUiTarget): boolean;
    contextForComponent(name: string): CanvasEditorContext<CanvasDocument>;
    subscribe(name: 'session.changed', listener: () => void): () => void;
    readonly context: CanvasEditorContext<CanvasDocument>;
    readonly activeDialog: { component: Component; props: Record<string, unknown> } | null;
}
