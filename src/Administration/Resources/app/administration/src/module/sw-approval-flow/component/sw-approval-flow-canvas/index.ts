/** @sw-package framework */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Graph } from '@antv/x6';
import type { Cell, Edge, EdgeMetadata, EventArgs, NodeMetadata } from '@antv/x6';
import type { PropType } from 'vue';
import type { ApprovalDesignGraph } from '../../service/approval-flow-design.api.service';
import type { ApprovalNodeView } from '../../designer/approval-designer.service';
import type { ApprovalConnectionEndpoints, ApprovalDesignNode } from '../../designer/approval-editor-session';
import { canConnect } from '../../designer/approval-editor-session';
import template from './sw-approval-flow-canvas.html.twig';

const NODE_HEIGHT = 72;
const NODE_WIDTH = 284;
const FREE_NODE_WIDTH = 204;
const TERMINAL_WIDTH = 180;
const TERMINAL_INSET = 15;
const TEXT = 'var(--color-text-primary-default)';
const MUTED_TEXT = 'var(--color-text-secondary-default)';
const ATTENTION = 'var(--color-text-attention-default)';
const NODE_FILL = 'var(--color-background-primary-default)';
const NODE_STROKE = 'var(--color-border-primary-default)';
const CONNECTION_STROKE = 'var(--sw-approval-flow-edge)';
const SELECTION_STROKE = 'var(--color-interaction-primary-default)';
const LABEL_STYLE = {
    refX: 0,
    refY: 0,
    textAnchor: 'start',
    textVerticalAnchor: 'middle',
    fontFamily: 'inherit',
};
const NODE_MARKUP = [
    { tagName: 'rect', selector: 'body' },
    { tagName: 'rect', selector: 'mark' },
    { tagName: 'path', selector: 'symbol' },
    { tagName: 'text', selector: 'title' },
    { tagName: 'text', selector: 'summary' },
    { tagName: 'path', selector: 'chevron' },
];

function nodeCell(id: string): string {
    return `node:${id}`;
}

function connectionCell(id: string): string {
    return `connection:${id}`;
}

function terminalAttributes(view: ApprovalNodeView): NodeMetadata['attrs'] {
    const left = (NODE_WIDTH - TERMINAL_WIDTH) / 2;
    const middle = NODE_HEIGHT / 2;

    return {
        body: {
            x: left,
            y: TERMINAL_INSET,
            refWidth: TERMINAL_WIDTH - NODE_WIDTH,
            refHeight: -TERMINAL_INSET * 2,
            rx: 21,
            fill: NODE_FILL,
            stroke: NODE_STROKE,
            cursor: 'pointer',
            'data-approval-node': 'terminal',
        },
        mark: {
            x: left + 17,
            y: middle - 4,
            width: 8,
            height: 8,
            rx: 4,
            fill: view.tone === 'brand' ? SELECTION_STROKE : CONNECTION_STROKE,
            stroke: 'none',
        },
        title: {
            ...LABEL_STYLE,
            x: left + 35,
            y: middle,
            text: view.title,
            fill: TEXT,
            fontSize: 13,
            fontWeight: 600,
        },
        summary: {
            ...LABEL_STYLE,
            x: left + 83,
            y: middle,
            text: view.summary ?? '',
            fill: MUTED_TEXT,
            fontSize: 12,
            textWrap: { width: 84, height: 19, ellipsis: true },
        },
    };
}

function stepAttributes(view: ApprovalNodeView, width: number): NodeMetadata['attrs'] {
    return {
        body: {
            width,
            height: NODE_HEIGHT,
            rx: 12,
            fill: NODE_FILL,
            stroke: NODE_STROKE,
            cursor: 'pointer',
            class: 'sw-approval-flow-designer__node-card',
            'data-approval-node': 'step',
        },
        mark: {
            x: 15,
            y: 18,
            width: 36,
            height: 36,
            rx: 10,
            fill: 'color-mix(in srgb, var(--color-interaction-primary-default) 12%, var(--color-background-primary-default))',
            stroke: 'none',
        },
        symbol: {
            d: 'M 28 31 H 39 M 28 36 H 39 M 28 41 H 34',
            fill: 'none',
            stroke: SELECTION_STROKE,
            strokeWidth: 1.6,
            strokeLinecap: 'round',
        },
        title: {
            ...LABEL_STYLE,
            x: 64,
            y: view.summary ? 26 : 36,
            text: view.title,
            fill: TEXT,
            fontSize: 14,
            fontWeight: 600,
            textWrap: { width: width - 98, height: 21, ellipsis: true },
        },
        summary: {
            ...LABEL_STYLE,
            x: 64,
            y: 48,
            text: view.summary ?? '',
            fill: view.tone === 'attention' ? ATTENTION : MUTED_TEXT,
            fontSize: 12,
            textWrap: { width: width - 98, height: 19, ellipsis: true },
        },
        chevron: {
            d: `M ${width - 24} 31 l 5 5 l -5 5`,
            fill: 'none',
            stroke: CONNECTION_STROKE,
            strokeWidth: 1.25,
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
        },
    };
}

