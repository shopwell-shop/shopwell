/** @sw-package framework */
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import zh from '../snippet/zh.json';

/** @public */
export type WorkflowFormFieldType = 'text' | 'amount' | 'number' | 'date' | 'select';

/** Published request form definition shared with workflow consumers. @public */
export interface WorkflowFormField {
    key: string;
    label: string;
    type: WorkflowFormFieldType;
    required: boolean;
    dataSource?: string;
    options?: { value: string; label: string }[];
}

/** Read draft fields without substituting removed fields or changing existing definitions. @public */
export function workflowFormFields(config: WorkflowNode['config']): WorkflowFormField[] {
    if (!('formFields' in config)) {
        return [{ key: 'reason', label: zh['sw-workflow'].inputs.reason, type: 'text', required: false }];
    }
    if (!Array.isArray(config.formFields)) return [];
    return JSON.parse(JSON.stringify(config.formFields)) as WorkflowFormField[];
}
