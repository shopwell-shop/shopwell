/**
 * @sw-package checkout
 */
import type { SubContainer } from 'src/global.types';
import LoyaltyApiService from './loyalty.api.service';

const { Application } = Shopwell;

declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        loyaltyApiService: LoyaltyApiService;
    }
}

/**
 * @private
 */
Application.addServiceProvider('loyaltyApiService', (container) => {
    return new LoyaltyApiService(Shopwell.Application.getContainer('init').httpClient, container.loginService);
});
