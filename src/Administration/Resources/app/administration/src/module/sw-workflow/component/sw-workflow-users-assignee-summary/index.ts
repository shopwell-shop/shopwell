/** @sw-package framework */
import { computed } from 'vue';
import type { PropType } from 'vue';
import useWorkflowUsers from '../../composable/use-workflow-users';
import template from './sw-workflow-users-assignee-summary.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: { config: { type: Object as PropType<Record<string, unknown>>, required: true } },
    setup(props) {
        const userIds = computed<string[]>(() => {
            const ids = props.config.userIds;
            return Array.isArray(ids)
                ? [...new Set(ids.filter((id): id is string => typeof id === 'string').map((id) => id.toLowerCase()))]
                : [];
        });
        return { userIds, ...useWorkflowUsers(userIds) };
    },
    computed: {
        members(): { label: string; unavailable: boolean }[] {
            const users = new Map(this.users.map((user) => [user.id.toLowerCase(), user]));
            return this.userIds.map((id, index) => {
                const user = users.get(id);
                const name = user?.name || user?.username;
                if (!user || !name) {
                    return {
                        label: this.$t('sw-workflow.approval.multiple.missingMember', { position: index + 1 }),
                        unavailable: true,
                    };
                }
                return {
                    label: user.active ? name : this.$t('sw-workflow.approval.multiple.inactiveMember', { name }),
                    unavailable: !user.active,
                };
            });
        },
        tooltip(): string | undefined {
            return this.status === 'ready'
                ? this.members.map((member) => member.label).join(this.$t('sw-workflow.approval.multiple.separator'))
                : undefined;
        },
        summary(): string {
            if (this.status !== 'ready') return this.$t(`sw-workflow.approval.multiple.status.${this.status}`);
            const separator = this.$t('sw-workflow.approval.multiple.separator');
            const names = this.members
                .slice(0, 2)
                .map((member) => member.label)
                .join(separator);
            if (this.members.length <= 2) return names;
            const summary = this.$t('sw-workflow.approval.multiple.more', { names, count: this.members.length });
            if (!this.members.slice(2).some((member) => member.unavailable)) return summary;
            return [
                summary,
                this.$t('sw-workflow.approval.multiple.unavailableMembers', {
                    count: this.members.filter((member) => member.unavailable).length,
                }),
            ].join(separator);
        },
    },
});
