/** @sw-package framework */
import { shallowReactive } from 'vue';
import StructuredWorkflowEditingStrategy from './editing/structured-workflow-editing.strategy';
import type { WorkflowNodeEditingStrategy } from 'src/core/workflow/workflow-studio.types';
import { registerWorkflowContribution } from './workflow-registration';

/** @private */
export default class WorkflowNodeEditingService {
    private readonly strategies = shallowReactive(new Map<string, WorkflowNodeEditingStrategy>());
    private readonly defaultStrategy = new StructuredWorkflowEditingStrategy();

    register(name: string, version: number, strategy: WorkflowNodeEditingStrategy): () => void {
        return registerWorkflowContribution(this.strategies, `${name}:${version}`, strategy);
    }

    get(name: string, version: number): WorkflowNodeEditingStrategy {
        return this.strategies.get(`${name}:${version}`) ?? this.defaultStrategy;
    }
}
