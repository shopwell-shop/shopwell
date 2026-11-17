/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowDocument, WorkflowNode } from 'src/core/workflow/workflow.types';
import { conditionBranches } from '../../util/workflow-condition.util';
import { canRemoveConditionBranch } from '../../service/editing/condition-workflow-editing.strategy';
import type { WorkflowConditionBranch, WorkflowCondition } from '../../util/workflow-condition.util';
import template from './sw-workflow-condition-settings.html.twig';
import './sw-workflow-condition-settings.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowFieldService'],
    props: {
        node: { type: Object as PropType<WorkflowNode>, required: true },
        document: { type: Object as PropType<WorkflowDocument>, required: true },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update-config'],
    data(): { branches: WorkflowConditionBranch[]; activeId: string } {
        return { branches: [], activeId: '' };
    },
    computed: {
        fields() {
            return this.workflowFieldService.getFields(this.document, this.node);
        },
    },
    watch: {
        node: {
            immediate: true,
            handler(node: WorkflowNode) {
                this.branches = JSON.parse(JSON.stringify(conditionBranches(node))) as WorkflowConditionBranch[];

                if (!this.branches.some((branch) => branch.id === this.activeId)) {
                    this.activeId = this.branches[0]?.id ?? '';
                }
            },
        },
    },
    methods: {
        branchRule(branch: WorkflowConditionBranch): WorkflowCondition {
            return Object.fromEntries(
                Object.entries(branch).filter(([key]) => key !== 'id' && key !== 'label'),
            ) as WorkflowCondition;
        },
        updateRule(index: number, rule: WorkflowCondition): void {
            if (!this.allowEdit) {
                return;
            }
            const { id, label } = this.branches[index];
            this.branches[index] = { ...rule, id, label };
            this.commit();
        },
        commit(): void {
            if (this.allowEdit) {
                this.$emit('update-config', { branches: this.branches.map((branch) => ({ ...branch })) });
            }
        },
        add(): void {
            if (!this.allowEdit) {
                return;
            }

            const id = `branch-${Shopwell.Utils.createId()}`;
            this.branches.push({
                id,
                label: this.$t('sw-workflow.condition.newBranch'),
                field: '',
                fieldType: '',
                operator: 'equals',
                value: '',
            });
            this.activeId = id;
            this.commit();
        },
        move(index: number, direction: number): void {
            const target = index + direction;

            if (!this.allowEdit || target < 0 || target >= this.branches.length) {
                return;
            }

            const [branch] = this.branches.splice(index, 1);
            this.branches.splice(target, 0, branch);
            this.commit();
        },
        canRemove(id: string): boolean {
            return this.allowEdit && this.branches.length > 1 && canRemoveConditionBranch(this.document, this.node.id, id);
        },
        remove(id: string): void {
            if (!this.canRemove(id)) {
                return;
            }

            this.branches = this.branches.filter((branch) => branch.id !== id);
            this.commit();
        },
    },
});
