/** @sw-package framework */
import { shallowReactive } from 'vue';
import type { WorkflowNodePresentation } from 'src/core/workflow/workflow-studio.types';
import { registerWorkflowContribution } from './workflow-registration';

/** @public */
export type { WorkflowNodePresentation } from 'src/core/workflow/workflow-studio.types';

/** Visual contributions never grant executable node capabilities. @private */
export default class WorkflowNodePresentationService {
    private readonly presentations = shallowReactive(new Map<string, WorkflowNodePresentation>());

    register(name: string, version: number, presentation: WorkflowNodePresentation): () => void {
        return registerWorkflowContribution(this.presentations, `${name}:${version}`, { ...presentation });
    }

    get(name: string, version: number): WorkflowNodePresentation {
        return this.presentations.get(`${name}:${version}`) ?? { icon: 'regular-flow', tone: 'neutral' };
    }
}
