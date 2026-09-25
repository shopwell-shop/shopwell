/**
 * @sw-package checkout
 */
import PromotionCodeApiService from '../service/promotion-code.api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('promotionCodeApiService', () => {
    return new PromotionCodeApiService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service('loginService'),
    );
});
