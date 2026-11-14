/** @sw-package framework */
import type { WorkflowDocument, WorkflowNodeTypeSpecification } from 'src/core/workflow/workflow.types';
import zh from '../snippet/zh.json';
import { createTransferWorkflow, transferWorkflowAvailable } from './payment-transfer-workflow.template';

type TemplateRegistry = {
    register(template: {
        name: string;
        titleKey: string;
        descriptionKey: string;
        icon: string;
        available: (types: WorkflowNodeTypeSpecification[]) => boolean;
        create: () => WorkflowDocument;
    }): void;
};

Shopwell.Application.addServiceProviderDecorator('workflowTemplateService', (service: TemplateRegistry) => {
    service.register({
        name: 'core:payment-transfer',
        titleKey: 'sw-finance.transfer.workflowTemplate.title',
        descriptionKey: 'sw-finance.transfer.workflowTemplate.description',
        icon: 'regular-money-bill',
        available: transferWorkflowAvailable,
        create: () => createTransferWorkflow(zh['sw-finance'].transfer.workflowTemplate),
    });
    return service;
});

Shopwell.Application.addServiceProviderDecorator(
    'workflowReleaseActionService',
    (service: { register: (name: string, component: string) => void }) => {
        service.register('payment:transfer', 'sw-finance-transfer-workflow-start');
        return service;
    },
);
