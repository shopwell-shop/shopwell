/** @sw-package framework */
import type { CanvasDocument } from './canvas-document.types';
import type {
    CanvasActionPosition,
    CanvasComponentPosition,
    CanvasEditorContext,
    CanvasUiAction,
    CanvasUiComponent,
    CanvasUiTarget,
} from './canvas-extension.types';

function copy<T>(value: T): T {
    return value === undefined ? value : (JSON.parse(JSON.stringify(value)) as T);
}

/** Session-owned UI contributions and dialog state, independent of command history. @private */
export class CanvasEditorContributions<TDocument extends CanvasDocument> {
    private readonly actionsByName = new Map<string, CanvasUiAction<TDocument>>();

    private readonly componentsByName = new Map<string, CanvasUiComponent<TDocument>>();

    private readonly componentContexts = new Map<string, CanvasEditorContext<TDocument>>();

    private dialog: { name: string; props: Record<string, unknown> } | null = null;

    constructor(
        private readonly host: CanvasEditorContext<TDocument>,
        private readonly onChange: () => void,
        private readonly onError: (error: unknown) => void,
    ) {}

    registerAction(action: CanvasUiAction<TDocument>): () => void {
        return this.register(this.actionsByName, action);
    }

    registerComponent(component: CanvasUiComponent<TDocument>, context: CanvasEditorContext<TDocument>): () => void {
        const unregister = this.register(this.componentsByName, component);
        this.componentContexts.set(component.name, context);
        let active = true;

        return () => {
            if (!active) {
                return;
            }

            active = false;
            this.componentContexts.delete(component.name);

            if (this.dialog?.name === component.name) {
                this.dialog = null;
            }

            unregister();
        };
    }

    actions(position: CanvasActionPosition, target: CanvasUiTarget = {}): CanvasUiAction<TDocument>[] {
        return [...this.actionsByName.values()]
            .filter((action) => action.position === position && this.visible(action, target))
            .sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
    }

    components(position: CanvasComponentPosition, target: CanvasUiTarget = {}): CanvasUiComponent<TDocument>[] {
        return [...this.componentsByName.values()]
            .filter((component) => component.position === position && this.visible(component, target))
            .sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
    }

    canRunAction(name: string, target: CanvasUiTarget = {}): boolean {
        const action = this.actionsByName.get(name);

        if (!action || !this.visible(action, target)) {
            return false;
        }

        const payload = this.actionPayload(action, target);
        return payload.valid && this.host.commands.canExecute(action.command, payload.value);
    }

    runAction(name: string, target: CanvasUiTarget = {}): boolean {
        const action = this.actionsByName.get(name);

        if (!action || !this.visible(action, target)) {
            return false;
        }

        const payload = this.actionPayload(action, target);
        return payload.valid && this.host.commands.execute(action.command, payload.value);
    }

    contextForComponent(name: string): CanvasEditorContext<TDocument> {
        const context = this.componentContexts.get(name);

        if (!context) {
            throw new Error(`Unknown canvas component: ${name}`);
        }

        return {
            getState: context.getState,
            commands: { execute: context.commands.execute, canExecute: context.commands.canExecute },
            selection: context.selection,
            ui: { openDialog: context.ui.openDialog, closeDialog: context.ui.closeDialog },
        };
    }

    get activeDialog(): { component: CanvasUiComponent<TDocument>; props: Record<string, unknown> } | null {
        const component = this.dialog ? this.componentsByName.get(this.dialog.name) : undefined;

        if (!component || !this.dialog || !this.visible(component, {})) {
            return null;
        }

        return { component, props: copy(this.dialog.props) };
    }

    openDialog(name: string, props: Record<string, unknown> = {}): void {
        if (this.componentsByName.get(name)?.position !== 'dialog') {
            throw new Error(`Unknown canvas dialog: ${name}`);
        }

        this.dialog = { name, props: copy(props) };
        this.onChange();
    }

    closeDialog(): void {
        this.dialog = null;
        this.onChange();
    }
    clear(): void {
        this.actionsByName.clear();
        this.componentsByName.clear();
        this.componentContexts.clear();
        this.dialog = null;
    }

    private actionPayload(action: CanvasUiAction<TDocument>, target: CanvasUiTarget): { valid: boolean; value?: unknown } {
        try {
            return {
                valid: true,
                value:
                    typeof action.payload === 'function'
                        ? action.payload(this.host.getState(), copy(target))
                        : copy(action.payload ?? target),
            };
        } catch (error) {
            this.onError(error);
            return { valid: false };
        }
    }

    private visible(item: { when?: CanvasUiAction<TDocument>['when'] }, target: CanvasUiTarget): boolean {
        try {
            return item.when?.(this.host.getState(), copy(target)) ?? true;
        } catch (error) {
            this.onError(error);
            return false;
        }
    }

    private register<T extends { name: string }>(registry: Map<string, T>, definition: T): () => void {
        if (!definition.name.includes(':')) {
            throw new Error(`Canvas registrations require a namespace: ${definition.name}`);
        }

        if (registry.has(definition.name)) {
            throw new Error(`Duplicate canvas registration: ${definition.name}`);
        }

        const entry = { ...definition };
        registry.set(entry.name, entry);
        this.onChange();
        return () => {
            if (registry.get(entry.name) === entry) {
                registry.delete(entry.name);
                this.onChange();
            }
        };
    }
}
