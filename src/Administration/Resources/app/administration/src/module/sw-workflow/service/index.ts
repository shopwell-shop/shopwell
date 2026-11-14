/** @sw-package framework */
import WorkflowRequestFormService from './workflow-request-form.service';
import WorkflowReleaseActionService from './workflow-release-action.service';
import WorkflowFieldService from './workflow-field.service';
import WorkflowLayoutService from './workflow-layout.service';
import WorkflowTemplateService from './workflow-template.service';
import WorkflowNodeEditingService from './workflow-node-editing.service';
import WorkflowNodePresentationService from './workflow-node-presentation.service';
import WorkflowExtensionService from './workflow-extension.service';
import WorkflowStudioService from './workflow-studio.service';
import coreWorkflowStudioExtension from './extension/core-workflow-studio.extension';
import conditionWorkflowStudioExtension from './extension/condition-workflow-studio.extension';

/** @public */
Shopwell.Service().register('workflowExtensionService', () => new WorkflowExtensionService());

/** @private */
Shopwell.Service().register('workflowNodePresentationService', () => new WorkflowNodePresentationService());

/** @private */
Shopwell.Service().register('workflowNodeEditingService', () => new WorkflowNodeEditingService());

/** @private */
Shopwell.Service().register('workflowTemplateService', () => new WorkflowTemplateService());

/** @private */
Shopwell.Service().register('workflowLayoutService', () => new WorkflowLayoutService());

/** @public */
Shopwell.Service().register('workflowFieldService', () => new WorkflowFieldService());

/** @public */
Shopwell.Service().register('workflowStudioService', () => {
    const service = new WorkflowStudioService({
        extensions: Shopwell.Service('workflowExtensionService'),
        presentations: Shopwell.Service('workflowNodePresentationService'),
        editing: Shopwell.Service('workflowNodeEditingService'),
        templates: Shopwell.Service('workflowTemplateService'),
        layouts: Shopwell.Service('workflowLayoutService'),
        fields: Shopwell.Service('workflowFieldService'),
    });
    service.register(coreWorkflowStudioExtension);
    service.register(conditionWorkflowStudioExtension);

    return service;
});

/** @public */
Shopwell.Service().register('workflowReleaseActionService', () => {
    const service = new WorkflowReleaseActionService();
    service.register('core:workflow', 'sw-workflow-start');
    return service;
});

/** @public */
Shopwell.Service().register('workflowRequestFormService', () => {
    const service = new WorkflowRequestFormService();
    service.registerDataSource({ name: 'core:currency', labelKey: 'sw-workflow.form.currencySource' });
    return service;
});

// Install built-ins once; consumers can resolve the individual registries in any order.
Shopwell.Service('workflowStudioService');
