/** @sw-package framework */
import type { HttpError } from 'src/core/factory/http-client.types';
import type { ApprovalDetail, FormValue } from '../../service/approval-flow.api.service';
import template from './sw-approval-flow-detail.html.twig';
import './sw-approval-flow-detail.scss';

type FormRow = { path: string; value: FormValue };

function visibleFormRows(form: Record<string, FormValue>, prefix = 'form'): FormRow[] {
    return Object.entries(form).flatMap(([key, value]) => {
        const path = `${prefix}.${key}`;
        if (value !== null && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length > 0) {
            return visibleFormRows(value, path);
        }
        return [{ path, value }];
    });
}

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-approval-flow-detail',
    template,
    inject: ['approvalFlowApiService', 'acl'],
    props: { requestId: { type: String, required: true } },
    data(): { detail: ApprovalDetail | null; isLoading: boolean; loadError: string | null; loadRevision: number } {
        return { detail: null, isLoading: false, loadError: null, loadRevision: 0 };
    },
    metaInfo() {
        return { title: this.$createTitle() };
    },
    computed: {
        backRoute() {
            const { view, ...query } = this.$route.query;
            const inbox = view === 'done' || view === 'started' ? view : 'todo';
            return { name: `sw.approval.flow.${inbox}`, query };
        },
        formRows() {
            if (!this.detail) {
                return [];
            }
            return visibleFormRows(this.detail.form).map((row) => {
                const label = this.detail?.formLabels[row.path];
                return {
                    ...row,
                    label:
                        label?.labelSnippet && this.$te(label.labelSnippet)
                            ? this.$t(label.labelSnippet)
                            : (label?.title ?? row.path),
                };
            });
        },
        steps() {
            if (!this.detail) {
                return [];
            }
            return this.detail.executions.map((execution) => ({
                ...execution,
                tasks: this.detail?.tasks.filter((task) => task.executionId === execution.id) ?? [],
            }));
        },
    },
    watch: {
        requestId: {
            immediate: true,
            handler() {
                void this.loadDetail();
            },
        },
    },
    beforeUnmount() {
        this.loadRevision += 1;
    },
    methods: {
        async loadDetail(): Promise<void> {
            const revision = ++this.loadRevision;
            this.detail = null;
            this.isLoading = true;
            this.loadError = null;
            try {
                const detail = await this.approvalFlowApiService.detail(this.requestId);
                if (revision === this.loadRevision) {
                    this.detail = detail;
                }
            } catch (error) {
                if (revision !== this.loadRevision) {
                    return;
                }
                const status = (error as HttpError | null)?.response?.status;
                this.loadError = status === 404 ? 'notFound' : status === 403 ? 'forbidden' : 'loadError';
            } finally {
                if (revision === this.loadRevision) {
                    this.isLoading = false;
                }
            }
        },
        personName(id: string): string {
            return this.detail?.participantNames[id] ?? this.$t('sw-approval-flow.general.deletedUser');
        },
        date(value: string | null): string {
            return value
                ? Shopwell.Utils.format.date(value, { hour: '2-digit', minute: '2-digit' })
                : this.$t('sw-approval-flow.general.notAvailable');
        },
        formValue(value: FormValue): string {
            if (value === null) {
                return this.$t('sw-approval-flow.detail.notFilled');
            }
            if (typeof value === 'boolean') {
                return this.$t(`global.default.${value ? 'yes' : 'no'}`);
            }
            return typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value);
        },
    },
});
