/** @sw-package framework */
import type { WorkflowStudioExtension } from 'src/core/workflow/workflow-studio.types';
import approvalWorkflowFieldProvider from '../field/approval-workflow-field.provider';
import ApprovalWorkflowEditingStrategy from '../editing/approval-workflow-editing.strategy';
import { createApprovalWorkflow, createExpenseWorkflow } from '../../util/approval-workflow-template.util';

/** Approval is installed through the same capability contract as business plugins. @private */
const approvalWorkflowStudioExtension: WorkflowStudioExtension = {
    name: 'core:approval-studio',
    fieldProviders: [{ name: 'core:approval-request', provide: approvalWorkflowFieldProvider }],
    nodes: [
        {
            type: 'core:approval',
            version: 1,
            editing: new ApprovalWorkflowEditingStrategy(),
            presentation: {
                icon: 'regular-user-shield',
                tone: 'green',
                summaryKey: 'sw-workflow.summaries.approval',
                portLabelKeys: { approved: 'sw-workflow.outcomes.approved', rejected: 'sw-workflow.outcomes.rejected' },
                settingsComponent: 'sw-workflow-approval-settings',
                nodeComponent: 'sw-workflow-approval-node',
            },
        },
    ],
    templates: [
        {
            name: 'core:approval',
            titleKey: 'sw-workflow.templates.approval',
            descriptionKey: 'sw-workflow.templates.approvalDescription',
            icon: 'regular-flow',
            available: (types) => types.some((type) => type.name === 'core:approval' && type.version === 1),
            create: createApprovalWorkflow,
        },
        {
            name: 'core:expense',
            titleKey: 'sw-workflow.templates.expense',
            descriptionKey: 'sw-workflow.templates.expenseDescription',
            icon: 'regular-flow',
            available: (types) =>
                ['core:approval', 'core:condition'].every((name) =>
                    types.some((type) => type.name === name && type.version === 1),
                ),
            create: createExpenseWorkflow,
        },
    ],
};

/** @private */
export default approvalWorkflowStudioExtension;
