/** @sw-package framework */
import { shallowReactive } from 'vue';
import { registerWorkflowContribution } from './workflow-registration';
/** @public */
export default class WorkflowReleaseActionService {
    private readonly components = shallowReactive(new Map<string, string>());

    register(publisher: string, component: string): () => void {
        return registerWorkflowContribution(this.components, publisher, component);
    }

    get(publisher: string): string | null {
        return this.components.get(publisher) ?? null;
    }
}
