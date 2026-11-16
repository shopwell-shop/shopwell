/** @sw-package framework */
import { WORKFLOW_ASSIGNEE_SOURCES, WORKFLOW_APPROVAL_MODES } from 'src/core/workflow/workflow-node.constants';
import '../index';
import WorkflowApprovalService from '../workflow-approval.service';
import approvalWorkflowStudioExtension from './approval-workflow-studio.extension';

/** @private */
Shopwell.Service().register('workflowApprovalService', () => {
    const service = new WorkflowApprovalService();
    service.registerSource({
        name: WORKFLOW_ASSIGNEE_SOURCES.USER,
        labelKey: 'sw-workflow.approval.namedUser',
        settingsComponent: 'sw-workflow-user-assignee-select',
        summaryComponent: 'sw-workflow-user-assignee-summary',
        supportedModes: [WORKFLOW_APPROVAL_MODES.SINGLE],
    });
    service.registerSource({
        name: WORKFLOW_ASSIGNEE_SOURCES.USERS,
        labelKey: 'sw-workflow.approval.namedUsers',
        settingsComponent: 'sw-workflow-users-assignee-select',
        summaryComponent: 'sw-workflow-users-assignee-summary',
        supportedModes: [WORKFLOW_APPROVAL_MODES.ALL, WORKFLOW_APPROVAL_MODES.ANY],
    });
    service.registerSource({
        name: WORKFLOW_ASSIGNEE_SOURCES.DEPARTMENT_LEADERS,
        labelKey: 'sw-workflow.approval.departmentLeaders',
        settingsComponent: 'sw-workflow-organization-assignee-settings',
        summaryComponent: 'sw-workflow-organization-assignee-summary',
        supportedModes: [WORKFLOW_APPROVAL_MODES.ALL, WORKFLOW_APPROVAL_MODES.ANY],
    });
    service.registerMode({
        name: WORKFLOW_APPROVAL_MODES.ALL,
        labelKey: 'sw-workflow.approval.all',
        descriptionKey: 'sw-workflow.approval.allHint',
    });
    service.registerMode({
        name: WORKFLOW_APPROVAL_MODES.ANY,
        labelKey: 'sw-workflow.approval.any',
        descriptionKey: 'sw-workflow.approval.anyHint',
    });
    service.registerMode({
        name: WORKFLOW_APPROVAL_MODES.SINGLE,
        labelKey: 'sw-workflow.approval.single',
        descriptionKey: 'sw-workflow.approval.singleHint',
    });
    return service;
});

/** @private */
Shopwell.Service('workflowStudioService').register(approvalWorkflowStudioExtension);
/** @private */
Shopwell.Service('workflowReleaseActionService').register('core:approval', 'sw-workflow-start');
