/**
 * @sw-package framework
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'dictionary',
    roles: {
        viewer: {
            privileges: [
                'dictionary_type:read',
                'dictionary_entry:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['dictionary_type:update', 'dictionary_entry:update'],
            dependencies: ['dictionary.viewer'],
        },
        creator: {
            privileges: ['dictionary_type:create', 'dictionary_entry:create'],
            dependencies: ['dictionary.viewer', 'dictionary.editor'],
        },
        deleter: {
            privileges: ['dictionary_type:delete', 'dictionary_entry:delete'],
            dependencies: ['dictionary.viewer'],
        },
    },
});
