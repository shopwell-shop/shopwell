/** @sw-package framework */
import '../index';
import WorkflowApprovalService from '../workflow-approval.service';
import approvalWorkflowStudioExtension from './approval-workflow-studio.extension';

/** @private */
Shopwell.Service().register('workflowApprovalService', () => {
    const service = new WorkflowApprovalService();
    service.registerSource({
        name: 'core:user',
        labelKey: 'sw-workflow.approval.namedUser',
        settingsComponent: 'sw-workflow-user-assignee-select',
        summaryComponent: 'sw-workflow-user-assignee-summary',
        supportedModes: ['core:single'],
    });
    service.registerSource({
        name: 'core:users',
        labelKey: 'sw-workflow.approval.namedUsers',
        settingsComponent: 'sw-workflow-users-assignee-select',
        summaryComponent: 'sw-workflow-users-assignee-summary',
        supportedModes: ['core:all', 'core:any'],
    });
    service.registerMode({
        name: 'core:all',
        labelKey: 'sw-workflow.approval.all',
        descriptionKey: 'sw-workflow.approval.allHint',
    });
    service.registerMode({
        name: 'core:any',
        labelKey: 'sw-workflow.approval.any',
        descriptionKey: 'sw-workflow.approval.anyHint',
    });
    service.registerMode({
        name: 'core:single',
        labelKey: 'sw-workflow.approval.single',
        descriptionKey: 'sw-workflow.approval.singleHint',
    });
    return service;
});

/** @private */
Shopwell.Service('workflowStudioService').register(approvalWorkflowStudioExtension);
/** @private */
Shopwell.Service('workflowReleaseActionService').register('core:approval', 'sw-workflow-start');
