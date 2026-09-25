import '../store/overview-cards.store';

/**
 * @sw-package checkout
 */

Shopwell.ExtensionAPI.handle('uiModulePaymentOverviewCard', (componentConfig) => {
    if (componentConfig.component === 'sw-card') {
        componentConfig.component = 'mt-card';
    }

    Shopwell.Store.get('paymentOverviewCard').add(componentConfig);
});
