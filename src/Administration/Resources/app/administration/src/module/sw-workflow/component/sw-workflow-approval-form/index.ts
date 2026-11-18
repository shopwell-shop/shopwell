/** @sw-package framework */
import type { PropType } from 'vue';
import type { RuntimeInstance, WorkflowFormField, WorkflowFormValue } from 'src/core/workflow/workflow-runtime.types';
import { WORKFLOW_FORM_ACCESS } from 'src/core/workflow/workflow-runtime.constants';
import { restoreFormValue, validateFormValue } from '../../util/workflow-runtime-form.util';
import template from './sw-workflow-approval-form.html.twig';
import './sw-workflow-approval-form.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowService'],
    props: {
        instance: { type: Object as PropType<RuntimeInstance>, required: true },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update-patch', 'validity-change'],
    data() {
        return {
            fields: [] as WorkflowFormField[],
            initialValues: {} as Record<string, WorkflowFormValue>,
            values: {} as Record<string, WorkflowFormValue>,
            isLoading: false,
            loadError: false,
            revision: 0,
        };
    },
    computed: {
        errors(): Record<string, string> {
            const errors: Record<string, string> = {};
            for (const field of this.fields) {
                const error = validateFormValue(field, this.values[field.key]);
                if (error) {
                    errors[field.key] = this.$t(`sw-workflow.request.validation.${error}`);
                }
            }
            return errors;
        },
        patch(): Record<string, WorkflowFormValue> {
            return Object.fromEntries(
                this.fields
                    .filter(
                        (field) => JSON.stringify(this.values[field.key]) !== JSON.stringify(this.initialValues[field.key]),
                    )
                    .map((field) => [field.key, this.values[field.key]]),
            );
        },
        valid(): boolean {
            return !this.isLoading && !this.loadError && Object.keys(this.errors).length === 0;
        },
    },
    watch: {
        instance: {
            immediate: true,
            handler() {
                void this.loadFields();
            },
        },
        patch: {
            deep: true,
            handler(value: Record<string, WorkflowFormValue>) {
                this.$emit('update-patch', value);
            },
        },
        valid: {
            immediate: true,
            handler(value: boolean) {
                this.$emit('validity-change', value);
            },
        },
    },
    beforeUnmount() {
        this.revision += 1;
    },
    methods: {
        async loadFields(): Promise<void> {
            const revision = ++this.revision;
            this.isLoading = true;
            this.loadError = false;
            this.fields = [];
            this.values = {};
            this.$emit('update-patch', {});
            try {
                const fields = (this.instance.formSchema ?? []).filter(
                    (field) => this.instance.formPermissions?.[field.key]?.access === WORKFLOW_FORM_ACCESS.EDITABLE,
                );
                const prepared = await Promise.all(
                    fields.map(async (field) => ({
                        ...field,
                        required: this.instance.formPermissions?.[field.key]?.required === true,
                        ...(field.dataSource
                            ? { options: await this.workflowService.getFormOptions(field.dataSource) }
                            : {}),
                    })),
                );
                if (revision !== this.revision) {
                    return;
                }
                this.initialValues = Object.fromEntries(
                    prepared.map((field) => [field.key, restoreFormValue(field, this.instance.formData?.[field.key])]),
                );
                this.values = { ...this.initialValues };
                this.fields = prepared;
            } catch {
                if (revision === this.revision) {
                    this.loadError = true;
                }
            } finally {
                if (revision === this.revision) {
                    this.isLoading = false;
                }
            }
        },
    },
});
