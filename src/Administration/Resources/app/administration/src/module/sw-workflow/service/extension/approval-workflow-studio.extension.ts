/** @sw-package framework */
import { WORKFLOW_NODE_TYPES, WORKFLOW_NODE_PORTS, WORKFLOW_INPUT_TYPES } from 'src/core/workflow/workflow-node.constants';
import type { WorkflowStudioExtension } from 'src/core/workflow/workflow-studio.types';
import approvalWorkflowFieldProvider from '../field/approval-workflow-field.provider';
import StructuredWorkflowEditingStrategy from '../editing/structured-workflow-editing.strategy';
import ApprovalWorkflowEditingStrategy from '../editing/approval-workflow-editing.strategy';
import { createApprovalWorkflow } from '../../util/approval-workflow-template.util';

/** Approval is installed through the same capability contract as business plugins. @private */
const approvalWorkflowStudioExtension: WorkflowStudioExtension = {
    name: 'core:approval-studio',
    fieldProviders: [{ name: WORKFLOW_INPUT_TYPES.APPROVAL_REQUEST, provide: approvalWorkflowFieldProvider }],
    nodes: [
        {
            type: WORKFLOW_NODE_TYPES.COPY,
            version: 1,
            editing: new StructuredWorkflowEditingStrategy(),
            presentation: {
                icon: 'regular-envelope',
                tone: 'blue',
                labelKey: 'sw-workflow.types.core.copy',
                settingsComponent: 'sw-workflow-copy-settings',
                nodeComponent: 'sw-workflow-copy-node',
            },
        },
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
    ],
};

/** @private */
export default approvalWorkflowStudioExtension;
