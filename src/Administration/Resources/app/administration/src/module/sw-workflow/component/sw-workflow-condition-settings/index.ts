/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowDocument, WorkflowNode } from 'src/core/workflow/workflow.types';
import { conditionBranches } from '../../util/workflow-condition.util';
import { canRemoveConditionBranch } from '../../service/editing/condition-workflow-editing.strategy';
import type { WorkflowConditionBranch } from '../../util/workflow-condition.util';
import type { WorkflowFieldLabel } from '../../service/workflow-field.service';
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
        fieldOptions() {
            return this.fields.map((field) => ({ value: field.path, label: this.fieldLabel(field.label) }));
        },
    },
    watch: {
        node: {
            immediate: true,
            handler(node: WorkflowNode) {
                this.branches = conditionBranches(node).map((branch) => ({ ...branch }));

                if (!this.branches.some((branch) => branch.id === this.activeId)) {
                    this.activeId = this.branches[0]?.id ?? '';
                }
            },
        },
    },
    methods: {
        supportsOrderedComparison(branch: WorkflowConditionBranch): boolean {
            switch (this.fieldDefinition(branch)?.type) {
                case 'amount':
                case 'number':
                case 'date':
                    return true;
                default:
                    return false;
            }
        },
        supportedOperators(branch: WorkflowConditionBranch): string[] {
            const operators = [
                'equals',
                'notEquals',
                'isEmpty',
                'isNotEmpty',
            ];
            return this.supportsOrderedComparison(branch)
                ? [
                      ...operators,
                      'greaterThan',
                      'greaterThanOrEqual',
                      'lessThan',
                      'lessThanOrEqual',
                  ]
                : operators;
        },
        hasUnavailableOperator(branch: WorkflowConditionBranch): boolean {
            return !this.supportedOperators(branch).includes(branch.operator);
        },
        operatorOptions(branch: WorkflowConditionBranch) {
            const options = this.supportedOperators(branch).map((value) => ({
                value,
                label: this.$t(`sw-workflow.condition.operators.${value}`),
                disabled: false,
            }));

            if (this.hasUnavailableOperator(branch)) {
                options.push({
                    value: branch.operator,
                    label: this.$t('sw-workflow.condition.unavailableOperator'),
                    disabled: true,
                });
            }

            return options;
        },
        valueHint(branch: WorkflowConditionBranch): string {
            const type = this.fieldDefinition(branch)?.type;
            return this.supportsOrderedComparison(branch) ? this.$t(`sw-workflow.form.valueHints.${type}`) : '';
        },
        fieldLabel(label: WorkflowFieldLabel): string {
            return typeof label === 'string' ? label : this.$t(label.snippet);
        },
        fieldDefinition(branch: WorkflowConditionBranch) {
            return this.fields.find((field) => field.path === branch.field);
        },
        optionsForField(branch: WorkflowConditionBranch) {
            if (!branch.field || this.fieldDefinition(branch)) {
                return this.fieldOptions;
            }

            return [
                ...this.fieldOptions,
                {
                    value: branch.field,
                    label: this.$t('sw-workflow.condition.unavailableField'),
                    disabled: true,
                },
            ];
        },
        valueOptions(branch: WorkflowConditionBranch) {
            const field = this.fieldDefinition(branch);

            if (field?.type !== 'select') {
                return [];
            }

            const options = field.options.map((option) => ({ value: option.value, label: this.fieldLabel(option.label) }));

            if (!branch.value || options.some((option) => option.value === branch.value)) {
                return options;
            }

            return [
                ...options,
                {
                    value: branch.value,
                    label: this.$t('sw-workflow.condition.unavailableValue'),
                    disabled: true,
                },
            ];
        },
        hasUnavailableValue(branch: WorkflowConditionBranch): boolean {
            const field = this.fieldDefinition(branch);
            return (
                field?.type === 'select' && !!branch.value && !field.options.some((option) => option.value === branch.value)
            );
        },
        hasStaleFieldType(branch: WorkflowConditionBranch): boolean {
            const field = this.fieldDefinition(branch);
            return !!field && branch.fieldType !== field.type;
        },
        selectField(branch: WorkflowConditionBranch, path: string): void {
            if (!this.allowEdit || path === branch.field) {
                return;
            }

            const field = this.fields.find((item) => item.path === path);

            if (!field) {
                return;
            }

            branch.field = path;
            this.bindField(branch);
        },
        bindField(branch: WorkflowConditionBranch): void {
            const field = this.fieldDefinition(branch);

            if (!this.allowEdit || !field) {
                return;
            }

            branch.fieldType = field.type;
            branch.operator = 'equals';
            branch.value = '';
            this.commit();
        },
        selectOperator(branch: WorkflowConditionBranch, operator: WorkflowConditionBranch['operator']): void {
            if (
                !this.allowEdit ||
                this.hasStaleFieldType(branch) ||
                !this.fieldDefinition(branch) ||
                !this.supportedOperators(branch).includes(operator)
            ) {
                return;
            }

            branch.operator = operator;

            if (operator === 'isEmpty' || operator === 'isNotEmpty') {
                branch.value = '';
            }

            this.commit();
        },
        selectValue(branch: WorkflowConditionBranch, value: string): void {
            const field = this.fieldDefinition(branch);

            if (
                !this.allowEdit ||
                this.hasStaleFieldType(branch) ||
                field?.type !== 'select' ||
                !field.options.some((option) => option.value === value)
            ) {
                return;
            }

            branch.value = value;
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
