/** @sw-package framework */

/** @public */
export const WORKFLOW_NODE_TYPES = {
    START: 'core:start',
    END: 'core:end',
    APPROVAL: 'core:approval',
    CONDITION: 'core:condition',
} as const;

/** @public */
export const WORKFLOW_NODE_KINDS = {
    START: 'start',
    ACTIVITY: 'activity',
    END: 'end',
} as const;

/** @public */
export const WORKFLOW_NODE_PORTS = {
    IN: 'in',
    OUT: 'out',
    APPROVED: 'approved',
    REJECTED: 'rejected',
    FALLBACK: 'fallback',
} as const;

/** @public */
export const WORKFLOW_ASSIGNEE_SOURCES = {
    USER: 'core:user',
    USERS: 'core:users',
} as const;

/** @public */
export const WORKFLOW_APPROVAL_MODES = {
    SINGLE: 'core:single',
    ALL: 'core:all',
    ANY: 'core:any',
} as const;

/** @public */
export const WORKFLOW_INPUT_TYPES = {
    APPROVAL_REQUEST: 'core:approval-request',
} as const;
