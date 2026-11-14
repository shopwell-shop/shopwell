/** @sw-package framework */
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import { workflowFormFields, type WorkflowFormField } from '../util/workflow-form.util';

type DataSource = { name: string; labelKey: string };

/** @public */
export default class WorkflowRequestFormService {
    private readonly dataSources = new Map<string, DataSource>();

    registerDataSource(source: DataSource): void {
        this.dataSources.set(source.name, source);
    }

    getDataSources(): DataSource[] {
        return [...this.dataSources.values()];
    }

    getFields(config: WorkflowNode['config']): WorkflowFormField[] {
        return workflowFormFields(config);
    }
}
