import '../store/extension-component-sections.store';

/**
 * @sw-package framework
 *
 * @private
 */
export default function initializeExtensionComponentSections(): void {
    // Handle incoming ExtensionComponentRenderer requests from the ExtensionAPI
    Shopwell.ExtensionAPI.handle('uiComponentSectionRenderer', (componentConfig, additionalInformation) => {
        const extension = Object.values(Shopwell.Store.get('extensions').extensionsState).find((ext) =>
            ext.baseUrl.startsWith(additionalInformation._event_.origin),
        );

        if (!extension) {
            throw new Error(`Extension with the origin "${additionalInformation._event_.origin}" not found.`);
        }

        Shopwell.Store.get('extensionComponentSections').addSection({
            ...componentConfig,
            extensionName: extension.name,
        });
    });
}
