/** @sw-package framework */
import { computed } from 'vue';
import type { PropType } from 'vue';
import useWorkflowUser from '../../composable/use-workflow-user';
import template from './sw-workflow-user-assignee-select.html.twig';
import './sw-workflow-user-assignee-select.scss';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        config: { type: Object as PropType<Record<string, unknown>>, required: true },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update-config'],
    setup(props) {
        const userId = computed(() => (typeof props.config.userId === 'string' ? props.config.userId : null));
        return { userId, ...useWorkflowUser(userId) };
    },
    computed: {
        criteria() {
            const { Criteria } = Shopwell.Data;
            return new Criteria(1, 25)
                .addFilter(Criteria.equals('active', true))
                .addSorting(Criteria.sort('username', 'ASC'));
        },
    },
    methods: {
        displayUser(user: Entity<'user'>): string {
            return user.name && user.name !== user.username ? `${user.name} (${user.username})` : user.username;
        },
        select(id: string | null, user?: Entity<'user'>): void {
            // The shared selector also emits null when an account disappears. Only explicit clear may discard an ID.
            if (!this.allowEdit || !this.canRead || !id || !user?.active || user.id !== id) {
                return;
            }

            this.$emit('update-config', { ...this.config, userId: id });
        },
        clear(): void {
            if (this.allowEdit && this.canRead) {
                this.$emit('update-config', { ...this.config, userId: null });
            }
        },
    },
});
