/** @sw-package framework */
import { CanvasEditorSession } from './canvas-editor-session';
import type { CanvasEditorContext, CanvasExtensionContext } from './canvas-extension.types';
import type { CanvasDocument } from './canvas-document.types';

interface TestDocument extends CanvasDocument {
    title: string;
    nodes: Array<{ id: string; data: Record<string, unknown> }>;
}

const document: TestDocument = {
    title: 'Board',
    nodes: [{ id: 'card', data: { label: 'Card' } }],
    connections: [],
};

describe('canvas extension sessions', () => {
    it('executes an extension command as one undoable change and protects the authoritative document snapshot', () => {
        const session = new CanvasEditorSession(document);
        session.install({
            name: 'example:review',
            setup(context) {
                context.commands.register({
                    name: 'example:rename',
                    execute(draft, payload) {
                        draft.nodes[0].data = { label: String(payload) };
                        return draft;
                    },
                });
            },
        });
        const snapshot = session.getState().document;
        snapshot.nodes[0].data = { label: 'Mutated outside a command' };
        expect(session.execute('example:rename', 'Renamed')).toBe(true);
        expect(session.getState().document.nodes[0].data).toEqual({ label: 'Renamed' });
        session.undo();
        expect(session.getState().document.nodes[0].data).toEqual({ label: 'Card' });
        session.redo();
        expect(session.getState().document.nodes[0].data).toEqual({ label: 'Renamed' });
        session.dispose();
    });

    it('enforces current edit permissions on extension commands, undo and redo', () => {
        let editable = true;
        const session = new CanvasEditorSession(document, { canEdit: () => editable });
        session.install({
            name: 'example:review',
            setup(context) {
                context.commands.register({
                    name: 'example:rename',
                    execute(draft) {
                        draft.nodes[0].data = { label: 'Changed' };
                        return draft;
                    },
                });
            },
        });
        session.execute('example:rename');
        editable = false;
        expect(session.execute('example:rename')).toBe(false);
        session.undo();
        expect(session.getState().document.nodes[0].data).toEqual({ label: 'Changed' });
        editable = true;
        session.undo();
        editable = false;
        session.redo();
        expect(session.getState().document.nodes[0].data).toEqual({ label: 'Card' });
        session.dispose();
    });

    it('supports ordered cancelable interactions and command interception without leaking across sessions', () => {
        const first = new CanvasEditorSession(document);
        const second = new CanvasEditorSession(document);
        const calls: string[] = [];
        first.install({
            name: 'example:interactions',
            setup(context) {
                context.events.subscribe('node.click', () => calls.push('later'));
                context.events.subscribe(
                    'node.click',
                    (event) => {
                        calls.push(event.nodeId ?? '');
                        event.preventDefault();
                    },
                    100,
                );
                context.events.subscribe('command.beforeExecute', (event) => event.preventDefault());
                context.commands.register({
                    name: 'example:rename',
                    execute(draft) {
                        draft.nodes[0].data = {};
                        return draft;
                    },
                });
            },
        });
        expect(first.interact('node.click', { nodeId: 'card' })).toBe(false);
        expect(calls).toEqual(['card', 'later']);
        expect(second.interact('node.click', { nodeId: 'card' })).toBe(true);
        expect(second.canExecute('example:rename')).toBe(false);
        expect(second.getState().selection).toEqual({ nodeId: null, connectionId: null });
        expect(first.execute('example:rename')).toBe(false);
        expect(first.getState().canUndo).toBe(false);
        first.dispose();
        second.dispose();
    });

    it('removes contributions and listeners on uninstall and invalidates retained plugin contexts', () => {
        const session = new CanvasEditorSession(document);
        let retained: CanvasExtensionContext<TestDocument> | undefined;
        const calls: string[] = [];
        session.install({
            name: 'example:tools',
            setup(context) {
                retained = context;
                context.commands.register({ name: 'example:inspect', mutatesDocument: false, execute: (draft) => draft });
                context.ui.registerAction({
                    name: 'example:inspect',
                    position: 'node-menu',
                    labelKey: 'example.inspect',
                    command: 'example:inspect',
                });
                context.ui.registerComponent({
                    name: 'example:badge',
                    position: 'node-decoration',
                    component: 'example-badge',
                });
                context.events.subscribe('selection.changed', () => calls.push('selection'));
                return () => calls.push('disposed');
            },
        });
        session.selectNode('card');
        expect(session.actions('node-menu')).toHaveLength(1);
        expect(session.components('node-decoration')).toHaveLength(1);
        session.uninstall('example:tools');
        expect(session.actions('node-menu')).toHaveLength(0);
        expect(session.components('node-decoration')).toHaveLength(0);
        session.selectNode(null);
        expect(calls).toEqual(['selection', 'disposed']);
        expect(() => retained?.commands.execute('example:inspect')).toThrow();
        session.dispose();
    });

    it('rolls back a failed installation and isolates listener and cleanup failures', () => {
        const errors: string[] = [];
        const session = new CanvasEditorSession(document, { onError: (error) => errors.push(error.message) });
        session.install({
            name: 'example:broken',
            setup(context) {
                context.ui.registerComponent({
                    name: 'example:orphan',
                    position: 'canvas-overlay',
                    component: 'example-orphan',
                });
                throw new Error('setup failed');
            },
        });
        expect(session.components('canvas-overlay')).toHaveLength(0);
        session.install({
            name: 'example:listeners',
            setup(context) {
                context.events.subscribe('selection.changed', () => {
                    throw new Error('listener failed');
                });
                return () => {
                    throw new Error('cleanup failed');
                };
            },
        });
        session.selectNode('card');
        expect(session.getState().selection.nodeId).toBe('card');
        session.dispose();
        expect(errors).toEqual(['setup failed', 'listener failed', 'cleanup failed']);
    });

    it('rejects duplicate registrations and read-only commands that try to alter the document', () => {
        const session = new CanvasEditorSession(document);
        session.install({
            name: 'example:tools',
            setup(context) {
                context.commands.register({
                    name: 'example:inspect',
                    mutatesDocument: false,
                    execute(draft) {
                        draft.nodes = [];
                        return draft;
                    },
                });
            },
        });
        expect(session.execute('example:inspect')).toBe(false);
        expect(session.getState().document.nodes).toHaveLength(1);
        expect(() => session.install({ name: 'example:tools', setup() {} })).toThrow();
        session.dispose();
        expect(() => session.execute('example:inspect')).toThrow();
    });
    it('prevents reentrant command execution during interception and reports command failures without corrupting history', () => {
        const onError = jest.fn();
        const session = new CanvasEditorSession(document, { onError });
        let nested: boolean | undefined;
        session.install({
            name: 'example:commands',
            setup(context) {
                context.commands.register({
                    name: 'example:fail',
                    execute(draft) {
                        draft.nodes = [];
                        throw new Error('failed command');
                    },
                });
                context.commands.register({ name: 'example:inspect', mutatesDocument: false, execute: (draft) => draft });
                context.events.subscribe('command.beforeExecute', () => {
                    nested = context.commands.execute('example:inspect');
                });
            },
        });
        expect(session.execute('example:fail')).toBe(false);
        expect(nested).toBe(false);
        expect(onError).toHaveBeenCalledWith(new Error('failed command'));
        expect(session.getState().document).toEqual(document);
        expect(session.getState().canUndo).toBe(false);
        expect(session.execute('example:inspect')).toBe(true);
        session.dispose();
    });

    it('does not notify a listener after its extension was uninstalled during the same event', () => {
        const session = new CanvasEditorSession(document);
        const listener = jest.fn();
        session.install({
            name: 'example:remover',
            setup(context) {
                context.events.subscribe('node.click', () => session.uninstall('example:removed'), 100);
            },
        });
        session.install({
            name: 'example:removed',
            setup(context) {
                context.events.subscribe('node.click', listener);
            },
        });
        session.interact('node.click', { nodeId: 'card' });
        expect(listener).not.toHaveBeenCalled();
        session.dispose();
    });
    it('keeps contexts passed into commands and UI contributions scoped to their owning extension', () => {
        const session = new CanvasEditorSession(document);
        let retained: CanvasEditorContext<TestDocument> | undefined;
        session.install({
            name: 'example:owner',
            setup(context) {
                context.commands.register({
                    name: 'example:capture',
                    mutatesDocument: false,
                    execute(draft, _payload, commandContext) {
                        retained = commandContext;
                        return draft;
                    },
                });
                context.ui.registerComponent({
                    name: 'example:badge',
                    position: 'node-decoration',
                    component: 'example-badge',
                });
            },
        });
        session.execute('example:capture');
        const componentContext = session.contextForComponent('example:badge');
        expect(componentContext.getState().document).toEqual(document);
        session.uninstall('example:owner');
        expect(() => retained?.getState()).toThrow();
        expect(() => componentContext.getState()).toThrow();
        session.dispose();
    });
    it('keeps commands locked until every document notification has been delivered', () => {
        const session = new CanvasEditorSession(document);
        const received: string[] = [];
        const nested: boolean[] = [];
        session.install({
            name: 'example:followup',
            setup(context) {
                context.commands.register({
                    name: 'example:change',
                    execute(draft, payload) {
                        draft.title = String(payload);
                        return draft;
                    },
                });
                context.events.subscribe('document.changed', (value) => {
                    if (value.title === 'first') {
                        nested.push(context.commands.execute('example:change', 'second'));
                    }
                });
            },
        });
        session.subscribe('document.changed', (value) => received.push(value.title));
        session.execute('example:change', 'first');
        expect(nested).toEqual([false]);
        expect(received).toEqual(['first']);
        expect(session.getState().document.title).toBe('first');
        session.undo();
        session.redo();
        expect(received).toEqual(['first', document.title, 'first']);
        session.dispose();
    });

    it('notifies extensions when an edit removes their selected node', () => {
        const session = new CanvasEditorSession(document);
        const selected = jest.fn();
        session.install({
            name: 'example:delete',
            setup(context) {
                context.commands.register({
                    name: 'example:delete',
                    execute(draft) {
                        draft.nodes = [];
                        return draft;
                    },
                });
                context.events.subscribe('selection.changed', selected);
            },
        });
        session.selectNode('card');
        selected.mockClear();
        session.execute('example:delete');
        expect(selected).toHaveBeenCalledWith({ nodeId: null, connectionId: null });
        session.dispose();
    });

    it('delivers independent document snapshots so an extension cannot alter the save listener payload', () => {
        const session = new CanvasEditorSession(document);
        const saved: unknown[] = [];
        session.install({
            name: 'example:snapshots',
            setup(context) {
                context.commands.register({
                    name: 'example:change',
                    execute(draft) {
                        draft.title = 'first';
                        return draft;
                    },
                });
                context.events.subscribe('document.changed', (value) => {
                    value.title = 'mutated snapshot';
                });
                context.events.subscribe('selection.changed', (value) => {
                    value.nodeId = 'mutated selection';
                });
            },
        });
        session.subscribe('document.changed', (value) => saved.push(value));
        const selection = jest.fn();
        session.subscribe('selection.changed', selection);
        session.selectNode('card');
        session.execute('example:change');
        expect(selection).toHaveBeenCalledWith({ nodeId: 'card', connectionId: null });
        expect(saved).toEqual([{ ...document, title: 'first' }]);
        expect(session.getState().document.title).toBe('first');
        session.dispose();
    });

    it('rejects a JavaScript command that removes the graph without committing or notifying document listeners', () => {
        const errors: Error[] = [];
        const changed = jest.fn();
        const session = new CanvasEditorSession(document, { onError: (error) => errors.push(error) });
        session.subscribe('document.changed', changed);
        session.install({
            name: 'example:malformed',
            setup(context) {
                context.commands.register({
                    name: 'example:remove-nodes',
                    execute(draft) {
                        Reflect.deleteProperty(draft, 'nodes');
                        return draft;
                    },
                });
            },
        });

        expect(session.execute('example:remove-nodes')).toBe(false);

        expect(session.getState().document).toEqual(document);
        expect(session.getState().canUndo).toBe(false);
        expect(changed).not.toHaveBeenCalled();
        expect(errors).toHaveLength(1);
        expect(errors[0].message).toMatch(/Canvas document/);
        session.dispose();
    });

    it('does not reopen a removed dialog when a new contribution reuses its name', () => {
        const session = new CanvasEditorSession(document);
        let removeDialog = () => {};
        session.install({
            name: 'example:first-dialog',
            setup(context) {
                removeDialog = context.ui.registerComponent({
                    name: 'example:settings',
                    position: 'dialog',
                    component: 'example-old-settings',
                });
            },
        });
        session.context.ui.openDialog('example:settings', { message: 'Old draft' });
        expect(session.activeDialog?.props).toEqual({ message: 'Old draft' });

        removeDialog();
        expect(session.activeDialog).toBeNull();
        session.install({
            name: 'example:replacement-dialog',
            setup(context) {
                context.ui.registerComponent({
                    name: 'example:settings',
                    position: 'dialog',
                    component: 'example-new-settings',
                });
            },
        });

        expect(session.activeDialog).toBeNull();
        session.context.ui.openDialog('example:settings', { message: 'New draft' });
        session.uninstall('example:first-dialog');
        expect(session.activeDialog?.props).toEqual({ message: 'New draft' });
        session.dispose();
    });

    it('applies dialog visibility to current permissions and selection while preserving its open props', () => {
        let editable = true;
        const session = new CanvasEditorSession(document, { canEdit: () => editable });
        session.install({
            name: 'example:conditional-dialog',
            setup(context) {
                context.ui.registerComponent({
                    name: 'example:settings',
                    position: 'dialog',
                    component: 'example-settings',
                    when: (state) => state.canEdit && state.selection.nodeId === 'card',
                });
            },
        });
        session.selectNode('card');
        session.context.ui.openDialog('example:settings', { message: 'Current draft' });
        expect(session.activeDialog?.props).toEqual({ message: 'Current draft' });

        editable = false;
        expect(session.activeDialog).toBeNull();
        editable = true;
        expect(session.activeDialog?.props).toEqual({ message: 'Current draft' });

        session.selectNode(null);
        expect(session.activeDialog).toBeNull();
        session.selectNode('card');
        expect(session.activeDialog?.props).toEqual({ message: 'Current draft' });
        session.context.ui.closeDialog();
        expect(session.activeDialog).toBeNull();
        session.dispose();
    });

    it('keeps a replacement contribution and its dialog intact when an old disposer runs twice', () => {
        const session = new CanvasEditorSession(document);
        const contexts: CanvasExtensionContext<TestDocument>[] = [];
        let removeFirst = () => {};
        session.install({
            name: 'example:dialog-generations',
            setup(context) {
                contexts.push(context);
                removeFirst = context.ui.registerComponent({
                    name: 'example:settings',
                    position: 'dialog',
                    component: 'example-old-settings',
                });
            },
        });
        removeFirst();
        contexts[0].ui.registerComponent({
            name: 'example:settings',
            position: 'dialog',
            component: 'example-new-settings',
        });
        session.context.ui.openDialog('example:settings', { message: 'New draft' });

        removeFirst();

        expect(session.contextForComponent('example:settings').getState().document).toEqual(document);
        expect(session.activeDialog?.props).toEqual({ message: 'New draft' });
        session.dispose();
    });
});
