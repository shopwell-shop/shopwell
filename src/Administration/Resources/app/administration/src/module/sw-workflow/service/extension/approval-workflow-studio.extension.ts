/** @sw-package framework */
import { WORKFLOW_NODE_TYPES, WORKFLOW_NODE_PORTS, WORKFLOW_INPUT_TYPES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowStudioExtension } from 'src/core/workflow/workflow-studio.types';
import approvalWorkflowFieldProvider from '../field/approval-workflow-field.provider';
import ApprovalWorkflowEditingStrategy from '../editing/approval-workflow-editing.strategy';
import { createApprovalWorkflow, createExpenseWorkflow } from '../../util/approval-workflow-template.util';

/** Approval is installed through the same capability contract as business plugins. @private */
const approvalWorkflowStudioExtension: WorkflowStudioExtension = {
    name: 'core:approval-studio',
    fieldProviders: [{ name: WORKFLOW_INPUT_TYPES.APPROVAL_REQUEST, provide: approvalWorkflowFieldProvider }],
    nodes: [
        {
            type: WORKFLOW_NODE_TYPES.APPROVAL,
            version: 1,
            editing: new ApprovalWorkflowEditingStrategy(),
            presentation: {
                icon: 'regular-user-shield',
                tone: 'green',
                summaryKey: 'sw-workflow.summaries.approval',
                portLabelKeys: {
                    [WORKFLOW_NODE_PORTS.APPROVED]: 'sw-workflow.outcomes.approved',
                    [WORKFLOW_NODE_PORTS.REJECTED]: 'sw-workflow.outcomes.rejected',
                },
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
            available: (types) => types.some((type) => type.name === WORKFLOW_NODE_TYPES.APPROVAL && type.version === 1),
            create: createApprovalWorkflow,
        },
        {
            name: 'core:expense',
            titleKey: 'sw-workflow.templates.expense',
            descriptionKey: 'sw-workflow.templates.expenseDescription',
            icon: 'regular-flow',
            available: (types) =>
                [WORKFLOW_NODE_TYPES.APPROVAL, WORKFLOW_NODE_TYPES.CONDITION].every((name) =>
                    types.some((type) => type.name === name && type.version === 1),
                ),
            create: createExpenseWorkflow,
        },
    ],
};

/** @private */
export default approvalWorkflowStudioExtension;
