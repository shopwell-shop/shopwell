/** @sw-package framework */
import type { PropType } from 'vue';
import type { HttpError } from 'src/core/factory/http-client.types';
import type { ApprovalBusinessDescriptor } from '../../service/approval-flow-design.api.service';
import template from './sw-approval-flow-release-activation.html.twig';
import './sw-approval-flow-release-activation.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-approval-flow-release-activation',
    template,
    inject: ['repositoryFactory', 'approvalFlowDesignApiService'],
    emits: ['close', 'activated'],
    props: {
        release: { type: Object as PropType<Entity<'approval_flow_release'>>, required: true },
        businessTypes: { type: Array as PropType<ApprovalBusinessDescriptor[]>, required: true },
    },
    data() {
        return {
            businessKey: this.businessTypes[0]?.key ?? '',
            head: null as Entity<'approval_flow_binding_head'> | null,
            activeBinding: null as Entity<'approval_flow_binding'> | null,
            isLoading: false,
            isActivating: false,
            loadError: false,
            activationError: '',
            conflict: false,
        };
    },
    computed: {
        business() {
            return this.businessTypes.find((business) => business.key === this.businessKey);
        },
        businessOptions() {
            return this.businessTypes.map((business) => ({
                value: business.key,
                label: `${this.$te(business.labelSnippet) ? this.$t(business.labelSnippet) : business.type} · ${business.version}`,
            }));
        },
        isActive(): boolean {
            return (
                this.activeBinding?.releaseId === this.release.id &&
                this.activeBinding.businessVersion === this.business?.version
            );
        },
    },
    created() {
        void this.loadBinding();
    },
    methods: {
        async loadBinding(): Promise<void> {
            if (!this.business) {
                return;
            }
            const { type } = this.business;
            this.isLoading = true;
            this.loadError = false;
            this.activationError = '';
            this.conflict = false;

            try {
                const headCriteria = new Shopwell.Data.Criteria(1, 1);
                headCriteria.addFilter(Shopwell.Data.Criteria.equals('businessType', type));
                headCriteria.addIncludes({
                    approval_flow_binding_head: [
                        'id',
                        'latestRevision',
                        'activeRevision',
                        'version',
                    ],
                });
                const head = await this.repositoryFactory.create('approval_flow_binding_head').search(headCriteria);
                this.head = head.first();
                this.activeBinding = null;
                if (this.head?.activeRevision != null) {
                    const bindingCriteria = new Shopwell.Data.Criteria(1, 1);
                    bindingCriteria.addFilter(Shopwell.Data.Criteria.equals('businessType', type));
                    bindingCriteria.addFilter(Shopwell.Data.Criteria.equals('revision', this.head.activeRevision));
                    bindingCriteria.addIncludes({ approval_flow_binding: ['id', 'releaseId', 'businessVersion'] });
                    const bindings = await this.repositoryFactory.create('approval_flow_binding').search(bindingCriteria);
                    this.activeBinding = bindings.first();
                }
            } catch {
                this.loadError = true;
            } finally {
                this.isLoading = false;
            }
        },
        async activate(): Promise<void> {
            if (!this.business) {
                return;
            }
            this.isActivating = true;
            this.activationError = '';
            try {
                await this.approvalFlowDesignApiService.activateRelease(this.release.id, {
                    businessType: this.business.type,
                    businessVersion: this.business.version,
                    expectedRevision: this.head?.latestRevision ?? 0,
                    expectedVersion: this.head?.version ?? 0,
                });
                this.$emit('activated', this.release.id);
            } catch (error) {
                const response = (error as HttpError<{ errors?: { code?: string; detail?: string }[] }>).response;
                const failure = response?.data.errors?.[0];
                this.conflict =
                    failure?.code === 'APPROVAL_FLOW__BINDING_REVISION_CONFLICT' ||
                    failure?.code === 'APPROVAL_FLOW__BINDING_VERSION_CONFLICT';
                this.activationError = this.conflict
                    ? this.$t('sw-approval-flow.design.activationConflict')
                    : (failure?.detail ?? this.$t('sw-approval-flow.design.activationError'));
            } finally {
                this.isActivating = false;
            }
        },
        close(): void {
            if (!this.isActivating) {
                this.$emit('close');
            }
        },
    },
});
