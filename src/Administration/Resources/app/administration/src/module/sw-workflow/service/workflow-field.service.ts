/** @sw-package framework */
import { shallowReactive } from 'vue';
import type { WorkflowDocument, WorkflowNode } from 'src/core/workflow/workflow.types';
import type { WorkflowField, WorkflowFieldProvider } from 'src/core/workflow/workflow-studio.types';
import { registerWorkflowContribution } from './workflow-registration';

/** @public */
export type { WorkflowFieldLabel, WorkflowField, WorkflowFieldProvider } from 'src/core/workflow/workflow-studio.types';

/** @public */
export default class WorkflowFieldService {
    private readonly providers = shallowReactive(new Map<string, WorkflowFieldProvider>());

    register(name: string, provider: WorkflowFieldProvider): () => void {
        return registerWorkflowContribution(this.providers, name, provider);
    }

    getFields(document: WorkflowDocument, node: WorkflowNode): WorkflowField[] {
        const fields = new Map<string, WorkflowField>();

        for (const provider of this.providers.values()) {
            for (const field of provider(document, node)) {
                if (fields.has(field.path)) {
                    throw new Error(`Duplicate workflow field: ${field.path}`);
                }

                fields.set(field.path, field);
            }
        }

        return [...fields.values()];
    }
}
