/**
 * @sw-package framework
 *
 * @private
 */
export default function initMainModules(): void {
    Shopwell.ExtensionAPI.handle('mainModuleAdd', async (mainModuleConfig, additionalInformation) => {
        const extensionName = Object.keys(Shopwell.Store.get('extensions').extensionsState).find((key) =>
            Shopwell.Store.get('extensions').extensionsState[key].baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extensionName) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        const extension = Shopwell.Store.get('extensions').extensionsState?.[extensionName];

        await Shopwell.Store.get('extensionSdkModules')
            .addModule({
                heading: mainModuleConfig.heading,
                locationId: mainModuleConfig.locationId,
                displaySearchBar: mainModuleConfig.displaySearchBar ?? true,
                baseUrl: extension.baseUrl,
            })
            .then((moduleId) => {
                if (typeof moduleId !== 'string') {
                    return;
                }

                Shopwell.Store.get('extensionMainModules').addMainModule({
                    extensionName,
                    moduleId,
                });
            });
    });

    Shopwell.ExtensionAPI.handle('smartBarButtonAdd', (configuration) => {
        Shopwell.Store.get('extensionSdkModules').addSmartBarButton(configuration);
    });

    Shopwell.ExtensionAPI.handle('smartBarHide', (configuration) => {
        Shopwell.Store.get('extensionSdkModules').addHiddenSmartBar(configuration.locationId);
    });
}
