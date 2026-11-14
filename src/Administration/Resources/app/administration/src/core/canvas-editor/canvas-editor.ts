/** @sw-package framework */
import type { CanvasDocument } from './canvas-document.types';

function copy<TDocument extends CanvasDocument>(document: TDocument): TDocument {
    return JSON.parse(JSON.stringify(document)) as TDocument;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function requireIdentifier(value: unknown, path: string): string {
    if (typeof value !== 'string' || value.trim().length === 0) {
        throw new Error(`Canvas document ${path} must be a non-empty string`);
    }

    return value;
}

function validateGraphItems(items: unknown[], collection: 'nodes' | 'connections'): void {
    const identifiers = new Set<string>();

    for (const [index, item] of items.entries()) {
        const path = `${collection}[${index}]`;

        if (!isRecord(item)) {
            throw new Error(`Canvas document ${path} must be an object`);
        }

        const id = requireIdentifier(item.id, `${path}.id`);

        if (identifiers.has(id)) {
            throw new Error(`Canvas document ${collection} contain a duplicate identifier: ${id}`);
        }

        identifiers.add(id);

        if (collection === 'connections') {
            requireIdentifier(item.source, `${path}.source`);
            requireIdentifier(item.target, `${path}.target`);
        }
    }
}

function assertCanvasDocument(document: unknown): asserts document is CanvasDocument {
    if (!isRecord(document) || !Array.isArray(document.nodes) || !Array.isArray(document.connections)) {
        throw new Error('Canvas document must contain nodes and connections arrays');
    }

    validateGraphItems(document.nodes, 'nodes');
    validateGraphItems(document.connections, 'connections');
}

/**
 * Stores graph snapshots and bounded undo history without viewport state or business rules.
 * @public
 */
export class CanvasEditor<TDocument extends CanvasDocument> {
    private current: TDocument;

    private past: TDocument[] = [];

    private future: TDocument[] = [];

    constructor(document: TDocument) {
        const initial = copy(document);
        assertCanvasDocument(initial);

        this.current = initial;
    }

    get document(): TDocument {
        return copy(this.current);
    }

    execute(command: (document: TDocument) => TDocument): void {
        const draft = copy(this.current);
        const next = command(draft);

        this.commit(copy(next));
    }

    get canUndo(): boolean {
        return this.past.length > 0;
    }

    get canRedo(): boolean {
        return this.future.length > 0;
    }

    replace(document: TDocument): void {
        this.commit(copy(document));
    }

    undo(): void {
        const previous = this.past.pop();
        if (!previous) {
            return;
        }
        this.future.push(this.current);
        this.current = previous;
    }

    redo(): void {
        const next = this.future.pop();
        if (!next) {
            return;
        }
        this.past.push(this.current);
        this.current = next;
    }

    private commit(next: TDocument): void {
        assertCanvasDocument(next);

        if (JSON.stringify(next) === JSON.stringify(this.current)) {
            return;
        }
        // Current and history snapshots stay private; commands only receive a copy.
        this.past.push(this.current);
        this.past = this.past.slice(-50);
        this.future = [];
        this.current = next;
    }
}
