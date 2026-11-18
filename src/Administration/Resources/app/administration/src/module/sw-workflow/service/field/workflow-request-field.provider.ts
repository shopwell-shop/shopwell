/** @sw-package framework */
import { WORKFLOW_NODE_TYPES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowField, WorkflowFieldProvider } from 'src/core/workflow/workflow-studio.types';
import { workflowFormFields } from '../../util/workflow-form.util';

/** A request schema belongs to its workflow, independently of the task types it contains. @private */
const workflowRequestFieldProvider: WorkflowFieldProvider = (document) => {
    const start = document.nodes.find((node) => node.type === WORKFLOW_NODE_TYPES.START && node.typeVersion === 1);

    if (!start || !('formFields' in start.config)) {
        return [];
    }

    return workflowFormFields(start.config).flatMap<WorkflowField>((field) => {
        if (field.type === 'user' || field.type === 'users' || field.type === 'department' || field.type === 'media' || field.type === 'mediaList' || field.type === 'images') {
            return [];
        }
        if (field.type === 'select') {
            return [{ path: `request.${field.key}`, label: field.label, type: field.type, options: field.options ?? [] }];
        }
        return [{ path: `request.${field.key}`, label: field.label, type: field.type }];
    });
};

/** @private */
export default workflowRequestFieldProvider;
