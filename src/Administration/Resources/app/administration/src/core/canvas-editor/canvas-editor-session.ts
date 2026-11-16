/** @sw-package framework */
import { CanvasEditor } from './canvas-editor';
import { CanvasEditorContributions } from './canvas-editor-contributions';
import type { CanvasDocument } from './canvas-document.types';
import type {
    CanvasActionPosition,
    CanvasCommand,
    CanvasComponentPosition,
    CanvasEditorContext,
    CanvasEditorEvents,
    CanvasEditorState,
    CanvasExtension,
    CanvasExtensionContext,
    CanvasSelection,
    CanvasSessionOptions,
    CanvasUiAction,
    CanvasUiComponent,
    CanvasUiTarget,
} from './canvas-extension.types';

type Listener = { priority: number; invoke: (event: unknown) => void };
type ExtensionScope = { active: boolean; disposers: Array<() => void> };
function clone<T>(value: T): T {
    return value === undefined ? value : (JSON.parse(JSON.stringify(value)) as T);
}
function cancelable<T>(value: T): T & { defaultPrevented: boolean; preventDefault(): void } {
    return {
        ...value,
        defaultPrevented: false,
        preventDefault() {
            this.defaultPrevented = true;
        },
    };
}

/** One instance owns its document, commands, listeners and contribution lifecycle. @public */
export class CanvasEditorSession<TDocument extends CanvasDocument> {
    private readonly editor: CanvasEditor<TDocument>;
    private readonly extensions = new Map<string, ExtensionScope>();
    private readonly commands = new Map<string, CanvasCommand<TDocument>>();
    private readonly contributions: CanvasEditorContributions<TDocument>;
    private readonly listeners = new Map<keyof CanvasEditorEvents<TDocument>, Set<Listener>>();
    private selection: CanvasSelection = { nodeId: null, connectionId: null };
    private disposed = false;
    private executing = false;

    constructor(
        document: TDocument,
        private readonly options: CanvasSessionOptions = {},
    ) {
        this.editor = new CanvasEditor(document);
        this.contributions = new CanvasEditorContributions(
            this.context,
            () => {
                if (!this.disposed) {
                    this.emit('session.changed', undefined);
                }
            },
            (error) => this.report(error),
        );
    }

    getState(): CanvasEditorState<TDocument> {
        this.assertActive();
        return {
            document: this.editor.document,
            selection: { ...this.selection },
            canEdit: this.options.canEdit?.() ?? true,
            canUndo: this.editor.canUndo,
            canRedo: this.editor.canRedo,
        };
    }

    get context(): CanvasEditorContext<TDocument> {
        return {
            getState: () => this.getState(),
            commands: {
                execute: (name, payload) => this.execute(name, payload),
                canExecute: (name, payload) => this.canExecute(name, payload),
            },
            selection: { selectNode: (id) => this.selectNode(id), selectConnection: (id) => this.selectConnection(id) },
            ui: { openDialog: (name, props) => this.openDialog(name, props), closeDialog: () => this.closeDialog() },
        };
    }

    install(extension: CanvasExtension<TDocument>): boolean {
        this.assertActive();

        if (this.extensions.has(extension.name)) {
            throw new Error(`Canvas extension already installed: ${extension.name}`);
        }

        const scope: ExtensionScope = { active: true, disposers: [] };
        this.extensions.set(extension.name, scope);
        const guard =
            <T extends unknown[], R>(callback: (...args: T) => R) =>
            (...args: T): R => {
                this.assertActive();

                if (!scope.active) {
                    throw new Error(`Canvas extension is inactive: ${extension.name}`);
                }

                return callback(...args);
            };
        const own = (dispose: () => void) => {
            scope.disposers.push(dispose);
            return dispose;
        };
        const base = this.context;
        const context: CanvasExtensionContext<TDocument> = {
            getState: guard(base.getState),
            commands: {
                execute: guard(base.commands.execute),
                canExecute: guard(base.commands.canExecute),
                register: guard((command) => {
                    if (['core:undo', 'core:redo'].includes(command.name)) {
                        throw new Error(`Reserved canvas command: ${command.name}`);
                    }

                    return own(
                        this.register(this.commands, {
                            ...command,
                            execute: (draft, payload) => command.execute(draft, payload, context),
                        }),
                    );
                }),
            },
            selection: {
                selectNode: guard(base.selection.selectNode),
                selectConnection: guard(base.selection.selectConnection),
            },
            ui: {
                openDialog: guard(base.ui.openDialog),
                closeDialog: guard(base.ui.closeDialog),
                registerAction: guard((action) => own(this.contributions.registerAction(action))),
                registerComponent: guard((component) => own(this.contributions.registerComponent(component, context))),
            },
            events: { subscribe: guard((name, listener, priority) => own(this.subscribe(name, listener, priority))) },
        };
        try {
            const dispose = extension.setup(context);

            if (dispose) {
                own(dispose);
            }
        } catch (error) {
            this.uninstall(extension.name);
            this.report(error);
            return false;
        }
        this.emit('session.changed', undefined);
        return true;
    }

