/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeActions(): void {
    Shopwell.ExtensionAPI.handle('actionExecute', async (actionConfiguration, additionalInformation) => {
        const extensionName = Object.keys(Shopwell.Store.get('extensions').extensionsState).find((key) =>
            Shopwell.Store.get('extensions').extensionsState[key].baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extensionName) {
            throw new Error(
                `Could not find an extension with the given event origin "${additionalInformation._event_.origin}"`,
            );
        }

        await Shopwell.Service('extensionSdkService').runAction(
            {
                url: actionConfiguration.url,
                entity: actionConfiguration.entity,
                action: Shopwell.Utils.createId(),
                appName: extensionName,
            },
            actionConfiguration.entityIds,
        );
    });
}
