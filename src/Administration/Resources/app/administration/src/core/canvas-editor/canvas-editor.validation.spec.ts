/** @sw-package framework */
import { CanvasEditor } from './canvas-editor';
import type { CanvasDocument } from './canvas-document.types';

const validDocument: CanvasDocument = {
    nodes: [{ id: 'card' }],
    connections: [{ id: 'link', source: 'card', target: 'card' }],
};

const malformedDocuments: Array<[string, unknown]> = [
    ['null document', null],
    ['array document', []],
    ['missing nodes', { connections: [] }],
    ['missing connections', { nodes: [] }],
    ['nodes object', { nodes: {}, connections: [] }],
    ['connections object', { nodes: [], connections: {} }],
    ['null node', { nodes: [null], connections: [] }],
    ['array node', { nodes: [[]], connections: [] }],
    ['primitive node', { nodes: ['card'], connections: [] }],
    ['missing node identifier', { nodes: [{}], connections: [] }],
    ['non-string node identifier', { nodes: [{ id: 1 }], connections: [] }],
    ['empty node identifier', { nodes: [{ id: '' }], connections: [] }],
    ['duplicate node identifiers', { nodes: [{ id: 'card' }, { id: 'card' }], connections: [] }],
    ['null connection', { nodes: [], connections: [null] }],
    ['array connection', { nodes: [], connections: [[]] }],
    ['missing connection identifier', { nodes: [], connections: [{ source: 'a', target: 'b' }] }],
    ['missing connection source', { nodes: [], connections: [{ id: 'link', target: 'b' }] }],
    ['missing connection target', { nodes: [], connections: [{ id: 'link', source: 'a' }] }],
    ['non-string connection source', { nodes: [], connections: [{ id: 'link', source: 1, target: 'b' }] }],
    ['blank connection target', { nodes: [], connections: [{ id: 'link', source: 'a', target: ' ' }] }],
    [
        'duplicate connection identifiers',
        {
            nodes: [],
            connections: [
                { id: 'link', source: 'a', target: 'b' },
                { id: 'link', source: 'b', target: 'a' },
            ],
        },
    ],
];

describe('canvas graph document boundary', () => {
    it.each(malformedDocuments)('rejects %s during construction', (_description, value) => {
        // JavaScript extensions and deserialized documents do not carry TypeScript guarantees.
        expect(() => new CanvasEditor(value as CanvasDocument)).toThrow(/Canvas document/);
    });

    it('rejects a malformed replacement without losing the current graph or redo history', () => {
        const editor = new CanvasEditor(validDocument);
        editor.execute((draft) => ({ ...draft, nodes: [{ id: 'changed' }] }));
        editor.undo();

        const invalid: unknown = { connections: [] };
        expect(() => editor.replace(invalid as CanvasDocument)).toThrow(/Canvas document/);

        expect(editor.document).toEqual(validDocument);
        expect(editor.canUndo).toBe(false);
        expect(editor.canRedo).toBe(true);
        editor.redo();
        expect(editor.document.nodes).toEqual([{ id: 'changed' }]);
    });

    it.each([
        ['invalid node', { nodes: [null], connections: [] }],
        ['invalid endpoint', { nodes: [], connections: [{ id: 'link', source: 'card', target: null }] }],
        ['duplicate node identifier', { nodes: [{ id: 'card' }, { id: 'card' }], connections: [] }],
        [
            'duplicate connection identifier',
            {
                nodes: [],
                connections: [
                    { id: 'link', source: 'a', target: 'b' },
                    { id: 'link', source: 'b', target: 'a' },
                ],
            },
        ],
    ])('rejects %s returned by an untyped command before committing', (_description, value) => {
        const editor = new CanvasEditor(validDocument);

        expect(() => editor.execute(() => value as CanvasDocument)).toThrow(/Canvas document/);

        expect(editor.document).toEqual(validDocument);
        expect(editor.canUndo).toBe(false);
        expect(editor.canRedo).toBe(false);
    });

    it('accepts dangling references, graph cycles and independently named node and connection identifiers', () => {
        const draft: CanvasDocument = {
            nodes: [{ id: 'shared' }, { id: 'other' }],
            connections: [
                { id: 'shared', source: 'shared', target: 'other' },
                { id: 'back', source: 'other', target: 'shared' },
                { id: 'unfinished', source: 'other', target: 'missing' },
            ],
        };

        expect(new CanvasEditor(draft).document).toEqual(draft);
    });
});
