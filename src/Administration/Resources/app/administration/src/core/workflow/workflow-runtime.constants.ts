/** @sw-package framework */

/** @public */
export const WORKFLOW_RUNTIME_NODE_TYPES = {
    START: 'snaker:start',
    END: 'snaker:end',
    TASK: 'snaker:task',
    CONDITION: 'snaker:condition',
    COPY: 'snaker:copy',
} as const;

/** @public */
export const WORKFLOW_INSTANCE_STATUSES = {
    RUNNING: 'RUNNING',
    COMPLETED: 'COMPLETED',
    AGREED: 'AGREE',
    REJECTED: 'REJECT',
    CANCELED: 'CANCEL',
} as const;

/** @public */
export const WORKFLOW_TASK_STATES = {
    TODO: 'todo',
    AGREED: 'agreed',
    REJECTED: 'rejected',
    CANCELED: 'canceled',
} as const;

/** @public */
export const WORKFLOW_TASK_DECISIONS = {
    AGREE: 'agree',
    REJECT: 'reject',
} as const;

/** @public */
export const WORKFLOW_COUNTERSIGN_TYPES = {
    SINGLE: 'single',
    COUNTERSIGN: 'countersign',
    FIRST_DECISION: 'firstDecision',
    OR_SIGN: 'orSign',
    ORDER_SIGN: 'orderSign',
} as const;
