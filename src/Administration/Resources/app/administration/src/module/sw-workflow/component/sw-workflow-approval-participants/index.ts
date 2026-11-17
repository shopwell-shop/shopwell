/** @sw-package framework */
import { WORKFLOW_TASK_STATES, WORKFLOW_COUNTERSIGN_TYPES } from 'src/core/workflow/workflow-runtime.constants';
import type { PropType } from 'vue';
import type { RuntimeTask, RuntimeStage, RuntimeParticipant } from 'src/core/workflow/workflow-runtime.types';
import template from './sw-workflow-approval-participants.html.twig';
import './sw-workflow-approval-participants.scss';

type ApprovalGroup = {
    id: string;
    name: string;
    description: string;
    mode: string;
    status: string;
    tasks: RuntimeTask[];
    participants: (RuntimeParticipant & { state: string; requiredReviewer?: boolean })[];
    counts: Record<string, number>;
    completed: number;
};

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    props: {
        tasks: { type: Array as PropType<RuntimeTask[]>, required: true },
        stages: { type: Array as PropType<RuntimeStage[]>, default: () => [] },
    },

    computed: {
        groups(): ApprovalGroup[] {
            const stages: RuntimeStage[] = this.stages.length ? this.stages : [];

            if (!stages.length) {
                const grouped = new Map<string, RuntimeStage>();
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
                const counts: Record<string, number> = Object.fromEntries(
                    Object.values(WORKFLOW_TASK_STATES).map((state) => [state, 0]),
                );
                stage.tasks.forEach((task) => {
                    counts[task.state] = (counts[task.state] ?? 0) + 1;
                });
                const participants = stage.participants.length
                    ? stage.participants.map((participant) => ({
                          ...participant,
                          requiredReviewer: stage.tasks.some(
                              (task) => task.assignee.id === participant.id && task.requiredReviewer,
                          ),
                          state: stage.tasks.find((task) => task.assignee.id === participant.id)?.state ?? stage.status,
                      }))
                    : stage.tasks.map((task) => ({
                          ...task.assignee,
                          id: task.id,
                          state: task.state,
                          requiredReviewer: task.requiredReviewer,
                      }));
                return {
                    id: stage.nodeId,
                    name: stage.nodeName,
                    description: stage.description,
                    mode: stage.countersignType,
                    status: stage.status,
                    tasks: stage.tasks,
                    participants,
                    counts,
                    completed:
                        counts[WORKFLOW_TASK_STATES.AGREED] +
                        counts[WORKFLOW_TASK_STATES.REJECTED] +
                        counts[WORKFLOW_TASK_STATES.CANCELED],
                };
            });
        },
    },

    methods: {
        rule(mode: string): string {
            if (
                ![
                    WORKFLOW_COUNTERSIGN_TYPES.SINGLE,
                    WORKFLOW_COUNTERSIGN_TYPES.COUNTERSIGN,
                    WORKFLOW_COUNTERSIGN_TYPES.FIRST_DECISION,
                    WORKFLOW_COUNTERSIGN_TYPES.OR_SIGN,
                    WORKFLOW_COUNTERSIGN_TYPES.ORDER_SIGN,
                ].some((knownMode) => knownMode === mode)
            ) {
                return '';
            }

            return this.$t(`sw-workflow.runtime.participants.rules.${mode}`);
        },

        stateLabel(state: string): string {
            if (
                ![
                    ...Object.values(WORKFLOW_TASK_STATES),
                    'pending',
                    'active',
                    'approved',
                    'skipped',
                ].includes(state)
            ) {
                return state;
            }

            return this.$t(`sw-workflow.runtime.participants.states.${state}`);
        },
    },
});
