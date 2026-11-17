/** @sw-package framework */
import { computed } from 'vue';
import type { PropType } from 'vue';
import useWorkflowUsers from '../../composable/use-workflow-users';
import template from './sw-workflow-users-assignee-select.html.twig';
import './sw-workflow-users-assignee-select.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['repositoryFactory'],
    props: {
        config: { type: Object as PropType<Record<string, unknown>>, required: true },
        allowEdit: { type: Boolean, default: true },
        labelKey: { type: String, default: 'sw-workflow.approval.assignee' },
        placeholderKey: { type: String, default: 'sw-workflow.approval.multiple.status.empty' },
    },
    emits: ['update-config'],
    setup(props) {
        const userIds = computed(() =>
            Array.isArray(props.config.userIds)
                ? [...new Set(props.config.userIds.filter((id: unknown): id is string => typeof id === 'string'))]
                : [],
        );
        return { userIds, ...useWorkflowUsers(userIds) };
    },
    computed: {
        repository() {
            return this.repositoryFactory.create('user');
        },
        criteria() {
            const { Criteria } = Shopwell.Data;
            return new Criteria(1, 25)
                .addFilter(Criteria.equals('active', true))
                .addSorting(Criteria.sort('username', 'ASC'));
        },
        collection(): EntityCollection<'user'> {
            const members = this.userIds.map((id, index) => {
                const user = this.users.find((item) => item.id.toLowerCase() === id.toLowerCase());

                if (user) {
                    return user;
                }

                const placeholder = this.repository.create(Shopwell.Context.api, id as Entity<'user'>['id']);
                placeholder.name = this.$t(
                    'sw-workflow.approval.multiple.' + (this.status === 'ready' ? 'missingMember' : 'unverifiedMember'),
                    { position: index + 1 },
                );
                placeholder.username = '';
                placeholder.active = false;
                return placeholder;
            });
            return new Shopwell.Data.EntityCollection(this.repository.route, 'user', Shopwell.Context.api, null, members);
        },
        unavailableCount(): number {
            return this.status === 'ready'
                ? this.userIds.filter(
                      (id) => !this.users.some((user) => user.id.toLowerCase() === id.toLowerCase() && user.active),
                  ).length
                : 0;
        },
    },
    methods: {
        label(user: Entity<'user'>): string {
            const name = user.name || user.username;
            return this.status === 'ready' && !user.active && this.users.some((item) => item.id === user.id)
                ? this.$t('sw-workflow.approval.multiple.inactiveMember', { name })
                : name;
        },
        change(users: Entity<'user'>[]): void {
            if (!this.allowEdit || !this.canRead || !Array.isArray(users)) {
                return;
            }

            const existing = new Set(this.userIds.map((id) => id.toLowerCase()));
            const ids: string[] = [];
            const seen = new Set<string>();
            for (const user of users) {
                if (!user || typeof user.id !== 'string') {
                    return;
                }

                const id = user.id.toLowerCase();

                if (!existing.has(id) && (!/^[a-f0-9]{32}$/.test(id) || !user.active)) {
                    return;
                }

                if (!seen.has(id)) {
                    ids.push(user.id);
                }

                seen.add(id);
            }
            this.$emit('update-config', { ...this.config, userIds: ids });
        },
    },
});
