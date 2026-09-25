/**
 * @sw-package framework
 */

type ServiceObject = {
    get: <SN extends keyof ServiceContainer>(serviceName: SN) => ServiceContainer[SN];
    list: () => (keyof ServiceContainer)[];
    register: typeof Shopwell.Application.addServiceProvider;
    registerMiddleware: typeof Shopwell.Application.addServiceProviderMiddleware;
    registerDecorator: typeof Shopwell.Application.addServiceProviderDecorator;
};

/**
 * Return the ServiceObject (Shopwell.Service().myService)
 * or direct access the services (Shopwell.Service('myService')
 */
function serviceAccessor<SN extends keyof ServiceContainer>(serviceName: SN): ServiceContainer[SN];
function serviceAccessor(): ServiceObject;
function serviceAccessor<SN extends keyof ServiceContainer>(serviceName?: SN): ServiceContainer[SN] | ServiceObject {
    if (serviceName) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return Shopwell.Application.getContainer('service')[serviceName];
    }

    const serviceObject: ServiceObject = {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        get: (name) => Shopwell.Application.getContainer('service')[name],
        list: () => Shopwell.Application.getContainer('service').$list(),
        register: (name, service) => Shopwell.Application.addServiceProvider(name, service),
        registerMiddleware: (...args) => Shopwell.Application.addServiceProviderMiddleware(...args),
        registerDecorator: (...args) => Shopwell.Application.addServiceProviderDecorator(...args),
    };

    return serviceObject;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default serviceAccessor;
