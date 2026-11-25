/** @sw-package framework */
import type { PropType } from 'vue';
import type Repository from 'src/core/data/repository.data';
import type { ApprovalDesignNode } from '../../designer/approval-editor-session';
import type { ApprovalBusinessDescriptor, ApprovalJsonValue } from '../../service/approval-flow-design.api.service';
import template from './sw-approval-flow-node-settings.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['repositoryFactory', 'acl'],
    props: {
        node: { type: Object as PropType<ApprovalDesignNode>, required: true },
        businessTypes: { type: Array as PropType<ApprovalBusinessDescriptor[]>, required: true },
        readOnly: { type: Boolean, default: false },
    },
    emits: ['update:configuration', 'select-business'],

    computed: {
        users(): Repository<'user'> {
            return this.repositoryFactory.create('user');
        },
        canReadUsers(): boolean {
            return this.acl.can('user:read');
        },
        reviewerCriteria() {
            const { Criteria } = Shopwell.Data;

            return new Criteria(1, 25)
                .addFilter(Criteria.equals('active', true))
                .addSorting(Criteria.sort('username', 'ASC'));
        },
        reviewerIds(): string[] {
            const sources = this.node.config.assignees as { userIds?: string[] }[] | undefined;

            return [...new Set(sources?.flatMap((source) => source.userIds ?? []))];
        },
        policy(): string {
            const policy = this.node.config.policy as { kind?: string } | undefined;

            return policy?.kind ?? 'core:all';
        },
        policyOptions() {
            const options = ['all', 'any', 'single'].map((name) => ({
                value: `core:${name}`,
                label: this.$t(`sw-approval-flow.designer.policy.${name}`),
            }));
            if (!options.some((option) => option.value === this.policy)) {
                options.push({ value: this.policy, label: this.policy });
            }

            return options;
        },
        businessOptions() {
            return Array.from(
                new Map(
                    this.businessTypes.map((business) => [
                        business.type,
                        {
                            value: business.type,
                            label: this.$te(business.labelSnippet) ? this.$t(business.labelSnippet) : business.type,
                        },
                    ]),
                ).values(),
            );
        },
    },

    methods: {
        update(key: string, value: ApprovalJsonValue): void {
            this.$emit('update:configuration', { ...this.node.config, [key]: value });
        },
        updatePolicy(kind: string): void {
            if (kind !== this.policy) {
                this.update('policy', { kind, reject: 'veto' });
            }
        },
        updateReviewers(userIds: string[]): void {
            this.update('assignees', userIds.length ? [{ kind: 'core:users', userIds }] : [{ kind: 'core:users' }]);
        },
    },
});
