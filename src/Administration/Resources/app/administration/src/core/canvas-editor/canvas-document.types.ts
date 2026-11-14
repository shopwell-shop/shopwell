/** @sw-package framework */

/** @public */
export interface CanvasNode {
    id: string;
}

/** @public */
export interface CanvasConnection {
    id: string;
    source: string;
    target: string;
}

/**
 * JSON graph data owned by the editor. Domain extensions may add their own fields.
 * Identifiers must be non-empty and unique within their collection.
 * Unresolved connections and graph cycles remain valid while editing.
 * @public
 */
export interface CanvasDocument {
    nodes: CanvasNode[];
    connections: CanvasConnection[];
}
