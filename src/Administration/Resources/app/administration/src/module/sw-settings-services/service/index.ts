/**
 * @sw-package framework
 */
import type { SubContainer } from '../../../global.types';
import ShopwellServicesService from './shopware-services.service';
import ServiceRegistryClient from './service-registry-client';

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        shopwareServicesService: ShopwellServicesService;
        serviceRegistryClient: ServiceRegistryClient;
    }
}

/**
 * @private
 */
Shopwell.Service().register('shopwareServicesService', () => {
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
