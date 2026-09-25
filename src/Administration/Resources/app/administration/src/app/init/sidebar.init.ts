/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeSidebar(): void {
    // eslint-disable-next-line @typescript-eslint/require-await
    Shopwell.ExtensionAPI.handle('uiSidebarAdd', async (sidebarConfig, { _event_ }) => {
        const extension = Object.values(Shopwell.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(_event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${_event_.origin}" not found.`);
        }

        // create sidebar store
        Shopwell.Store.get('sidebar').addSidebar({
            baseUrl: extension.baseUrl,
            active: false,
            ...sidebarConfig,
        });
    });

    Shopwell.ExtensionAPI.handle('uiSidebarClose', ({ locationId }) => {
        // Same close path as the panel's close button, so the animation plays too
        Shopwell.Store.get('sidebar').requestCloseSidebar(locationId);
    });

    Shopwell.ExtensionAPI.handle('uiSidebarSetActive', ({ locationId }: { locationId: string }) => {
        Shopwell.Store.get('sidebar').setActiveSidebar(locationId);
    });

    Shopwell.ExtensionAPI.handle('uiSidebarRemove', ({ locationId }) => {
        Shopwell.Store.get('sidebar').removeSidebar(locationId);
    });
}
