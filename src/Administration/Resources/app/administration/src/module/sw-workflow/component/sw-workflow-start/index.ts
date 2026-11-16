/** @sw-package framework */
import { WORKFLOW_NODE_TYPES } from 'src/core/workflow/workflow-node.constants';
import type { PropType } from 'vue';
import type { WorkflowRelease } from 'src/core/service/api/workflow.api.service';
import type { WorkflowDocument } from 'src/core/workflow/workflow.types';
import type { WorkflowFormField } from 'src/core/workflow/workflow-runtime.types';
import { serializeForm, validateFormValue } from '../../util/workflow-runtime-form.util';
import template from './sw-workflow-start.html.twig';

type Release = Pick<WorkflowRelease, 'id' | 'name'> & { document?: WorkflowDocument };

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: [
        'workflowRuntimeApiService',
        'acl',
        'workflowService',
        'workflowRequestFormService',
    ],
    props: { release: { type: Object as PropType<Release>, required: true } },
    data() {
        return {
            requestRevision: 0,
            showModal: false,
            title: '',
            fields: [] as WorkflowFormField[],
            values: {} as Record<string, string>,
            isLoading: false,
            isReady: false,
            isSubmitting: false,
            hasError: false,
            errorDetail: '',
            showValidation: false,
        };
    },
    computed: {
        canStart(): boolean {
            return this.acl.can('workflow_participant.creator') && !this.isSubmitting && !this.isLoading;
        },
        fieldErrors(): Record<string, string> {
            return Object.fromEntries(
                this.fields.flatMap((field) => {
                    const error = validateFormValue(field, this.values[field.key]);
                    return error ? [[field.key, this.$t(`sw-workflow.request.validation.${error}`)]] : [];
                }),
            );
        },
    },
    watch: {
        'release.id'() {
            this.requestRevision += 1;
            this.showModal = false;
            this.title = '';
            this.fields = [];
            this.values = {};
            this.isLoading = false;
            this.isReady = false;
            this.isSubmitting = false;
            this.hasError = false;
            this.errorDetail = '';
        },
    },
    beforeUnmount() {
        this.requestRevision += 1;
    },
    methods: {
        async open(): Promise<void> {
            if (!this.canStart) {
                return;
            }

            this.title = this.release.name;
            this.values = {};
            this.fields = [];
            this.hasError = false;
            this.errorDetail = '';
            this.showValidation = false;
            this.showModal = true;
            this.isLoading = true;
            this.isReady = false;
            const requestRevision = ++this.requestRevision;
            try {
                const release = this.release.document
                    ? this.release
                    : await this.workflowService.getRelease(this.release.id);

                if (requestRevision !== this.requestRevision) {
                    return;
                }

                const start = release.document?.nodes.find((node) => node.type === WORKFLOW_NODE_TYPES.START);

                if (!start) {
                    throw new Error(this.$t('sw-workflow.request.loadFailed'));
                }

                const fields = await Promise.all(
                    this.workflowRequestFormService
                        .getFields(start.config, this.$t('sw-workflow.inputs.reason'))
                        .map(async (field) =>
                            field.dataSource
                                ? { ...field, options: await this.workflowService.getFormOptions(field.dataSource) }
                                : field,
                        ),
                );

                if (requestRevision !== this.requestRevision) {
                    return;
                }

                this.fields = fields;
                this.values = serializeForm(this.fields, {});
                this.isReady = true;
            } catch (error) {
                if (requestRevision !== this.requestRevision) {
                    return;
                }

                this.hasError = true;
                this.errorDetail = this.errorMessage(error);
            } finally {
                if (requestRevision === this.requestRevision) {
                    this.isLoading = false;
                }
            }
        },
        async start(): Promise<void> {
            if (!this.canStart || !this.title.trim() || this.title.length > 255) {
                return;
            }

            this.showValidation = true;

            if (!this.isReady || Object.keys(this.fieldErrors).length) {
                return;
            }

            this.isSubmitting = true;
            this.hasError = false;
            this.errorDetail = '';
            const requestRevision = ++this.requestRevision;
            try {
                const response = await this.workflowRuntimeApiService.startInstance({
                    definitionId: this.release.id,
                    title: this.title.trim(),
                    formData: serializeForm(this.fields, this.values),
                });

                if (requestRevision !== this.requestRevision) {
                    return;
                }

                if (!response.data.id) {
                    throw new Error(this.$t('sw-workflow.request.failed'));
                }

                this.showModal = false;
                void this.$router.push({
                    name: 'sw.workflow.instance',
                    params: { id: response.data.id },
                });
            } catch (error) {
                if (requestRevision === this.requestRevision) {
                    this.hasError = true;
                    this.errorDetail = this.errorMessage(error);
                }
            } finally {
                if (requestRevision === this.requestRevision) {
                    this.isSubmitting = false;
                }
            }
        },
        errorMessage(error: unknown): string {
            const response = error as {
                response?: { data?: { errors?: { detail?: string }[] } };
                message?: string;
            };
            return response.response?.data?.errors?.[0]?.detail ?? response.message ?? '';
        },
    },
});
