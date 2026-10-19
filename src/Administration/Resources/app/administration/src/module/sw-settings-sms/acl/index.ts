/**
 * @sw-package framework
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'sms_gateway',
    roles: {
        viewer: {
            privileges: ['sms_gateway:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['sms_gateway:update'],
            dependencies: ['sms_gateway.viewer'],
        },
        creator: {
            privileges: ['sms_gateway:create'],
            dependencies: ['sms_gateway.viewer', 'sms_gateway.editor'],
        },
        deleter: {
            privileges: ['sms_gateway:delete'],
            dependencies: ['sms_gateway.viewer'],
        },
    },
});

/**
 * The mapping that lands a logical template on the concrete template of a provider is edited on the
 * template, so its privileges belong to this feature - a role that may change a template may change
 * where it goes.
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'sms_template',
    roles: {
        viewer: {
            privileges: ['sms_template:read', 'sms_template_mapping:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['sms_template:update', 'sms_template_mapping:update'],
            dependencies: ['sms_template.viewer'],
        },
        creator: {
            privileges: ['sms_template:create', 'sms_template_mapping:create'],
            dependencies: ['sms_template.viewer', 'sms_template.editor'],
        },
        deleter: {
            privileges: ['sms_template:delete', 'sms_template_mapping:delete'],
            dependencies: ['sms_template.viewer'],
        },
    },
});

/**
 * The record of what went out is read only: nothing in the administration changes an attempt that
 * was made, so a role either reads the log or does not.
 */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'sms_delivery',
    roles: {
        viewer: {
            privileges: ['sms_delivery:read'],
            dependencies: [],
        },
        editor: {
            privileges: [],
            dependencies: ['sms_delivery.viewer'],
        },
        creator: {
            privileges: [],
            dependencies: ['sms_delivery.viewer'],
        },
        deleter: {
            privileges: [],
            dependencies: ['sms_delivery.viewer'],
        },
    },
});
