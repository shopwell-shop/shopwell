/**
 * @sw-package framework
 */

/* Is covered by E2E tests */
/* istanbul ignore file */
import type { Router } from 'vue-router';

/**
 * @private
 */
export const _windowLocationHelpers = {
    reload(): void {
        window.location.reload();
    },
    navigate(url: string): void {
        window.location.href = url;
    },
};

/**
 * @private
 */
export default function initializeWindow(): void {
    // Handle incoming window requests from the ExtensionAPI
    Shopwell.ExtensionAPI.handle('windowReload', () => {
        _windowLocationHelpers.reload();
    });

    Shopwell.ExtensionAPI.handle('windowRedirect', ({ newTab, url }) => {
        if (newTab) {
            window.open(url, '_blank');
        } else {
            _windowLocationHelpers.navigate(url);
        }
    });

    Shopwell.ExtensionAPI.handle('windowRouterPush', async ({ name, params, path, replace }) => {
        const $router = Shopwell.Application.view?.router as unknown as Router;

        if (!$router) {
            return;
        }

        await $router.push({
            // @ts-expect-error
            name: name && name.length > 0 ? name : undefined,
            params,
            path: path && path.length > 0 ? path : '',
            replace: replace ?? false,
        });
    });

    Shopwell.ExtensionAPI.handle('windowRouterGetPath', () => {
        const $router = Shopwell.Application.view?.router as unknown as Router;

        if (!$router) {
            return '';
        }

        return $router.currentRoute?.value?.fullPath || '';
    });
}
