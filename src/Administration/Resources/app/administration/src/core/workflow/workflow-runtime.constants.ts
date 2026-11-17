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
    RETURNED: 'RETURNED',
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
    OPERATION: 'operation',
    REMIND: 'remind',
    RETURN: 'return',
    RESUBMIT: 'resubmit',
    AGREE: 'agree',
    REJECT: 'reject',
    TRANSFER: 'transfer',
    ADD_SIGN: 'add-sign',
} as const;

/** @public */
export const WORKFLOW_COUNTERSIGN_TYPES = {
    SINGLE: 'single',
    COUNTERSIGN: 'countersign',
    FIRST_DECISION: 'firstDecision',
    OR_SIGN: 'orSign',
    ORDER_SIGN: 'orderSign',
} as const;

/** @public */
export const WORKFLOW_FORM_ACCESS = {
    HIDDEN: 'hidden',
    READONLY: 'readonly',
    EDITABLE: 'editable',
} as const;
