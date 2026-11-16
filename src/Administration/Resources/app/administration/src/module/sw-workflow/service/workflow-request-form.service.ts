/** @sw-package framework */
import { shallowReactive } from 'vue';
import { registerWorkflowContribution } from './workflow-registration';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import type { WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';
import { workflowFormFields } from '../util/workflow-form.util';

type DataSource = { name: string; labelKey: string };

/** @public */
export default class WorkflowRequestFormService {
    private readonly dataSources = shallowReactive(new Map<string, DataSource>());

    registerDataSource(source: DataSource): () => void {
        return registerWorkflowContribution(this.dataSources, source.name, { ...source });
    }

    getDataSources(): DataSource[] {
        return [...this.dataSources.values()].map((source) => ({ ...source }));
    }

    getFields(config: WorkflowNode['config'], defaultReasonLabel: string): WorkflowFormField[] {
        if (!('formFields' in config)) {
            return [{ key: 'reason', label: defaultReasonLabel, type: 'text', required: false }];
        }

        return workflowFormFields(config);
    }
}
