/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type { PropType } from 'vue';
import template from './sw-settings-organization-unit-tree.html.twig';
import './sw-settings-organization-unit-tree.scss';

const { debounce } = Shopwell.Utils;

type OrganizationUnitEntity = EntitySchema.Entity<'organization_unit'>;

/**
 * The unit tree of one organization.
 *
 * The page hands over the flat unit collection it already loaded; the tree builds the hierarchy
 * locally. A node is a root when it has no parentId or when its parent is not part of the
 * collection - the second case is what makes search work, because a filtered set is re-rooted at
 * the highest surviving ancestor instead of orphaning the matches.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['acl'],

    props: {
        units: {
            type: Array as PropType<OrganizationUnitEntity[]>,
            required: true,
        },

        selectedUnitId: {
            type: String as PropType<string | null>,
            required: false,
            default: null,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    emits: ['select-unit', 'add-child', 'add-root-unit'],

    data(): {
        searchTerm: string | null;
        expandedIds: Record<string, boolean>;
    } {
        return {
            searchTerm: null,
            expandedIds: {},
        };
    },

    computed: {
        unitById(): Record<string, OrganizationUnitEntity> {
            const map: Record<string, OrganizationUnitEntity> = {};

            this.units.forEach((unit) => {
                map[unit.id] = unit;
            });

            return map;
        },

        filteredUnits(): OrganizationUnitEntity[] {
            if (!this.isSearching) {
                return this.units;
            }

            const term = (this.searchTerm ?? '').toLowerCase();
            const keep: Record<string, boolean> = {};

            this.units.forEach((unit) => {
                const name = unit.translated?.name ?? unit.name ?? '';

                if (name.toLowerCase().includes(term)) {
                    // the match itself and every one of its ancestors stays visible
                    let current: OrganizationUnitEntity | undefined = unit;

                    while (current) {
                        keep[current.id] = true;
                        current = this.unitById[current.parentId ?? ''];
                    }
                }
            });

            return this.units.filter((unit) => keep[unit.id]);
        },

        rootUnits(): OrganizationUnitEntity[] {
            return this.filteredUnits.filter((unit) => {
                return !unit.parentId || !this.unitById[unit.parentId];
            });
        },

        isSearching(): boolean {
            return this.searchTerm !== null && this.searchTerm.length > 0;
        },
    },

    methods: {
        onSearch: debounce(function debouncedTreeSearch(this: { searchTerm: string | null }, value: string) {
            this.searchTerm = value;
        }, 300),

        childrenOf(unit: OrganizationUnitEntity): OrganizationUnitEntity[] {
            return this.filteredUnits.filter((candidate) => candidate.parentId === unit.id);
        },

        isExpanded(unit: OrganizationUnitEntity): boolean {
            return this.isSearching || !!this.expandedIds[unit.id];
        },

        toggleExpand(unit: OrganizationUnitEntity): void {
            this.expandedIds[unit.id] = !this.expandedIds[unit.id];
        },

        onSelectUnit(unit: OrganizationUnitEntity): void {
            this.$emit('select-unit', unit);
        },

        onAddChild(unit: OrganizationUnitEntity): void {
            this.$emit('add-child', unit);
        },

        onAddRootUnit(): void {
            this.$emit('add-root-unit');
        },
    },
});
