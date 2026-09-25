/**
 * @sw-package framework
 */
import type { SubContainer } from '../../../global.types';
import ShopwellServicesService from './shopwell-services.service';
import ServiceRegistryClient from './service-registry-client';

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        shopwellServicesService: ShopwellServicesService;
        serviceRegistryClient: ServiceRegistryClient;
    }
}

/**
 * @private
 */
Shopwell.Service().register('shopwellServicesService', () => {
    return new ShopwellServicesService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service('loginService'),
        Shopwell.Service('systemConfigApiService'),
    );
});

/**
 * @private
 */
Shopwell.Service().register('serviceRegistryClient', () => {
    return new ServiceRegistryClient(Shopwell.Context.api.serviceRegistryUrl!);
});
