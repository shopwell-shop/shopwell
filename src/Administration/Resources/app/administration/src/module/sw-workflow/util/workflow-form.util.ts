/** @sw-package framework */
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import type { WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';

/** Read draft fields without substituting removed fields or changing existing definitions. @public */
export function workflowFormFields(config: WorkflowNode['config']): WorkflowFormField[] {
    if (!('formFields' in config) || !Array.isArray(config.formFields)) {
        return [];
    }

    return JSON.parse(JSON.stringify(config.formFields)) as WorkflowFormField[];
}
