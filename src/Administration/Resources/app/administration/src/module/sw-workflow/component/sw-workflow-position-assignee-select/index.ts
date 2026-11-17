/** @sw-package framework */
import { toRef } from 'vue';
import type { PropType } from 'vue';
import useWorkflowPositions from '../../composable/use-workflow-positions';
import template from './sw-workflow-position-assignee-select.html.twig';

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    props: {
        config: { type: Object as PropType<Record<string, unknown>>, required: true },
        allowEdit: { type: Boolean, default: true },
    },
    emits: ['update-config'],
    setup(props) {
        return useWorkflowPositions(toRef(props, 'config'));
    },
    computed: {
        criteria() {
            const { Criteria } = Shopwell.Data;
            return new Criteria(1, 25).addFilter(Criteria.equals('active', true)).addSorting(Criteria.sort('name', 'ASC'));
        },
        collection(): EntityCollection<'position'> {
            const records = this.positionIds.map((id, index) => {
                const position = this.positions.find((item) => item.id === id);
                if (position) {
                    return position;
                }
                const placeholder = this.repository.create(Shopwell.Context.api, id as Entity<'position'>['id']);
                placeholder.name = this.$t('sw-workflow.approval.positions.missing', { position: index + 1 });
                placeholder.active = false;
                return placeholder;
            });
            return new Shopwell.Data.EntityCollection(
                this.repository.route,
                'position',
                Shopwell.Context.api,
                null,
                records,
            );
        },
        unavailableCount(): number {
            return this.status === 'ready'
                ? this.positionIds.filter((id) => !this.positions.some((item) => item.id === id && item.active)).length
                : 0;
        },
    },
    methods: {
        label(position: Entity<'position'>): string {
            const name = position.translated?.name || position.name;
            return position.active ? name : this.$t('sw-workflow.approval.positions.unavailable', { name });
        },
        change(positions: Entity<'position'>[]): void {
            if (!this.allowEdit || !this.canRead || !Array.isArray(positions) || positions.length > 50) {
                return;
            }
            const ids: string[] = [];
            for (const position of positions) {
                if (!position || typeof position.id !== 'string') {
                    return;
                }
                const id = position.id.toLowerCase();
                if (!this.positionIds.includes(id) && (!/^[a-f0-9]{32}$/.test(id) || !position.active)) {
                    return;
                }
                if (!ids.includes(id)) {
                    ids.push(id);
                }
            }
            this.$emit('update-config', { ...this.config, positionIds: ids });
        },
    },
});
