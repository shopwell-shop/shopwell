/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeMediaModal(): void {
    Shopwell.ExtensionAPI.handle('uiMediaModalOpen', (modalConfig) => {
        Shopwell.Store.get('mediaModal').openModal(modalConfig);
    });

    Shopwell.ExtensionAPI.handle('uiMediaModalOpenSaveMedia', (saveModalConfig) => {
        Shopwell.Store.get('mediaModal').openSaveModal(saveModalConfig);
    });
}
