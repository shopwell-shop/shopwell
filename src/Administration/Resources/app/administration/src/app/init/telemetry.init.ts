import type { telemetryDispatch } from '@shopwell-ag/meteor-admin-sdk/es/telemetry';
import type { TrackableType } from '../../core/telemetry/types';

/**
 * @sw-package framework
 * @private
 */
export default function initializeTelemetry(): void {
    Shopwell.ExtensionAPI.handle('telemetryDispatch', (payload: Omit<telemetryDispatch, 'responseType'>, additionalInfo) => {
        const event = additionalInfo._event_;
        const sourceWindow = event.source != null ? (event.source as Window) : undefined;

        Shopwell.Telemetry.track({
            eventName: payload.event,
            ...(payload.data as Record<string, TrackableType>),
            source: Shopwell.Utils.extension.getExtensionNameByOrigin(event.origin, sourceWindow) ?? 'unknown',
        });
    });
}
