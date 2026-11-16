/** @sw-package framework */
/** @public */
export type WorkflowFormFieldType = 'text' | 'amount' | 'number' | 'date' | 'select';

/** @public */
export interface WorkflowFormField {
    key: string;
    label: string;
    type: WorkflowFormFieldType;
    required: boolean;
    dataSource?: string;
    options?: { value: string; label: string }[];
}

/** @public */
export type RuntimeGraph = {
    nodes: Array<{ id: string; type: string; x?: number; y?: number; properties?: { name?: string; description?: string } }>;
    edges: Array<{ id: string; sourceNodeId: string; targetNodeId: string; properties?: { name?: string; expr?: string } }>;
};
/** @public */
export type RuntimePath = { nodeIds: string[]; edgeIds: string[] };
/** @public */
export type RuntimeParticipant = { id: string; name?: string; unitName?: string };
/** @public */
export type RuntimeTask = {
    id: string;
    instanceId?: string;
    nodeId: string;
    nodeName: string;
    round: number;
    countersignType: string;
    state: string;
    actionable?: boolean;
    assignee: RuntimeParticipant;
    decision?: string;
    comment?: string;
    createdAt?: string | null;
    completedAt?: string | null;
};
/** @public */
export type RuntimeStage = {
    nodeId: string;
    nodeName: string;
    description: string;
    countersignType: string;
    status: string;
    participants: RuntimeParticipant[];
    tasks: RuntimeTask[];
};
/** One persisted visit to a node, independent of human tasks. @public */
export type RuntimeNodeExecution = {
    id: string;
    nodeId: string;
    sequence: number;
    round: number;
    status: 'running' | 'waiting' | 'completed' | 'canceled';
    sourceConnectionId: string | null;
    waitingFor: string | null;
    result: string | null;
    output: Record<string, unknown>;
    startedAt: string | null;
    endedAt: string | null;
};

/** @public */
export type RuntimeInstanceListItem = {
    id: string;
    title: string;
    number?: string;
    status: string;
    startedAt?: string | null;
    initiator?: RuntimeParticipant;
};

/** Instance summaries are absent when the associated instance is unavailable. @public */
export type RuntimeTaskInstanceSummary = Partial<Pick<RuntimeInstanceListItem, 'title' | 'number' | 'initiator'>>;

/** Pending task endpoint projection. @public */
export type RuntimeTodoTask = RuntimeTask & RuntimeTaskInstanceSummary & { instanceId: string };

/** Decision history as presented in instance details, independent of current task state. @public */
export type RuntimeTaskHistory = {
    id: string;
    taskId: string;
    nodeId: string;
    nodeName: string;
    assignee: RuntimeParticipant;
    countersignType: string;
    decision: string;
    comment: string | null;
    completedAt: string | null;
};

/** Completed decisions have their own history identity and no current task state. @public */
export type RuntimeDoneTask = Pick<
    RuntimeTaskHistory,
    'id' | 'taskId' | 'nodeId' | 'nodeName' | 'decision' | 'comment' | 'completedAt'
> &
    RuntimeTaskInstanceSummary & { instanceId: string };

/** Instance detail presenter, including optional extension contributions. @public */
export type RuntimeInstance = RuntimeInstanceListItem & {
    graph?: RuntimeGraph;
    formData?: Record<string, unknown>;
    formSchema?: WorkflowFormField[];
    executions?: RuntimeNodeExecution[];
    result?: string | null;
    tasks?: RuntimeTask[];
    approvalTasks?: RuntimeTask[];
    history?: RuntimeTaskHistory[];
    logs?: Array<{
        id: string;
        action: string;
        operator: RuntimeParticipant | null;
        comment: string | null;
        createdAt: string | null;
    }>;
    approvalStages?: RuntimeStage[];
    selectedPath?: RuntimePath;
    plannedPath?: RuntimePath | null;
    currentNodeId?: string | null;
    currentNode?: { id: string; name: string; enteredAt: string | null };
    canCancel?: boolean;
};
/** @public */
export type RuntimePage<T> = { data: T[]; meta?: { total?: number } };
