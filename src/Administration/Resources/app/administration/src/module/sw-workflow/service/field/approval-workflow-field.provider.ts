/** @sw-package framework */
import type { WorkflowFieldProvider } from 'src/core/workflow/workflow-studio.types';
import { workflowFormFields } from '../../util/workflow-form.util';

/** Only the approval extension supplies its implicit default explanation field. @private */
const approvalWorkflowFieldProvider: WorkflowFieldProvider = (document) => {
    const start = document.nodes.find((node) => node.type === 'core:start' && node.typeVersion === 1);

    if (
        !start ||
        'formFields' in start.config ||
        !('inputType' in start.config) ||
        start.config.inputType !== 'core:approval-request'
    ) {
        return [];
    }

    return workflowFormFields(start.config).map((field) => ({
        path: `request.${field.key}`,
        label: field.label,
        type: 'text',
    }));
};

/** @private */
export default approvalWorkflowFieldProvider;
