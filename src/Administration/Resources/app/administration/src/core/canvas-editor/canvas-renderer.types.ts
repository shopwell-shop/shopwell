/** @sw-package framework */

/** Canvas coordinates, independent of the rendering engine. @public */
export type CanvasPoint = { x: number; y: number };

/** @public */
export type CanvasNodeSize = { width: number; height: number };

/** @public */
export type CanvasPort = { id: string; side?: 'top' | 'bottom' | 'left' | 'right' };

/** A view projection supplied by the owning editor, never the persisted document. @public */
export interface CanvasRenderNode {
    id: string;
    label?: string;
    position: CanvasPoint;
    data: Record<string, unknown>;
    component?: string;
    inputPorts?: CanvasPort[];
    outputPorts?: CanvasPort[];
    selected?: boolean;
}

/** @public */
export interface CanvasRenderConnection {
    id: string;
    source: string;
    target: string;
    sourcePort?: string;
    targetPort?: string;
    label?: string;
    data?: Record<string, unknown>;
    selected?: boolean;
}

/** @public */
export type CanvasRenderGraph = { nodes: CanvasRenderNode[]; connections: CanvasRenderConnection[] };

/** @public */
export type CanvasConnectionRoute = { path: string; label: CanvasPoint; tools: CanvasPoint };

/** @public */
export type CanvasConnectionRouter = (
    source: CanvasPoint,
    target: CanvasPoint,
    connection: CanvasRenderConnection,
) => CanvasConnectionRoute;

/** @public */
export type CanvasConnectIntent = {
    source: string;
    target: string;
    sourcePort: string | null;
    targetPort: string | null;
};
