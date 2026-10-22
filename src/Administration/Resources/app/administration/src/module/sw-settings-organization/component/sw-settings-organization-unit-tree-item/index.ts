/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-settings-organization-unit-tree-item.html.twig';
import './sw-settings-organization-unit-tree-item.scss';

type OrganizationUnitEntity = EntitySchema.Entity<'organization_unit'>;

/**
 * One node of the organization unit tree, recursive via its registered name.
 *
 * The node shows the level icon, the unit name and the count of its children - nothing else,
 * because the level in the tree is the only semantics a unit has (ADR-002): no type badge, no
 * type select. On hover it offers the "add child" action, which opens the create modal of the
 * detail page with this unit as parent.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['acl'],

    props: {
        unit: {
            type: Object as PropType<OrganizationUnitEntity>,
            required: true,
        },

        unitTree: {
            type: Object,
            required: true,
        },

        filteredUnits: {
            type: Array as PropType<OrganizationUnitEntity[]>,
            required: true,
        },

        selectedUnitId: {
            type: String as PropType<string | null>,
            required: false,
            default: null,
        },

        isSearching: {
            type: Boolean,
            required: false,
            default: false,
        },

        expandedIds: {
            type: Object as PropType<Record<string, boolean>>,
            required: true,
        },
    },

    emits: ['select-unit', 'add-child', 'toggle-expand'],

    computed: {
        unitName(): string {
            return this.unit.translated?.name ?? this.unit.name ?? '';
        },

        childUnits(): OrganizationUnitEntity[] {
            return this.filteredUnits.filter((candidate) => candidate.parentId === this.unit.id);
        },

        isSelected(): boolean {
            return this.selectedUnitId === this.unit.id;
        },

        isOpened(): boolean {
            return this.isSearching || !!this.expandedIds[this.unit.id];
        },
    },

    methods: {
        onSelectUnit(): void {
            this.$emit('select-unit', this.unit);
        },

        onAddChild(event: MouseEvent): void {
            event.stopPropagation();
            this.$emit('add-child', this.unit);
        },

        onToggleExpand(event: MouseEvent): void {
            event.stopPropagation();
            this.$emit('toggle-expand', this.unit);
        },
    },
});
