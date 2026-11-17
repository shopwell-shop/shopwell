/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowField } from 'src/core/workflow/workflow-studio.types';
import type { WorkflowConditionComparison } from '../../util/workflow-condition.util';
import type { WorkflowFieldLabel } from '../../service/workflow-field.service';
import template from './sw-workflow-condition-comparison.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        modelValue: { type: Object as PropType<WorkflowConditionComparison>, required: true },
        fields: { type: Array as PropType<WorkflowField[]>, required: true },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update:modelValue'],
    data(): { branch: WorkflowConditionComparison } {
        return { branch: { ...this.modelValue } };
    },
    computed: {
        fieldOptions() {
            return this.fields.map((field) => ({ value: field.path, label: this.fieldLabel(field.label) }));
        },
    },
    watch: {
        modelValue(value: WorkflowConditionComparison) {
            this.branch = { ...value };
        },
    },
    methods: {
        supportsOrderedComparison(branch: WorkflowConditionComparison): boolean {
            switch (this.fieldDefinition(branch)?.type) {
                case 'amount':
                case 'number':
                case 'date':
                    return true;
                default:
                    return false;
            }
        },
        supportedOperators(branch: WorkflowConditionComparison): string[] {
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
        hasUnavailableOperator(branch: WorkflowConditionComparison): boolean {
            return !this.supportedOperators(branch).includes(branch.operator);
        },
        operatorOptions(branch: WorkflowConditionComparison) {
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
        valueHint(branch: WorkflowConditionComparison): string {
            const type = this.fieldDefinition(branch)?.type;
            return this.supportsOrderedComparison(branch) ? this.$t(`sw-workflow.form.valueHints.${type}`) : '';
        },
        fieldLabel(label: WorkflowFieldLabel): string {
            return typeof label === 'string' ? label : this.$t(label.snippet);
        },
        fieldDefinition(branch: WorkflowConditionComparison) {
            return this.fields.find((field) => field.path === branch.field);
        },
        optionsForField(branch: WorkflowConditionComparison) {
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
        valueOptions(branch: WorkflowConditionComparison) {
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
        hasUnavailableValue(branch: WorkflowConditionComparison): boolean {
            const field = this.fieldDefinition(branch);
            return (
                field?.type === 'select' && !!branch.value && !field.options.some((option) => option.value === branch.value)
            );
        },
        hasStaleFieldType(branch: WorkflowConditionComparison): boolean {
            const field = this.fieldDefinition(branch);
            return !!field && branch.fieldType !== field.type;
        },
        selectField(branch: WorkflowConditionComparison, path: string): void {
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
        bindField(branch: WorkflowConditionComparison): void {
            const field = this.fieldDefinition(branch);

            if (!this.allowEdit || !field) {
                return;
            }

            branch.fieldType = field.type;
            branch.operator = 'equals';
            branch.value = '';
            this.commit();
        },
        selectOperator(branch: WorkflowConditionComparison, operator: WorkflowConditionComparison['operator']): void {
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
        selectValue(branch: WorkflowConditionComparison, value: string): void {
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
                this.$emit('update:modelValue', { ...this.branch });
            }
        },
    },
});
