/** @sw-package framework */
import { CanvasEditor } from './canvas-editor';

const emptyBoard = { nodes: [] as Array<{ id: string; label: string }>, connections: [] };

describe('canvas document history', () => {
    it('commits an entire graph command as one undoable change', () => {
        const editor = new CanvasEditor(emptyBoard);

        editor.execute((document) => ({ ...document, nodes: [{ id: 'card', label: 'Card' }] }));
        expect(editor.document.nodes).toEqual([{ id: 'card', label: 'Card' }]);

        editor.undo();
        expect(editor.document).toEqual(emptyBoard);

        editor.redo();
        expect(editor.document.nodes).toEqual([{ id: 'card', label: 'Card' }]);
    });

    it('keeps graph and history intact when a command throws', () => {
        const editor = new CanvasEditor(emptyBoard);

        expect(() =>
            editor.execute((draft) => {
                draft.nodes.push({ id: 'card', label: 'Discarded' });
                throw new Error('Invalid edit');
            }),
        ).toThrow('Invalid edit');

        expect(editor.document).toEqual(emptyBoard);
        expect(editor.canUndo).toBe(false);
    });

    it('isolates snapshots and discards redo when editing after undo', () => {
        const editor = new CanvasEditor({ ...emptyBoard, nodes: [{ id: 'card', label: 'Original' }] });
        const snapshot = editor.document;
        snapshot.nodes[0].label = 'Outside mutation';

        editor.execute((draft) => {
            draft.nodes[0].label = 'First';
            return draft;
        });
        editor.undo();
        editor.execute((draft) => {
            draft.nodes[0].label = 'Second';
            return draft;
        });

        expect(editor.canRedo).toBe(false);
        expect(editor.document.nodes[0].label).toBe('Second');
    });

    it('keeps command results detached after a plugin retains its draft', () => {
        const editor = new CanvasEditor(emptyBoard);
        const drafts: (typeof emptyBoard)[] = [];

        editor.execute((draft) => {
            draft.nodes.push({ id: 'card', label: 'Saved' });
            drafts.push(draft);
            return draft;
        });
        drafts[0].nodes[0].label = 'Outside mutation';

        expect(editor.document.nodes[0].label).toBe('Saved');
        editor.undo();
        editor.redo();
        expect(editor.document.nodes[0].label).toBe('Saved');
    });
});
