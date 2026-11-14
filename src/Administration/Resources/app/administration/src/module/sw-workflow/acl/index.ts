/** @sw-package framework */
Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'workflow',
    roles: {
        publisher: { privileges: ['workflow:publish'], dependencies: ['workflow.viewer'] },
        viewer: { privileges: ['workflow:read', 'workflow_draft:read', 'user:read'], dependencies: [] },
        creator: { privileges: ['workflow:create'], dependencies: ['workflow.editor'] },
        deleter: { privileges: ['workflow:delete'], dependencies: ['workflow.viewer'] },
        editor: { privileges: ['workflow:update'], dependencies: ['workflow.viewer'] },
    },
});

/** @private */
export {};

Shopwell.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'workflow_participant',
    roles: {
        viewer: { privileges: ['workflow_instance:access'], dependencies: [] },
        creator: { privileges: ['workflow_instance:start', 'workflow:read'], dependencies: ['workflow_participant.viewer'] },
        approver: { privileges: ['workflow_task:decide'], dependencies: ['workflow_participant.viewer'] },
    },
});
