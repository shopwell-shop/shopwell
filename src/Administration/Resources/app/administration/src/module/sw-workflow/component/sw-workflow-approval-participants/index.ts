/** @sw-package framework */
import type { PropType } from 'vue';
import template from './sw-workflow-approval-participants.html.twig';
import './sw-workflow-approval-participants.scss';

type Participant = { id: string; name: string };
type ApprovalTask = {
    id: string;
    nodeId: string;
    nodeName: string;
    round: number;
    countersignType: string;
    state: string;
    assignee: Participant;
};
type ApprovalStage = {
    nodeId: string;
    nodeName: string;
    description: string;
    countersignType: string;
    status: string;
    participants: Participant[];
    tasks: ApprovalTask[];
};
type ApprovalGroup = {
    id: string;
    name: string;
    description: string;
    mode: string;
    status: string;
    tasks: ApprovalTask[];
    participants: { id: string; name: string; state: string }[];
    counts: Record<string, number>;
    completed: number;
};

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    props: {
        tasks: { type: Array as PropType<ApprovalTask[]>, required: true },
        stages: { type: Array as PropType<ApprovalStage[]>, default: () => [] },
    },

    computed: {
        groups(): ApprovalGroup[] {
            const stages: ApprovalStage[] = this.stages.length ? this.stages : [];
            if (!stages.length) {
                const grouped = new Map<string, ApprovalStage>();
                this.tasks.forEach((task) => {
                    const id = `${task.nodeId}:${task.round}`;
                    if (!grouped.has(id)) {
                        grouped.set(id, {
                            nodeId: id,
                            nodeName: task.nodeName,
                            description: '',
                            countersignType: task.countersignType,
                            status: '',
                            participants: [],
                            tasks: [],
                        });
                    }
                    grouped.get(id)?.tasks.push(task);
                });
                stages.push(...grouped.values());
            }
            return stages.map((stage) => {
                const counts: Record<string, number> = { todo: 0, agreed: 0, rejected: 0, canceled: 0 };
                stage.tasks.forEach((task) => {
                    counts[task.state] = (counts[task.state] ?? 0) + 1;
                });
                const participants = stage.participants.length
                    ? stage.participants.map((participant) => ({
                          ...participant,
                          state: stage.tasks.find((task) => task.assignee.id === participant.id)?.state ?? stage.status,
                      }))
                    : stage.tasks.map((task) => ({ ...task.assignee, id: task.id, state: task.state }));
                return {
                    id: stage.nodeId,
                    name: stage.nodeName,
                    description: stage.description,
                    mode: stage.countersignType,
                    status: stage.status,
                    tasks: stage.tasks,
                    participants,
                    counts,
                    completed: counts.agreed + counts.rejected + counts.canceled,
                };
            });
        },
    },

    methods: {
        rule(mode: string): string {
            if (
                ![
                    'single',
                    'countersign',
                    'firstDecision',
                    'orSign',
                    'orderSign',
                ].includes(mode)
            )
                return '';
            return this.$t(`sw-workflow.runtime.participants.rules.${mode}`);
        },

        stateLabel(state: string): string {
            if (
                ![
                    'todo',
                    'agreed',
                    'rejected',
                    'canceled',
                    'pending',
                    'active',
                    'approved',
                    'skipped',
                ].includes(state)
            )
                return state;
            return this.$t(`sw-workflow.runtime.participants.states.${state}`);
        },
    },
});