    uninstall(name: string): void {
        const scope = this.extensions.get(name);

        if (!scope) {
            return;
        }

        scope.active = false;
        this.extensions.delete(name);
        scope.disposers.reverse().forEach((dispose) => {
            try {
                dispose();
            } catch (error) {
                this.report(error);
            }
        });

        if (!this.disposed) {
            this.emit('session.changed', undefined);
        }
    }

    subscribe<K extends keyof CanvasEditorEvents<TDocument>>(
        name: K,
        listener: (event: CanvasEditorEvents<TDocument>[K]) => void,
        priority = 0,
    ): () => void {
        this.assertActive();
        const entry: Listener = { priority, invoke: (event) => listener(event as CanvasEditorEvents<TDocument>[K]) };
        const listeners = this.listeners.get(name) ?? new Set<Listener>();
        listeners.add(entry);
        this.listeners.set(name, listeners);
        return () => {
            listeners.delete(entry);
        };
    }

    canExecute(name: string, payload?: unknown): boolean {
        this.assertActive();

        if (this.executing) {
            return false;
        }

        const state = this.getState();

        if (name === 'core:undo') {
            return state.canEdit && state.canUndo;
        }

        if (name === 'core:redo') {
            return state.canEdit && state.canRedo;
        }

        const command = this.commands.get(name);

        if (!command || (command.mutatesDocument !== false && !state.canEdit)) {
            return false;
        }

        try {
            return command.canExecute?.(state, clone(payload)) ?? true;
        } catch (error) {
            this.report(error);
            return false;
        }
    }

    execute(name: string, payload?: unknown): boolean {
        this.assertActive();

        if (!this.canExecute(name, payload)) {
            return false;
        }

        if (name === 'core:undo' || name === 'core:redo') {
            return this.travel(name);
        }

        const command = this.commands.get(name);

        if (!command) {
            return false;
        }

        const previous = JSON.stringify(this.editor.document);
        this.executing = true;
        try {
            if (
                !this.beforeCommand(name, payload) ||
                this.disposed ||
                this.commands.get(name) !== command ||
                (command.mutatesDocument !== false && !this.getState().canEdit)
            ) {
                return false;
            }

            this.editor.execute((draft) => {
                const result = command.execute(draft, clone(payload), this.context);
                this.assertActive();

                if (command.mutatesDocument === false && JSON.stringify(result) !== previous) {
                    throw new Error(`View command changed the canvas: ${name}`);
                }

                return result;
            });
            this.afterCommand(name, previous);
            return true;
        } catch (error) {
            this.report(error);
            return false;
        } finally {
            this.executing = false;
        }
    }

    undo(): void {
        this.execute('core:undo');
    }
    redo(): void {
        this.execute('core:redo');
    }

    selectNode(id: string | null): void {
        this.assertActive();

        if (id && !this.editor.document.nodes.some((node) => node.id === id)) {
            return;
        }

        this.select({ nodeId: id, connectionId: null });
    }

    selectConnection(id: string | null): void {
        this.assertActive();

        if (id && !this.editor.document.connections.some((edge) => edge.id === id)) {
            return;
        }

        this.select({ nodeId: null, connectionId: id });
    }

