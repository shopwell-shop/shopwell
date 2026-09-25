/**
 * @sw-package framework
 * @private
 */
export default function initializeTracking(): Promise<void> {
    Shopwell.Telemetry.initialize();

    return Promise.resolve();
}
