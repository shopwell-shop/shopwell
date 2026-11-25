/** @sw-package framework */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: null,
    key: 'approval_flow',
    roles: {
        viewer: { privileges: ['approval_flow:access'], dependencies: [] },
    },
});

/** @private */
export {};
