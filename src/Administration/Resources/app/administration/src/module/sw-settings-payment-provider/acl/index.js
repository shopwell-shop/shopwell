/**
 * @sw-package framework
 *
 * The privileges of the payment interfaces.
 *
 * An interface is configured once for the whole platform: its credentials and the parameters of its
 * channels are shared by every app, so editing them is a sensitive operation and gets its own
 * privileges. The credentials of a single channel are kept apart, because a shop may hand out the
 * channel configuration without handing out the merchant account itself.
 */
Shopwell.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'payment_provider_config',
        roles: {
            viewer: {
                privileges: ['payment_provider_config:read'],
                dependencies: [],
            },
            editor: {
                privileges: ['payment_provider_config:update'],
                dependencies: ['payment_provider_config.viewer'],
            },
            creator: {
                privileges: ['payment_provider_config:create'],
                dependencies: ['payment_provider_config.viewer', 'payment_provider_config.editor'],
            },
            deleter: {
                privileges: ['payment_provider_config:delete'],
                dependencies: ['payment_provider_config.viewer'],
            },
        },
    })
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'settings',
        key: 'payment_channel_config',
        roles: {
            viewer: {
                privileges: ['payment_channel_config:read'],
                dependencies: [],
            },
            editor: {
                privileges: ['payment_channel_config:update'],
                dependencies: ['payment_channel_config.viewer'],
            },
            creator: {
                privileges: ['payment_channel_config:create'],
                dependencies: ['payment_channel_config.viewer', 'payment_channel_config.editor'],
            },
            deleter: {
                privileges: ['payment_channel_config:delete'],
                dependencies: ['payment_channel_config.viewer'],
            },
        },
    });
