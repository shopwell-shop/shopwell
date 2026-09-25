/*
 * @sw-package inventory
 */

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'product_manufacturer',
    roles: {
        viewer: {
            privileges: [
                'product_manufacturer:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                Shopwell.Service('privileges').getPrivileges('media.viewer'),
                'user_config:read',
                'user_config:create',
                'user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['product_manufacturer:update', Shopwell.Service('privileges').getPrivileges('media.creator')],
            dependencies: ['product_manufacturer.viewer'],
        },
        creator: {
            privileges: ['product_manufacturer:create'],
            dependencies: ['product_manufacturer.viewer', 'product_manufacturer.editor'],
        },
        deleter: {
            privileges: ['product_manufacturer:delete'],
            dependencies: ['product_manufacturer.viewer'],
        },
    },
});
