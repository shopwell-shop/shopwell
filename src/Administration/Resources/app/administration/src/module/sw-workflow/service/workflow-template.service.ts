/** @sw-package framework */
import { shallowReactive } from 'vue';
import type { WorkflowTemplate } from 'src/core/workflow/workflow-studio.types';
import { registerWorkflowContribution } from './workflow-registration';

/** @public */
export type { WorkflowTemplate } from 'src/core/workflow/workflow-studio.types';

/** @private */
export default class WorkflowTemplateService {
    private readonly templates = shallowReactive(new Map<string, WorkflowTemplate>());

    register(template: WorkflowTemplate): () => void {
        return registerWorkflowContribution(this.templates, template.name, { ...template });
    }

    all(): WorkflowTemplate[] {
        return [...this.templates.values()];
    }

    get(name: string): WorkflowTemplate | undefined {
        return this.templates.get(name);
    }
}
