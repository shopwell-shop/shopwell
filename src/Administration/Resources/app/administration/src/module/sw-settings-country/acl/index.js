/**
 * @sw-package fundamentals@discovery
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'country',
    roles: {
        viewer: {
            privileges: [
                'country:read',
                'country_state:read',
                'country_city:read',
                'country_district:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                'currency:read',
                'user_config:read',
                'customer:read',
                'salutation:read',
                'customer_address:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'country:update',
                'country_state:read',
                'country_state:create',
                'country_state:update',
                'country_state:delete',
                'country_city:read',
                'country_city:create',
                'country_city:update',
                'country_city:delete',
                'country_district:read',
                'country_district:create',
                'country_district:update',
                'country_district:delete',
            ],
            dependencies: ['country.viewer'],
        },
        creator: {
            privileges: ['country:create'],
            dependencies: ['country.viewer', 'country.editor'],
        },
        deleter: {
            privileges: ['country:delete'],
            dependencies: ['country.viewer'],
        },
    },
});
