/**
 * @sw-package framework
 *
 * @private
 */
import '../store/action-buttons.store';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeActionButtons(): void {
    Shopwell.ExtensionAPI.handle('actionButtonAdd', (configuration) => {
        Shopwell.Store.get('actionButtons').add(configuration);
    });
}
