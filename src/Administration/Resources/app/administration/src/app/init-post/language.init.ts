/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initLanguageService() {
    Shopwell.Application.addServiceProviderMiddleware('repositoryFactory', (repositoryFactory) => {
        // load the language when repositoryFactory is created
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        Shopwell.Application.getContainer('service').languageAutoFetchingService;

        return repositoryFactory;
    });
}
