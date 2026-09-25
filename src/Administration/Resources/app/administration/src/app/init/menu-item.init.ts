/**
 * @sw-package framework
 *
 * @private
 */
export default function initMenuItems(): void {
    Shopwell.ExtensionAPI.handle('menuItemAdd', async (menuItemConfig, additionalInformation) => {
        const extension = Object.values(Shopwell.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        await Shopwell.Store.get('extensionSdkModules')
            .addModule({
                heading: menuItemConfig.label,
                locationId: menuItemConfig.locationId,
                displaySearchBar: menuItemConfig.displaySearchBar!,
                displaySmartBar: menuItemConfig.displaySmartBar,
                baseUrl: extension.baseUrl,
            })
            .then((moduleId) => {
                if (typeof moduleId !== 'string') {
                    return;
                }

                Shopwell.Store.get('menuItem').addMenuItem({
                    ...menuItemConfig,
                    moduleId,
                });
            });
    });

    Shopwell.ExtensionAPI.handle('menuCollapse', () => {
        Shopwell.Store.get('adminMenu').collapseSidebar();
    });

    Shopwell.ExtensionAPI.handle('menuExpand', () => {
        Shopwell.Store.get('adminMenu').expandSidebar();
    });
}
