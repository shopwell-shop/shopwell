/**
 * @sw-package checkout
 *
 * `$device` is installed as a global property by a plain-JS helper, so no component type declares it.
 * Reading it through one typed function keeps the assertion in a single place instead of repeating it
 * in every page that wants to print the platform modifier of a keyboard shortcut.
 */

/**
 * @private
 */
interface DeviceHelper {
    getSystemKey: () => string;
}

/**
 * @private
 */
export function systemKey(component: unknown): string {
    return (component as { $device: DeviceHelper }).$device.getSystemKey();
}
