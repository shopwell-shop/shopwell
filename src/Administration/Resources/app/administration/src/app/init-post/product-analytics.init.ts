/**
 * @sw-package framework
 */
import { computed, watch, type WatchHandle } from 'vue';
import useConsentStore from 'src/core/consent/consent.store';
import { GatewayClient } from 'src/core/telemetry/product-analytics/gateway-client';
import createConsentEventHandler from 'src/core/telemetry/product-analytics/consent-event-handler';
import createTelemetryEventHandler from 'src/core/telemetry/product-analytics/telemetry-event-handler';
import { trackSessionSnapshot } from 'src/app/service/product-analytics-session-snapshot.service';

/**
 * @private
 */
export default async function (): Promise<WatchHandle | undefined> {
    const analyticsGatewayUrl = Shopwell.Store.get('context').app.analyticsGatewayUrl;

    if (!analyticsGatewayUrl) {
        return;
    }

    /*
     * register consent event handler
     */

    const gatewayClient = new GatewayClient(analyticsGatewayUrl, await getDefaultLanguageName());

    const consentEventHandler = createConsentEventHandler(gatewayClient);

    // eslint-disable-next-line listeners/no-missing-remove-event-listener
    Shopwell.Utils.EventBus.on('consent', consentEventHandler);

    /*
     * initialize product analytics
     */
    const consentStore = useConsentStore();
    const isTelemetryConsentAccepted = computed((): boolean => {
        try {
            return consentStore.isAccepted('product_analytics');
        } catch {
            return false;
        }
    });

    gatewayClient.setOptOut(true);

    const eventHandlers = createTelemetryEventHandler(gatewayClient);

    return watch(
        isTelemetryConsentAccepted,
        (newValue: boolean) => {
            if (newValue) {
                if (!gatewayClient.isInitialized) {
                    gatewayClient.init();
                }

                gatewayClient.setOptOut(false);
                Shopwell.Utils.EventBus.on('telemetry', eventHandlers);

                Shopwell.Telemetry.identify();
                void Shopwell.Application.viewInitialized.then(trackSessionSnapshot);
            } else {
                if (!gatewayClient.isInitialized) {
                    return;
                }

                gatewayClient.setOptOut(true);
                Shopwell.Utils.EventBus.off('telemetry', eventHandlers);
                void gatewayClient.flushWithoutRetry().finally(() => {
                    deleteUser(gatewayClient);
                    gatewayClient.clearStorage();
                });
            }
        },
        { immediate: true },
    );
}

function deleteUser(client: GatewayClient) {
    const shopId = Shopwell.Store.get('context').app.config.shopId;
    const userId = Shopwell.Store.get('session').currentUser?.id ?? null;

    if (typeof shopId === 'string' && typeof userId === 'string') {
        client.deleteUser(shopId, userId);
    }
}

async function getDefaultLanguageName(): Promise<string> {
    const languageRepository = Shopwell.Service('repositoryFactory').create('language');

    try {
        const defaultLanguage = await languageRepository.get(Shopwell.Context.api.systemLanguageId!);

        return defaultLanguage!.name;
    } catch {
        return 'N/A';
    }
}
