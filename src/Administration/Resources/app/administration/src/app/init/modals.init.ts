/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeModal(): void {
    // eslint-disable-next-line @typescript-eslint/require-await
    Shopwell.ExtensionAPI.handle('uiModalOpen', async (modalConfig, { _event_ }) => {
        const extension = Object.values(Shopwell.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        Shopwell.Store.get('modals').openModal({
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            baseUrl: extension.baseUrl,
            ...modalConfig,
        });
    });

    Shopwell.ExtensionAPI.handle('uiModalUpdate', (modalConfig, { _event_ }) => {
        const extension = Object.values(Shopwell.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        // Update the modal with the new configuration
        const currentModal = Shopwell.Store.get('modals').modals.findIndex((modal) => {
            return modal.locationId === modalConfig.locationId;
        });

        if (currentModal !== -1) {
            // Index is used to maintain Vue reactivity
            Shopwell.Store.get('modals').modals[currentModal] = {
                ...Shopwell.Store.get('modals').modals[currentModal],
                ...modalConfig,
                // Buttons explizit überschreiben, falls im modalConfig enthalten
                ...(modalConfig.buttons ? { buttons: modalConfig.buttons } : {}),
            };
        } else {
            console.error(`Modal with locationId "${modalConfig.locationId}" not found.`);
        }
    });

    Shopwell.ExtensionAPI.handle('uiModalClose', ({ locationId }) => {
        if (!locationId) {
            Shopwell.Store.get('modals').closeLastModalWithoutLocationId();
        } else {
            Shopwell.Store.get('modals').closeModal(locationId);
        }
    });
}