function projectNode(node: ApprovalDesignNode, view: ApprovalNodeView, vertical: boolean): NodeMetadata {
    const width = vertical ? NODE_WIDTH : FREE_NODE_WIDTH;
    const terminal = vertical && view.shape === 'terminal';
    const inset = terminal ? TERMINAL_INSET : 0;

    return {
        id: nodeCell(node.id),
        shape: 'rect',
        x: node.position.x,
        y: node.position.y,
        width,
        height: NODE_HEIGHT,
        markup: NODE_MARKUP,
        attrs: terminal ? terminalAttributes(view) : stepAttributes(view, width),
        ports: {
            groups: {
                input: {
                    position: vertical ? { name: 'top', args: { dy: inset } } : 'left',
                    attrs: { circle: { r: 3.5, magnet: 'passive', fill: NODE_FILL, stroke: CONNECTION_STROKE } },
                },
                output: {
                    position: vertical ? { name: 'bottom', args: { dy: -inset } } : 'right',
                    attrs: { circle: { r: 3.5, magnet: true, fill: NODE_FILL, stroke: CONNECTION_STROKE } },
                },
            },
            items: node.ports.map((port) => ({ id: port.id, group: port.direction })),
        },
    };
}

function insertionButton(onInsert: (id: string) => void) {
    return {
        name: 'button',
        args: {
            distance: 0.5,
            markup: [
                {
                    tagName: 'circle',
                    attrs: {
                        r: 12,
                        fill: NODE_FILL,
                        stroke: SELECTION_STROKE,
                        strokeWidth: 1,
                        cursor: 'pointer',
                        'data-approval-insert': '',
                    },
                },
                {
                    tagName: 'path',
                    attrs: {
                        d: 'M -5 0 H 5 M 0 -5 V 5',
                        fill: 'none',
                        stroke: SELECTION_STROKE,
                        strokeWidth: 1.5,
                        strokeLinecap: 'round',
                        pointerEvents: 'none',
                    },
                },
            ],
            onClick: ({ cell }: { cell: Cell }) => onInsert(cell.id.slice('connection:'.length)),
        },
    };
}

function projectGraph(
    document: ApprovalDesignGraph,
    views: Record<string, ApprovalNodeView>,
    addButton?: ReturnType<typeof insertionButton>,
): { nodes: NodeMetadata[]; edges: EdgeMetadata[] } {
    const vertical = document.meta.editingMode === 'oa';
    const nodes = document.nodes.map((node) => projectNode(node, views[node.id] ?? { title: node.type }, vertical));
    const edges = document.connections.map((edge) => ({
        id: connectionCell(edge.id),
        shape: 'edge',
        source: { cell: nodeCell(edge.source), port: edge.sourcePort },
        target: { cell: nodeCell(edge.target), port: edge.targetPort },
        router: 'orth',
        connector: { name: 'rounded', args: { radius: 8 } },
        tools: addButton ? [addButton] : undefined,
    }));

    return { nodes, edges };
}

function createCanvas(viewport: HTMLElement, document: () => ApprovalDesignGraph, canEdit: () => boolean): Graph {
    return new Graph({
        container: viewport,
        autoResize: true,
        panning: true,
        mousewheel: { enabled: true, modifiers: ['ctrl', 'meta'], minScale: 0.3, maxScale: 2 },
        interacting: () => ({ nodeMovable: canEdit(), edgeMovable: false, arrowheadMovable: false }),
        connecting: {
            allowBlank: false,
            allowNode: false,
            allowLoop: false,
            allowEdge: false,
            highlight: true,
            router: 'orth',
            connector: { name: 'rounded', args: { radius: 8 } },
            validateMagnet: ({ magnet }) => canEdit() && magnet.getAttribute('port-group') === 'output',
            validateConnection: ({ sourceCell, targetCell, sourcePort, targetPort }) => {
                if (!canEdit() || !sourceCell || !targetCell || !sourcePort || !targetPort) {
                    return false;
                }

                return canConnect(document(), {
                    source: sourceCell.id.slice('node:'.length),
                    target: targetCell.id.slice('node:'.length),
                    sourcePort,
                    targetPort,
                });
            },
        },
    });
}

