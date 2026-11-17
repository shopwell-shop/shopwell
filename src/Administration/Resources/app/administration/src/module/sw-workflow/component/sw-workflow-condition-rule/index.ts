/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowField } from 'src/core/workflow/workflow-studio.types';
import { countConditionNodes, isConditionGroup, WORKFLOW_CONDITION_LIMITS } from '../../util/workflow-condition.util';
import type { WorkflowCondition, WorkflowConditionGroup } from '../../util/workflow-condition.util';
import template from './sw-workflow-condition-rule.html.twig';
import './sw-workflow-condition-rule.scss';

function emptyComparison(): WorkflowCondition {
    return { field: '', fieldType: '', operator: 'equals', value: '' };
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        modelValue: { type: Object as PropType<WorkflowCondition>, required: true },
        fields: { type: Array as PropType<WorkflowField[]>, required: true },
        allowEdit: { type: Boolean, default: true },
        depth: { type: Number, default: 0 },
        branchSize: { type: Number, default: null },
    },
    emits: ['update:modelValue'],
    computed: {
        group(): WorkflowConditionGroup | null {
            return isConditionGroup(this.modelValue) ? this.modelValue : null;
        },
        operatorOptions() {
            return ['and', 'or'].map((value) => ({ value, label: this.$t(`sw-workflow.condition.groups.${value}`) }));
        },
        canAdd(): boolean {
            return this.allowEdit && this.depth < WORKFLOW_CONDITION_LIMITS.DEPTH;
        },
        conditionCount(): number {
            return this.branchSize ?? countConditionNodes(this.modelValue);
        },
        canAddComparison(): boolean {
            const addedNodes = this.group ? 1 : 2;

            return this.canAdd && this.conditionCount + addedNodes <= WORKFLOW_CONDITION_LIMITS.SIZE;
        },
        canAddGroup(): boolean {
            const addedNodes = this.group ? 2 : 3;

            return (
                this.canAdd &&
                this.depth + 1 < WORKFLOW_CONDITION_LIMITS.DEPTH &&
                this.conditionCount + addedNodes <= WORKFLOW_CONDITION_LIMITS.SIZE
            );
        },
        quotaExceeded(): boolean {
            return this.conditionCount > WORKFLOW_CONDITION_LIMITS.SIZE;
        },
        quotaLabel(): string {
            return this.$t('sw-workflow.condition.groups.quota', {
                count: this.conditionCount,
                limit: WORKFLOW_CONDITION_LIMITS.SIZE,
            });
        },
    },
    methods: {
        update(condition: WorkflowCondition): void {
            if (this.allowEdit) {
                this.$emit('update:modelValue', condition);
            }
        },
        canEditChild(condition: unknown): boolean {
            if (this.depth >= WORKFLOW_CONDITION_LIMITS.DEPTH || typeof condition !== 'object' || condition === null) {
                return false;
            }
            if (isConditionGroup(condition)) {
                return true;
            }
            return (
                'field' in condition &&
                typeof condition.field === 'string' &&
                'fieldType' in condition &&
                typeof condition.fieldType === 'string' &&
                'operator' in condition &&
                typeof condition.operator === 'string' &&
                condition.operator !== 'and' &&
                condition.operator !== 'or' &&
                'value' in condition &&
                typeof condition.value === 'string'
            );
        },
        setOperator(operator: string): void {
            if (this.group && (operator === 'and' || operator === 'or')) {
                this.update({ ...this.group, operator });
            }
        },
        updateChild(index: number, condition: WorkflowCondition): void {
            if (this.group) {
                this.update({
                    ...this.group,
                    conditions: this.group.conditions.map((child, i) => (i === index ? condition : child)),
                });
            }
        },
        removeChild(index: number): void {
            if (this.group) {
                this.update({ ...this.group, conditions: this.group.conditions.filter((_, i) => i !== index) });
            }
        },
        add(nested = false): void {
            if (nested ? !this.canAddGroup : !this.canAddComparison) {
                return;
            }
            const child: WorkflowCondition = nested
                ? { operator: 'or', conditions: [emptyComparison()] }
                : emptyComparison();
            this.update({
                operator: this.group?.operator ?? 'and',
                conditions: [...(this.group?.conditions ?? [this.modelValue]), child],
            });
        },
    },
});
