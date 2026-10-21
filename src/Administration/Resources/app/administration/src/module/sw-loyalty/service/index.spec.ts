/**
 * @sw-package checkout
 */

import LoyaltyApiService from './loyalty.api.service';
import './index';

describe('src/module/sw-loyalty/service/index', () => {
    it('registers the loyalty api service in the service container', () => {
        const container = Shopwell.Application.getContainer('service');

        expect(container).toHaveProperty('loyaltyApiService');
    });

    it('builds the service with the http client and the login service of the container', () => {
        const service = Shopwell.Application.getContainer('service').loyaltyApiService;

        expect(service).toBeInstanceOf(LoyaltyApiService);
    });
});
