/** @sw-package framework */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'approval_flow',
    roles: {
        viewer: { privileges: ['approval_flow:access'], dependencies: [] },
        editor: { privileges: ['approval_flow_task:decide'], dependencies: ['approval_flow.viewer'] },
    },
});

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'approval_flow_definition',
    roles: {
        viewer: {
            privileges: ['approval_flow_definition:read', 'approval_flow_draft:read', 'approval_flow_release:read'],
            dependencies: [],
        },
    },
});

/** @private */
export {};
