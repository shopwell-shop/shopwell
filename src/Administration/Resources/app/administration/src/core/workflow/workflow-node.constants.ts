/** @sw-package framework */

/** @public */
export const WORKFLOW_NODE_TYPES = {
    START: 'core:start',
    END: 'core:end',
    APPROVAL: 'core:approval',
    COPY: 'core:copy',
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
    DEPARTMENT_LEADERS: 'core:department-leaders',
    POSITIONS: 'core:positions',
} as const;

/** @public */
export const WORKFLOW_APPROVAL_MODES = {
    SINGLE: 'core:single',
    ALL: 'core:all',
    ANY: 'core:any',
    OR_SIGN: 'core:or-sign',
    SEQUENTIAL: 'core:sequential',
} as const;

/** @public */
export const WORKFLOW_INPUT_TYPES = {
    APPROVAL_REQUEST: 'core:approval-request',
} as const;
