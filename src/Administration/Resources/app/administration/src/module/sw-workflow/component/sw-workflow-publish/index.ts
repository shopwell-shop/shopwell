/** @sw-package framework */
import axios from 'axios';
import type { WorkflowPublisher, WorkflowRelease } from 'src/core/service/api/workflow.api.service';
import template from './sw-workflow-publish.html.twig';

function publicationError(error: unknown): string {
    if (!axios.isAxiosError<{ errors?: { meta?: { parameters?: { reason?: string } } }[] }>(error)) {
        return 'failed';
    }

    if (error.response?.status === 409) {
        return 'conflict';
    }

    const reason = error.response?.data.errors?.[0]?.meta?.parameters?.reason;

    if (
        error.response?.status === 422 &&
        reason &&
        [
            'invalid_graph',
            'unsupported_approval_workflow',
            'approval_assignee_unavailable',
            'approval_assignee_forbidden',
        ].includes(reason)
    ) {
        return reason;
    }

    return 'failed';
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['workflowService', 'acl'],
    props: {
        draftId: { type: String, required: true },
        revision: { type: Number, required: true },
        hasChanges: { type: Boolean, required: true },
        disabled: { type: Boolean, default: false },
    },
    data(): {
        requestRevision: number;
        publishers: WorkflowPublisher[];
        publisher: string;
        showModal: boolean;
        isSubmitting: boolean;
        isLoading: boolean;
        error: string | null;
        release: WorkflowRelease | null;
    } {
        return {
            requestRevision: 0,
            publishers: [],
            publisher: '',
            showModal: false,
            isSubmitting: false,
            isLoading: false,
            error: null,
            release: null,
        };
    },
    computed: {
        canPublish(): boolean {
            return (
                this.acl.can('workflow:publish') &&
                this.revision > 0 &&
                !this.hasChanges &&
                !this.disabled &&
                !this.isSubmitting
            );
        },
        publisherOptions(): { value: string; label: string }[] {
            return this.publishers
                .filter((publisher) => publisher.canPublish)
                .map((publisher) => ({ value: publisher.name, label: this.$t(publisher.labelKey) }));
        },
        canConfirm(): boolean {
            return (
                this.canPublish && !this.isLoading && this.publisherOptions.some((option) => option.value === this.publisher)
            );
        },
    },
    watch: {
        draftId() {
            this.requestRevision += 1;
            this.isLoading = false;
            this.isSubmitting = false;
            this.publishers = [];
            this.publisher = '';
            this.release = null;
            this.showModal = false;
            this.error = null;
        },
    },
    beforeUnmount() {
        this.requestRevision += 1;
    },
    methods: {
        async open(): Promise<void> {
            if (!this.canPublish || this.isLoading) {
                return;
            }

            const requestRevision = ++this.requestRevision;
            this.showModal = true;
            this.error = null;
            this.isLoading = true;
            this.publishers = [];
            this.publisher = '';
            try {
                const publishers = await this.workflowService.getPublishers();

                if (requestRevision !== this.requestRevision) {
                    return;
                }

                this.publishers = publishers;
                this.publisher = this.publisherOptions[0]?.value ?? '';
            } catch {
                if (requestRevision === this.requestRevision) {
                    this.error = 'providersFailed';
                }
            } finally {
                if (requestRevision === this.requestRevision) {
                    this.isLoading = false;
                }
            }
        },
        async publish(): Promise<void> {
            if (!this.canConfirm) {
                return;
            }

            this.isSubmitting = true;
            this.error = null;
            const draftId = this.draftId;
            const requestRevision = ++this.requestRevision;
            try {
                const release = await this.workflowService.publishDraft(draftId, this.revision, this.publisher);

                if (requestRevision !== this.requestRevision) {
                    return;
                }

                this.release = release;
                this.showModal = false;
            } catch (error) {
                if (requestRevision !== this.requestRevision) {
                    return;
                }

                this.error = publicationError(error);
            } finally {
                if (requestRevision === this.requestRevision) {
                    this.isSubmitting = false;
                }
            }
        },
    },
});
