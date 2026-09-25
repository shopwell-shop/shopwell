/**
 * @sw-package framework
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeApiServices() {
    // // Add custom api service providers
    const apiServices = Shopwell._private.ApiServices();

    // Register all api services
    apiServices.forEach((ApiService) => {
        const factoryContainer = Shopwell.Application.getContainer('factory');
        const initContainer = Shopwell.Application.getContainer('init');

        const apiServiceFactory = factoryContainer.apiService;
        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        const service = new ApiService(initContainer.httpClient, Shopwell.Service('loginService'));
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        const serviceName = service.name as keyof ServiceContainer;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        apiServiceFactory.register(serviceName, service);

        Shopwell.Application.addServiceProvider(serviceName, () => {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return service;
        });
    });
}
