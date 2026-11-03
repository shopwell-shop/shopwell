/**
 * @sw-package checkout
 */

/**
 * Reads the `allowSave` computed of the component a shortcut was registered on.
 *
 * The shortcut registry calls `active()` without typing its receiver, so the component has no shape
 * there. Taking `unknown` keeps the call site free of an assertion and keeps the check in one place
 * instead of once per page.
 *
 * @private
 */
export function shortcutAllowsSave(component: unknown): boolean {
    return Boolean((component as { allowSave?: boolean }).allowSave);
}
