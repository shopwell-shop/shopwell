/**
 * @sw-package framework
 */
import initializeApiServices from 'src/app/init-pre/api-services.init';

function removeServiceProvider(serviceName) {
    const container = Shopwell.Application.$container;
    const serviceContainer = container.nested.service;

    delete container.providerMap[`service.${serviceName}`];
    delete container.originalProviders[`service.${serviceName}`];
    delete serviceContainer.providerMap[serviceName];
    delete serviceContainer.originalProviders[serviceName];
    delete serviceContainer.container[serviceName];
}

function registerUserConfigServiceMock() {
    Shopwell.Service().register('userConfigService', () => ({
        search: jest.fn(() => Promise.resolve({ data: {} })),
        upsert: jest.fn(() => Promise.resolve()),
    }));
}

describe('src/app/init-pre/api-services.init.ts', () => {
    beforeEach(() => {
        removeServiceProvider('userConfigService');

        Shopwell._private.ApiServices = jest.fn(() => {
            const services = [];
            const serviceNames = [
                'aclApiService',
                'appActionButtonService',
                'appCmsBlocks',
                'appModulesService',
                'appUrlChangeService',
                'businessEventService',
                'cacheApiService',
                'calculate-price',
                'cartStoreService',
                'checkoutStoreService',
                'configService',
                'customSnippetApiService',
                'customerGroupRegistrationService',
                'customerValidationService',
                'documentService',
                'documentV2Service',
                'excludedSearchTermService',
                'extensionSdkService',
                'firstRunWizardService',
                'flowActionService',
                'importExportService',
                'integrationService',
                'knownIpsService',
                'languagePluginService',
                'mailService',
                'mediaFolderService',
                'mediaService',
                'messageQueueService',
                'notificationsService',
                'numberRangeService',
                'orderDocumentApiService',
                'orderStateMachineService',
                'orderService',
                'productExportService',
                'productStreamPreviewService',
                'promotionSyncService',
                'recommendationsService',
                'ruleConditionsConfigApiService',
                'salesChannelService',
                'scheduledTaskService',
                'searchService',
                'seoUrlTemplateService',
                'seoUrlService',
                'snippetSetService',
                'snippetService',
                'stateMachineService',
                'contextStoreService',
                'storeService',
                'syncService',
                'systemConfigApiService',
                'tagApiService',
                'updateService',
                'userActivityApiService',
                'userConfigService',
                'userInputSanitizeService',
                'userRecoveryService',
                'userValidationService',
                'userService',
                'shopIdChangeService',
            ];

            serviceNames.forEach((serviceName) => {
                const MockApiClass = class {
                    constructor() {
                        this.name = serviceName;
                    }
                };
                services.push(MockApiClass);
            });

            return services;
        });
    });

    afterEach(() => {
        removeServiceProvider('userConfigService');
        registerUserConfigServiceMock();
    });

    it('should initialize the api services', async () => {
        expect(Shopwell.Service('aclApiService')).toBeUndefined();
        expect(Shopwell.Service('appActionButtonService')).toBeUndefined();
        expect(Shopwell.Service('appCmsBlocks')).toBeUndefined();
        expect(Shopwell.Service('appModulesService')).toBeUndefined();
        expect(Shopwell.Service('appUrlChangeService')).toBeUndefined();
        expect(Shopwell.Service('businessEventService')).toBeUndefined();
        expect(Shopwell.Service('cacheApiService')).toBeUndefined();
        expect(Shopwell.Service('calculate-price')).toBeUndefined();
        expect(Shopwell.Service('cartStoreService')).toBeUndefined();
        expect(Shopwell.Service('checkoutStoreService')).toBeUndefined();
        expect(Shopwell.Service('configService')).toBeUndefined();
        expect(Shopwell.Service('customSnippetApiService')).toBeUndefined();
        expect(Shopwell.Service('customerGroupRegistrationService')).toBeUndefined();
        expect(Shopwell.Service('customerValidationService')).toBeUndefined();
        expect(Shopwell.Service('documentService')).toBeUndefined();
        expect(Shopwell.Service('documentV2Service')).toBeUndefined();
        expect(Shopwell.Service('excludedSearchTermService')).toBeUndefined();
        expect(Shopwell.Service('extensionSdkService')).toBeUndefined();
        expect(Shopwell.Service('firstRunWizardService')).toBeUndefined();
        expect(Shopwell.Service('flowActionService')).toBeUndefined();
        expect(Shopwell.Service('importExportService')).toBeUndefined();
        expect(Shopwell.Service('integrationService')).toBeUndefined();
        expect(Shopwell.Service('knownIpsService')).toBeUndefined();
        expect(Shopwell.Service('languagePluginService')).toBeUndefined();
        expect(Shopwell.Service('mailService')).toBeUndefined();
        expect(Shopwell.Service('mediaFolderService')).toBeUndefined();
        expect(Shopwell.Service('mediaService')).toBeUndefined();
        expect(Shopwell.Service('messageQueueService')).toBeUndefined();
        expect(Shopwell.Service('notificationsService')).toBeUndefined();
        expect(Shopwell.Service('numberRangeService')).toBeUndefined();
        expect(Shopwell.Service('orderDocumentApiService')).toBeUndefined();
        expect(Shopwell.Service('orderStateMachineService')).toBeUndefined();
        expect(Shopwell.Service('orderService')).toBeUndefined();
        expect(Shopwell.Service('productExportService')).toBeUndefined();
        expect(Shopwell.Service('productStreamPreviewService')).toBeUndefined();
        expect(Shopwell.Service('promotionSyncService')).toBeUndefined();
        expect(Shopwell.Service('recommendationsService')).toBeUndefined();
        expect(Shopwell.Service('ruleConditionsConfigApiService')).toBeUndefined();
        expect(Shopwell.Service('salesChannelService')).toBeUndefined();
        expect(Shopwell.Service('scheduledTaskService')).toBeUndefined();
        expect(Shopwell.Service('searchService')).toBeUndefined();
        expect(Shopwell.Service('seoUrlTemplateService')).toBeUndefined();
        expect(Shopwell.Service('seoUrlService')).toBeUndefined();
        expect(Shopwell.Service('snippetSetService')).toBeUndefined();
        expect(Shopwell.Service('snippetService')).toBeUndefined();
        expect(Shopwell.Service('stateMachineService')).toBeUndefined();
        expect(Shopwell.Service('contextStoreService')).toBeUndefined();
        expect(Shopwell.Service('storeService')).toBeUndefined();
        expect(Shopwell.Service('syncService')).toBeUndefined();
        expect(Shopwell.Service('systemConfigApiService')).toBeUndefined();
        expect(Shopwell.Service('tagApiService')).toBeUndefined();
        expect(Shopwell.Service('updateService')).toBeUndefined();
        expect(Shopwell.Service('userActivityApiService')).toBeUndefined();
        expect(Shopwell.Service('userConfigService')).toBeUndefined();
        expect(Shopwell.Service('userInputSanitizeService')).toBeUndefined();
        expect(Shopwell.Service('userRecoveryService')).toBeUndefined();
        expect(Shopwell.Service('userValidationService')).toBeUndefined();
        expect(Shopwell.Service('userService')).toBeUndefined();
        expect(Shopwell.Service('shopIdChangeService')).toBeUndefined();

        await initializeApiServices();

        expect(Shopwell.Service('aclApiService')).toBeDefined();
        expect(Shopwell.Service('appActionButtonService')).toBeDefined();
        expect(Shopwell.Service('appCmsBlocks')).toBeDefined();
        expect(Shopwell.Service('appModulesService')).toBeDefined();
        expect(Shopwell.Service('appUrlChangeService')).toBeDefined();
        expect(Shopwell.Service('businessEventService')).toBeDefined();
        expect(Shopwell.Service('cacheApiService')).toBeDefined();
        expect(Shopwell.Service('calculate-price')).toBeDefined();
        expect(Shopwell.Service('cartStoreService')).toBeDefined();
        expect(Shopwell.Service('checkoutStoreService')).toBeDefined();
        expect(Shopwell.Service('configService')).toBeDefined();
        expect(Shopwell.Service('customSnippetApiService')).toBeDefined();
        expect(Shopwell.Service('customerGroupRegistrationService')).toBeDefined();
        expect(Shopwell.Service('customerValidationService')).toBeDefined();
        expect(Shopwell.Service('documentService')).toBeDefined();
        expect(Shopwell.Service('documentV2Service')).toBeDefined();
        expect(Shopwell.Service('excludedSearchTermService')).toBeDefined();
        expect(Shopwell.Service('extensionSdkService')).toBeDefined();
        expect(Shopwell.Service('firstRunWizardService')).toBeDefined();
        expect(Shopwell.Service('flowActionService')).toBeDefined();
        expect(Shopwell.Service('importExportService')).toBeDefined();
        expect(Shopwell.Service('integrationService')).toBeDefined();
        expect(Shopwell.Service('knownIpsService')).toBeDefined();
        expect(Shopwell.Service('languagePluginService')).toBeDefined();
        expect(Shopwell.Service('mailService')).toBeDefined();
        expect(Shopwell.Service('mediaFolderService')).toBeDefined();
        expect(Shopwell.Service('mediaService')).toBeDefined();
        expect(Shopwell.Service('messageQueueService')).toBeDefined();
        expect(Shopwell.Service('notificationsService')).toBeDefined();
        expect(Shopwell.Service('numberRangeService')).toBeDefined();
        expect(Shopwell.Service('orderDocumentApiService')).toBeDefined();
        expect(Shopwell.Service('orderStateMachineService')).toBeDefined();
        expect(Shopwell.Service('orderService')).toBeDefined();
        expect(Shopwell.Service('productExportService')).toBeDefined();
        expect(Shopwell.Service('productStreamPreviewService')).toBeDefined();
        expect(Shopwell.Service('promotionSyncService')).toBeDefined();
        expect(Shopwell.Service('recommendationsService')).toBeDefined();
        expect(Shopwell.Service('ruleConditionsConfigApiService')).toBeDefined();
        expect(Shopwell.Service('salesChannelService')).toBeDefined();
        expect(Shopwell.Service('scheduledTaskService')).toBeDefined();
        expect(Shopwell.Service('searchService')).toBeDefined();
        expect(Shopwell.Service('seoUrlTemplateService')).toBeDefined();
        expect(Shopwell.Service('seoUrlService')).toBeDefined();
        expect(Shopwell.Service('snippetSetService')).toBeDefined();
        expect(Shopwell.Service('snippetService')).toBeDefined();
        expect(Shopwell.Service('stateMachineService')).toBeDefined();
        expect(Shopwell.Service('contextStoreService')).toBeDefined();
        expect(Shopwell.Service('storeService')).toBeDefined();
        expect(Shopwell.Service('syncService')).toBeDefined();
        expect(Shopwell.Service('systemConfigApiService')).toBeDefined();
        expect(Shopwell.Service('tagApiService')).toBeDefined();
        expect(Shopwell.Service('updateService')).toBeDefined();
        expect(Shopwell.Service('userActivityApiService')).toBeDefined();
        expect(Shopwell.Service('userConfigService')).toBeDefined();
        expect(Shopwell.Service('userInputSanitizeService')).toBeDefined();
        expect(Shopwell.Service('userRecoveryService')).toBeDefined();
        expect(Shopwell.Service('userValidationService')).toBeDefined();
        expect(Shopwell.Service('userService')).toBeDefined();
        expect(Shopwell.Service('shopIdChangeService')).toBeDefined();
    });
});
