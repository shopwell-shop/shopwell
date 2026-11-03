/**
 * @sw-package framework
 */

/** @private */
Shopwell.Component.register('sw-extension-sdk-module', () => import('./page/sw-extension-sdk-module'));

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopwell.Module.register('sw-extension-sdk', {
    type: 'core',
    name: 'sw-extension-sdk',
    title: 'sw-extension-sdk.general.mainMenuItemGeneral',
    description: 'sw-extension-sdk.general.moduleDescription',
    icon: 'regular-view-grid',
    color: 'var(--sw-color-module-neutral-default)',
    routePrefixPath: 'extension',

    routes: {
        index: {
            component: 'sw-extension-sdk-module',
            path: ':id/:back?',
            props: {
                default(route) {
                    const { id, back } = route.params;
                    return {
                        id,
                        back,
                    };
                },
            },
        },
    },

    // No navigation on purpose. The route needs the id of an extension (`:id/:back?`) and is
    // opened by the extension itself, so a main menu entry would offer a link that has nothing
    // to open - and only a placeholder between the extensions and the settings.
});