    actions(position: CanvasActionPosition, target: CanvasUiTarget = {}): CanvasUiAction<TDocument>[] {
        this.assertActive();
        return this.contributions.actions(position, target);
    }

    components(position: CanvasComponentPosition, target: CanvasUiTarget = {}): CanvasUiComponent<TDocument>[] {
        this.assertActive();
        return this.contributions.components(position, target);
    }

    canRunAction(name: string, target: CanvasUiTarget = {}): boolean {
        this.assertActive();
        return this.contributions.canRunAction(name, target);
    }

    runAction(name: string, target: CanvasUiTarget = {}): boolean {
        this.assertActive();
        return this.contributions.runAction(name, target);
    }

    contextForComponent(name: string): CanvasEditorContext<TDocument> {
        this.assertActive();
        return this.contributions.contextForComponent(name);
    }

    get activeDialog(): { component: CanvasUiComponent<TDocument>; props: Record<string, unknown> } | null {
        return this.contributions.activeDialog;
    }

    dispose(): void {
        if (this.disposed) {
            return;
        }

        this.disposed = true;
        this.emit('session.destroyed', undefined);
        [...this.extensions.keys()].reverse().forEach((name) => this.uninstall(name));
        this.listeners.clear();
        this.commands.clear();
        this.contributions.clear();
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
        this.emit('session.changed', undefined);
        return () => {
            if (registry.get(entry.name) === entry) {
                registry.delete(entry.name);

                if (!this.disposed) {
                    this.emit('session.changed', undefined);
                }
            }
        };
    }

    private beforeCommand(name: string, payload?: unknown): boolean {
        const event = cancelable({ name, payload: clone(payload) });
        this.emit('command.beforeExecute', event);
        return !event.defaultPrevented;
    }

    private afterCommand(name: string, previous: string): void {
        const document = this.editor.document;

        if (JSON.stringify(document) !== previous) {
            this.select({
                nodeId: document.nodes.some((node) => node.id === this.selection.nodeId) ? this.selection.nodeId : null,
                connectionId: document.connections.some((edge) => edge.id === this.selection.connectionId)
                    ? this.selection.connectionId
                    : null,
            });
            this.emit('document.changed', document);
        }

        this.emit('command.executed', { name });
        this.emit('session.changed', undefined);
    }

    private travel(name: 'core:undo' | 'core:redo'): boolean {
        this.executing = true;
        const previous = JSON.stringify(this.editor.document);
        try {
            if (!this.beforeCommand(name) || this.disposed || !this.getState().canEdit) {
                return false;
            }

            if (name === 'core:undo') {
                this.editor.undo();
            } else {
                this.editor.redo();
            }

            this.afterCommand(name, previous);
            return true;
        } finally {
            this.executing = false;
        }
    }

    private select(selection: CanvasSelection): void {
        if (selection.nodeId === this.selection.nodeId && selection.connectionId === this.selection.connectionId) {
            return;
        }

        this.selection = selection;
        this.emit('selection.changed', { ...selection });
        this.emit('session.changed', undefined);
    }

    private openDialog(name: string, props: Record<string, unknown> = {}): void {
        this.assertActive();
        this.contributions.openDialog(name, props);
    }

    private closeDialog(): void {
        this.assertActive();
        this.contributions.closeDialog();
    }

    private assertActive(): void {
        if (this.disposed) {
            throw new Error('Canvas session has been disposed');
        }
    }
    private report(error: unknown): void {
        try {
            this.options.onError?.(error instanceof Error ? error : new Error(String(error)));
        } catch {
            /* Error reporting must not interrupt session cleanup. */
        }
    }
    private emit<K extends keyof CanvasEditorEvents<TDocument>>(name: K, event: CanvasEditorEvents<TDocument>[K]): void {
        [...(this.listeners.get(name) ?? [])]
            .sort((a, b) => b.priority - a.priority)
            .forEach((listener) => {
                try {
                    if (this.listeners.get(name)?.has(listener)) {
                        listener.invoke(
                            ['document.changed', 'selection.changed', 'command.executed'].includes(name)
                                ? clone(event)
                                : event,
                        );
                    }
                } catch (error) {
                    this.report(error);
                }
            });
    }
}
