/** @sw-package framework */
/** @public */
export interface WorkflowFormField {
    key: string;
    label: string;
    type: 'text' | 'amount' | 'number' | 'date' | 'select';
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
    title?: string;
    number?: string;
    nodeId: string;
    nodeName: string;
    round: number;
    countersignType: string;
    state: string;
    actionable?: boolean;
    assignee: RuntimeParticipant;
    initiator?: RuntimeParticipant;
    decision?: string;
    comment?: string;
    createdAt?: string;
    completedAt?: string;
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
export type RuntimeInstance = {
    id: string;
    title: string;
    number?: string;
    status: string;
    startedAt?: string;
    initiator?: RuntimeParticipant;
    graph?: RuntimeGraph;
    formData?: Record<string, unknown>;
    formSchema?: WorkflowFormField[];
    executions?: RuntimeNodeExecution[];
    result?: string | null;
    tasks?: RuntimeTask[];
    approvalTasks?: RuntimeTask[];
    history?: RuntimeTask[];
    logs?: Array<{
        id: string;
        action: string;
        operator: RuntimeParticipant | null;
        comment: string | null;
        createdAt: string | null;
    }>;
    approvalStages?: RuntimeStage[];
    selectedPath?: RuntimePath;
    plannedPath?: RuntimePath;
    currentNodeId?: string;
    currentNode?: { id: string; name: string; enteredAt: string | null };
    canCancel?: boolean;
};
/** @public */
export type RuntimePage<T> = { data: T[]; meta?: { total?: number } };
