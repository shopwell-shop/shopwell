/** @sw-package framework */
import { CanvasEditorSession } from 'src/core/canvas-editor/canvas-editor-session';
import type { CanvasExtension, CanvasSessionOptions } from 'src/core/canvas-editor/canvas-extension.types';
import { approvalEditing } from './approval-editing.extension';
import type {
    ApprovalDesignGraph,
    ApprovalJsonValue,
    ApprovalNodeDescriptor,
} from '../service/approval-flow-design.api.service';

/** @public */
export interface ApprovalNodeView {
    title: string;
    summary?: string;
    shape?: 'step' | 'terminal';
    tone?: 'brand' | 'attention' | 'neutral';
}

/**
 * Administration extensions register presentation for an exact server-declared node version.
 *
 * @public
 */
export interface ApprovalNodePresentation {
    settingsComponent: string;
    initialConfiguration: () => Record<string, ApprovalJsonValue>;
    view?: (
        node: ApprovalDesignGraph['nodes'][number],
        translate: (key: string, count?: number) => string,
    ) => Partial<ApprovalNodeView>;
    portCardinality?: Record<string, 'one' | 'many'>;
}

/** @private */
export default class ApprovalDesignerService {
    private readonly presentations = new Map<string, ApprovalNodePresentation>();

    private readonly extensions = new Map<string, CanvasExtension<ApprovalDesignGraph>>();

    /** Register a contribution for approval designer sessions opened after registration. */
    registerExtension(extension: CanvasExtension<ApprovalDesignGraph>): void {
        if (extension.name === approvalEditing.name || this.extensions.has(extension.name)) {
            throw new Error(`Approval designer extension already registered: ${extension.name}`);
        }

        this.extensions.set(extension.name, extension);
    }

    createSession(document: ApprovalDesignGraph, options: CanvasSessionOptions): CanvasEditorSession<ApprovalDesignGraph> {
        const session = new CanvasEditorSession(document, options);
        session.install(approvalEditing);

        for (const extension of this.extensions.values()) {
            session.install(extension);
        }

        return session;
    }

    registerNode(key: string, presentation: ApprovalNodePresentation): void {
        if (this.presentations.has(key)) {
            throw new Error(`Approval node presentation already registered: ${key}`);
        }

        this.presentations.set(key, presentation);
    }

    getNode(key: string): ApprovalNodePresentation | undefined {
        return this.presentations.get(key);
    }

    createNode(descriptor: ApprovalNodeDescriptor, position: ApprovalDesignGraph['nodes'][number]['position']) {
        const presentation = this.presentations.get(descriptor.key);

        return {
            id: Shopwell.Utils.createId(),
            type: descriptor.type,
            typeVersion: descriptor.version,
            config: presentation?.initialConfiguration() ?? {},
            ports: Object.entries(descriptor.ports).map(([id, direction]) => ({
                id,
                direction,
                channel: 'control' as const,
                cardinality: presentation?.portCardinality?.[id] ?? 'one',
            })),
            position,
            extensions: {},
        };
    }
}
