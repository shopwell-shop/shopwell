/** @sw-package framework */
import {
    createTransferWorkflow,
    transferWorkflowAvailable,
} from 'src/module/sw-finance/service/payment-transfer-workflow.template';

/** Register finance contributions after module services have been initialized. @private */
export default function initializePaymentTransferWorkflow(): void {
    /** @private */
    Shopwell.Service('workflowTemplateService').register({
        name: 'core:payment-transfer',
        titleKey: 'sw-finance.transfer.workflowTemplate.title',
        descriptionKey: 'sw-finance.transfer.workflowTemplate.description',
        icon: 'regular-money-bill',
        available: transferWorkflowAvailable,
        create: (_types, { translate }) => {
            const label = (key: string) => translate(`sw-finance.transfer.workflowTemplate.${key}`);
            return createTransferWorkflow({
                finance: label('finance'),
                department: label('department'),
                reviewRequired: label('reviewRequired'),
                copy: label('copy'),
                amount: label('amount'),
                currency: label('currency'),
                reason: label('reason'),
                recipient: label('recipient'),
                paymentAccount: label('paymentAccount'),
            });
        },
    });

    /** @private */
    Shopwell.Service('workflowReleaseActionService').register('payment:transfer', 'sw-finance-transfer-workflow-start');
}
