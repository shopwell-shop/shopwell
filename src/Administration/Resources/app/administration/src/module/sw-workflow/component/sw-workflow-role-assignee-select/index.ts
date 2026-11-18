/** @sw-package framework */
import { toRef } from 'vue';
import type { PropType } from 'vue';
import useWorkflowRoles from '../../composable/use-workflow-roles';
import template from './sw-workflow-role-assignee-select.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        config: { type: Object as PropType<Record<string, unknown>>, required: true },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update-config'],
    setup(props) {
        return useWorkflowRoles(toRef(props, 'config'));
    },
    computed: {
        criteria() {
            const { Criteria } = Shopwell.Data;
            return new Criteria(1, 25).addSorting(Criteria.sort('name', 'ASC'));
        },
        collection(): EntityCollection<'acl_role'> {
            const records = this.roleIds.map((id, index) => {
                const role = this.roles.find((item) => item.id === id);
                if (role) {
                    return role;
                }
                const placeholder = this.repository.create(Shopwell.Context.api, id as Entity<'acl_role'>['id']);
                placeholder.name = this.$t('sw-workflow.approval.roles.missing', { role: index + 1 });
                return placeholder;
            });
            return new Shopwell.Data.EntityCollection(
                this.repository.route,
                'acl_role',
                Shopwell.Context.api,
                null,
                records,
            );
        },
        unavailableCount(): number {
            return this.status === 'ready'
                ? this.roleIds.filter((id) => !this.roles.some((item) => item.id === id)).length
                : 0;
        },
    },
    methods: {
        change(roles: Entity<'acl_role'>[]): void {
            if (!this.allowEdit || !this.canRead || !Array.isArray(roles) || roles.length > 50) {
                return;
            }
            const ids: string[] = [];
            for (const role of roles) {
                if (!role || typeof role.id !== 'string') {
                    return;
                }
                const id = role.id.toLowerCase();
                if (!this.roleIds.includes(id) && !/^[a-f0-9]{32}$/.test(id)) {
                    return;
                }
                if (!ids.includes(id)) {
                    ids.push(id);
                }
            }
            this.$emit('update-config', { ...this.config, roleIds: ids });
        },
    },
});
