/** @sw-package framework */
import type { WorkflowFieldProvider } from 'src/core/workflow/workflow-studio.types';
import { workflowFormFields } from '../../util/workflow-form.util';

/** A request schema belongs to its workflow, independently of the task types it contains. @private */
const workflowRequestFieldProvider: WorkflowFieldProvider = (document) => {
    const start = document.nodes.find((node) => node.type === 'core:start' && node.typeVersion === 1);

    if (!start || !('formFields' in start.config)) {
        return [];
    }

    return workflowFormFields(start.config).map((field) =>
        field.type === 'select'
            ? { path: `request.${field.key}`, label: field.label, type: field.type, options: field.options ?? [] }
            : { path: `request.${field.key}`, label: field.label, type: field.type },
    );
};

/** @private */
export default workflowRequestFieldProvider;
