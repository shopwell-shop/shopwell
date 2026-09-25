/**
 * @sw-package inventory
 */
import ProductIndexService from '../service/productIndex.api.service';
import LiveSearchApiService from '../service/livesearch.api.service';
import ExcludedSearchTermService from '../../../core/service/api/excludedSearchTerm.api.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('productIndexService', () => {
    return new ProductIndexService(Shopwell.Application.getContainer('init').httpClient, Shopwell.Service('loginService'));
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('liveSearchService', () => {
    return new LiveSearchApiService(Shopwell.Application.getContainer('init').httpClient, Shopwell.Service('loginService'));
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Service().register('excludedSearchTermService', () => {
    return new ExcludedSearchTermService(
        Shopwell.Application.getContainer('init').httpClient,
        Shopwell.Service('loginService'),
    );
});
