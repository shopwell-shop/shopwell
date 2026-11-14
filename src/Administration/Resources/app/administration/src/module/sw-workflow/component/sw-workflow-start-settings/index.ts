/** @sw-package framework */
import type { PropType } from 'vue';
import type { WorkflowNode } from 'src/core/workflow/workflow.types';
import { workflowFormFields } from '../../util/workflow-form.util';
import type { WorkflowFormField, WorkflowFormFieldType } from '../../util/workflow-form.util';
import template from './sw-workflow-start-settings.html.twig';
import './sw-workflow-start-settings.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowRequestFormService'],
    props: {
        node: { type: Object as PropType<WorkflowNode>, required: true },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update-config'],
    data(): { fields: WorkflowFormField[]; activeKey: string } {
        return { fields: [], activeKey: '' };
    },
    computed: {
        enabled(): boolean {
            return this.node.type === 'core:start' && this.node.typeVersion === 1;
        },
        dataSources() {
            return [
                { value: '', label: this.$t('sw-workflow.form.freeText') },
                ...this.workflowRequestFormService
                    .getDataSources()
                    .map((source) => ({ value: source.name, label: this.$t(source.labelKey) })),
            ];
        },
        types() {
            return [
                'text',
                'amount',
                'number',
                'date',
                'select',
            ].map((value) => ({
                value,
                label: this.$t(`sw-workflow.form.types.${value}`),
            }));
        },
    },
    watch: {
        node: {
            immediate: true,
            handler(node: WorkflowNode) {
                this.fields = workflowFormFields(node.config);
                if (!this.fields.some((field) => field.key === this.activeKey)) {
                    this.activeKey = this.fields[0]?.key ?? '';
                }
            },
        },
    },
    methods: {
        commit(): void {
            if (!this.allowEdit || !this.enabled) {
                return;
            }
            this.$emit('update-config', { formFields: JSON.parse(JSON.stringify(this.fields)) as WorkflowFormField[] });
        },
        add(): void {
            if (!this.allowEdit || !this.enabled || this.fields.length >= 50) {
                return;
            }
            const field: WorkflowFormField = {
                key: `field_${Shopwell.Utils.createId()}`,
                label: this.$t('sw-workflow.form.newField'),
                type: 'text',
                required: false,
            };
            this.fields.push(field);
            this.activeKey = field.key;
            this.commit();
        },
        remove(key: string): void {
            if (!this.allowEdit) {
                return;
            }
            this.fields = this.fields.filter((field) => field.key !== key);
            this.commit();
        },
        move(index: number, direction: number): void {
            const target = index + direction;
            if (!this.allowEdit || target < 0 || target >= this.fields.length) {
                return;
            }
            const [field] = this.fields.splice(index, 1);
            this.fields.splice(target, 0, field);
            this.commit();
        },
        selectType(field: WorkflowFormField, type: WorkflowFormFieldType): void {
            if (!this.allowEdit || !this.types.some((option) => option.value === type)) {
                return;
            }
            field.type = type;
            if (type === 'select' && !field.options) {
                field.options = [];
            }
            if (type !== 'select') {
                delete field.options;
            }
            if (type !== 'text') {
                delete field.dataSource;
            }
            this.commit();
        },
        selectDataSource(field: WorkflowFormField, source: string): void {
            if (!this.allowEdit || field.type !== 'text') {
                return;
            }
            if (source) {
                field.dataSource = source;
            } else {
                delete field.dataSource;
            }
            this.commit();
        },
        addOption(field: WorkflowFormField): void {
            if (!this.allowEdit || (field.options?.length ?? 0) >= 50) {
                return;
            }
            field.options ??= [];
            field.options.push({
                value: `option_${Shopwell.Utils.createId()}`,
                label: this.$t('sw-workflow.form.newOption'),
            });
            this.commit();
        },
        removeOption(field: WorkflowFormField, index: number): void {
            if (!this.allowEdit) {
                return;
            }
            field.options?.splice(index, 1);
            this.commit();
        },
    },
});
