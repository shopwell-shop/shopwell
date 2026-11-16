/** @sw-package framework */
import type { WorkflowTemplate } from 'src/core/workflow/workflow-studio.types';
import { createTransferWorkflow, transferWorkflowAvailable } from './payment-transfer-workflow.template';

type TemplateRegistry = {
    register(template: WorkflowTemplate): () => void;
};

Shopwell.Application.addServiceProviderDecorator('workflowTemplateService', (service: TemplateRegistry) => {
    service.register({
        name: 'core:payment-transfer',
        titleKey: 'sw-finance.transfer.workflowTemplate.title',
        descriptionKey: 'sw-finance.transfer.workflowTemplate.description',
        icon: 'regular-money-bill',
        available: transferWorkflowAvailable,
        create: (_types, { translate }) => {
            const label = (key: string) => translate(`sw-finance.transfer.workflowTemplate.${key}`);
            return createTransferWorkflow({
                finance: label('finance'),
                manager: label('manager'),
                foreignReview: label('foreignReview'),
                foreignCurrency: label('foreignCurrency'),
                threshold: label('threshold'),
                amount: label('amount'),
                currency: label('currency'),
                reason: label('reason'),
                recipient: label('recipient'),
                paymentAccount: label('paymentAccount'),
            });
        },
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