function applySelection(canvas: Graph, nodeId: string | null, connectionId: string | null): void {
    for (const node of canvas.getNodes()) {
        const selected = node.id === nodeCell(nodeId ?? '');
        node.attr('body/stroke', selected ? SELECTION_STROKE : NODE_STROKE);
        node.attr('body/strokeWidth', selected ? 2.5 : 1.5);
    }
    for (const edge of canvas.getEdges()) {
        const selected = edge.id === connectionCell(connectionId ?? '');
        edge.attr('line/stroke', selected ? SELECTION_STROKE : CONNECTION_STROKE);
        edge.attr('line/strokeWidth', selected ? 3 : 1.5);
    }
}

function connectionEndpoints(edge: Edge): ApprovalConnectionEndpoints | null {
    const sourcePort = edge.getSourcePortId();
    const targetPort = edge.getTargetPortId();
    if (!sourcePort || !targetPort) {
        return null;
    }

    return {
        source: edge.getSourceCellId().slice('node:'.length),
        target: edge.getTargetCellId().slice('node:'.length),
        sourcePort,
        targetPort,
    };
}

function fitCanvas(canvas: Graph, alignTop = false): void {
    canvas.zoomToFit({ padding: 48, maxScale: 1 });
    if (alignTop) {
        const area = canvas.getContentArea();
        canvas.positionPoint({ x: area.center.x, y: area.y }, '50%', 88);
    }
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    props: {
        document: { type: Object as PropType<ApprovalDesignGraph>, required: true },
        views: { type: Object as PropType<Record<string, ApprovalNodeView>>, required: true },
        canInsert: { type: Boolean, default: false },
        selectedId: { type: String, default: null },
        selectedConnection: { type: String, default: null },
        readOnly: { type: Boolean, default: false },
    },

    emits: [
        'select-node',
        'select-connection',
        'move-node',
        'connect',
        'insert-node',
    ],

    setup(props, { emit, expose }) {
        const viewport = ref<HTMLElement>();
        let canvas: Graph | undefined;
        let viewWidth = 0;

        const addButton = insertionButton((id) => emit('insert-node', id));
        const cells = computed<ReturnType<typeof projectGraph>>((previous) => {
            const projection = projectGraph(props.document, props.views, props.canInsert ? addButton : undefined);

            return previous && JSON.stringify(previous) === JSON.stringify(projection) ? previous : projection;
        });

        function onNodeClick({ node }: EventArgs['node:click']): void {
            emit('select-node', node.id.slice('node:'.length));
        }

        function onConnectionClick({ edge }: EventArgs['edge:click']): void {
            emit('select-connection', edge.id.slice('connection:'.length));
        }

        function onResize({ width }: EventArgs['resize']): void {
            if (canvas && viewWidth) {
                const { tx, ty } = canvas.translate();
                canvas.translate(tx + (width - viewWidth) / 2, ty);
            }

            viewWidth = width;
        }

        function onBlankClick(): void {
            emit('select-node', null);
        }

        function onNodeMoved({ node }: EventArgs['node:moved']): void {
            emit('move-node', node.id.slice('node:'.length), node.position());
        }

        function onEdgeConnected({ edge, isNew }: EventArgs['edge:connected']): void {
            if (isNew) {
                const endpoints = connectionEndpoints(edge);
                canvas?.removeCell(edge);
                if (endpoints) {
                    emit('connect', endpoints);
                }
            }
        }

        function updateSelection(): void {
            if (canvas) {
                applySelection(canvas, props.selectedId, props.selectedConnection);
            }
        }

        function renderGraph(): void {
            canvas?.fromJSON(cells.value);
            updateSelection();
        }

        watch(cells, renderGraph);
        watch(() => [props.selectedId, props.selectedConnection], updateSelection);

        onMounted(() => {
            canvas = createCanvas(
                viewport.value as HTMLElement,
                () => props.document,
                () => !props.readOnly,
            );

            viewWidth = viewport.value?.clientWidth ?? 0;
            canvas.on('resize', onResize);
            canvas.on('node:click', onNodeClick);
            canvas.on('edge:click', onConnectionClick);
            canvas.on('blank:click', onBlankClick);
            canvas.on('node:moved', onNodeMoved);
            canvas.on('edge:connected', onEdgeConnected);
            renderGraph();
            fitCanvas(canvas, props.document.meta.editingMode === 'oa');
        });

        onBeforeUnmount(() => {
            canvas?.off('resize', onResize);
            canvas?.off('node:click', onNodeClick);
            canvas?.off('edge:click', onConnectionClick);
            canvas?.off('blank:click', onBlankClick);
            canvas?.off('node:moved', onNodeMoved);
            canvas?.off('edge:connected', onEdgeConnected);
            canvas?.dispose();
        });

        expose({
            fit: () => canvas?.zoomToFit({ padding: 48, maxScale: 1 }),
            zoom: (delta: number) => canvas?.zoom(delta, { minScale: 0.3, maxScale: 2 }),
        });

        return { viewport };
    },
});
