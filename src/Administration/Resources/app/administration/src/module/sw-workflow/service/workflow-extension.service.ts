/** @sw-package framework */
import { CanvasEditorSession } from 'src/core/canvas-editor/canvas-editor-session';
import type { WorkflowDocument } from 'src/core/workflow/workflow.types';
import type { WorkflowExtension, WorkflowSessionOptions } from 'src/core/workflow/workflow-extension.types';
import { registerWorkflowContribution } from './workflow-registration';

/** Registers extension definitions and releases their resources in every open editor. @public */
export default class WorkflowExtensionService {
    private readonly extensions = new Map<string, WorkflowExtension>();

    private readonly sessions = new Set<CanvasEditorSession<WorkflowDocument>>();

    register(extension: WorkflowExtension): () => void {
        const definition = { ...extension };
        const unregister = registerWorkflowContribution(this.extensions, definition.name, definition);
        let active = true;

        const installed: CanvasEditorSession<WorkflowDocument>[] = [];

        try {
            for (const session of this.sessions) {
                if (!session.install(definition)) {
                    throw new Error(`Could not install workflow extension: ${definition.name}`);
                }

                installed.push(session);
            }
        } catch (error) {
            unregister();

            for (const session of installed) {
                session.uninstall(definition.name);
            }

            throw error;
        }

        return () => {
            if (!active) {
                return;
            }

            active = false;
            unregister();

            for (const session of this.sessions) {
                session.uninstall(definition.name);
            }
        };
    }

    createSession(
        document: WorkflowDocument,
        options: WorkflowSessionOptions = {},
        localExtensions: WorkflowExtension[] = [],
    ): CanvasEditorSession<WorkflowDocument> {
        const session = new CanvasEditorSession<WorkflowDocument>(document, options);
        this.sessions.add(session);
        session.subscribe('session.destroyed', () => {
            this.sessions.delete(session);
        });

        for (const extension of localExtensions) {
            session.install(extension);
        }

        for (const extension of this.extensions.values()) {
            session.install(extension);
        }

        return session;
    }
}
