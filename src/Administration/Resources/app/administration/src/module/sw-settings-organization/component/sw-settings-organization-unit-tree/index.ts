/**
 * @sw-package framework
 */
import type { PropType } from 'vue';
import template from './sw-settings-organization-unit-tree.html.twig';
import './sw-settings-organization-unit-tree.scss';

type Organization = EntitySchema.Entity<'organization'>;
type Unit = EntitySchema.Entity<'organization_unit'>;
type NodeData = { organization: Organization | null; unit: Unit | null };
type TreeNode = { id: string; data: NodeData };
type TreeRef = { openTreeById: (id: string) => void };

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['acl'],
    props: {
        organizations: { type: Array as PropType<Organization[]>, required: true },
        units: { type: Array as PropType<Unit[]>, required: true },
        unitCounts: { type: Object as PropType<Record<string, number>>, default: () => ({}) },
        loadedOrganizationIds: { type: Array as PropType<string[]>, default: () => [] },
        selectedOrganizationId: { type: String as PropType<string | null>, default: null },
        selectedUnitId: { type: String as PropType<string | null>, default: null },
        isLoading: { type: Boolean, default: false },
        disabled: { type: Boolean, default: false },
        searchTerm: { type: String, default: '' },
    },
    emits: [
        'select-organization',
        'select-unit',
        'add-root',
        'add-child',
        'delete-organization',
        'delete-unit',
        'load-organization',
    ],
    computed: {
        activeNodeId(): string {
            if (this.selectedUnitId) {
                return `unit:${this.selectedUnitId}`;
            }
            return this.selectedOrganizationId ? `organization:${this.selectedOrganizationId}` : '';
        },
        canManage(): boolean {
            return this.acl.can('organization.creator') || this.acl.can('organization.deleter');
        },
        nodes() {
            const counts = new Map<string, number>();
            const unitById = new Map(this.units.map((unit) => [unit.id, unit]));
            const unitNodes = this.units.map((unit) => {
                const parent = unit.parentId ? unitById.get(unit.parentId) : null;
                const parentId =
                    parent && parent.organizationId === unit.organizationId
                        ? `unit:${parent.id}`
                        : `organization:${unit.organizationId}`;
                counts.set(parentId, (counts.get(parentId) ?? 0) + 1);
                return {
                    id: `unit:${unit.id}`,
                    parentId,
                    name: this.getName(unit, 'sw-settings-organization.detail.titleCreateUnit'),
                    active: unit.active,
                    organization: null,
                    unit,
                };
            });
            return [
                ...this.organizations.map((organization) => ({
                    id: `organization:${organization.id}`,
                    parentId: null,
                    name: this.getName(organization, 'sw-settings-organization.list.modalTitleCreate'),
                    active: organization.active,
                    organization,
                    unit: null,
                })),
                ...unitNodes,
            ].map((node) => ({
                ...node,
                translated: { name: node.name },
                afterId: null,
                childCount:
                    counts.get(node.id) ??
                    (node.organization && !this.loadedOrganizationIds.includes(node.organization.id)
                        ? (this.unitCounts[node.organization.id] ?? 0)
                        : 0),
            }));
        },
        filteredNodes() {
            const term = this.searchTerm.trim().toLocaleLowerCase();
            if (!term) {
                return this.nodes;
            }
            const byId = new Map(this.nodes.map((node) => [node.id, node]));
            const keep = new Set<string>();
            const matchingOrganizations = new Set<string>(
                this.organizations
                    .filter((organization) => this.getName(organization, '').toLocaleLowerCase().includes(term))
                    .map((organization) => organization.id),
            );
            this.nodes.forEach((node) => {
                if (
                    !node.name.toLocaleLowerCase().includes(term) &&
                    !matchingOrganizations.has(this.getOrganizationId(node)) &&
                    !node.unit?.isNew?.() &&
                    !node.organization?.isNew?.()
                ) {
                    return;
                }
                let current: typeof node | undefined = node;
                while (current && !keep.has(current.id)) {
                    keep.add(current.id);
                    current = current.parentId ? byId.get(current.parentId) : undefined;
                }
            });
            const filtered = this.nodes.filter((node) => keep.has(node.id));
            const counts = new Map<string, number>();
            filtered.forEach((node) => {
                if (node.parentId) {
                    counts.set(node.parentId, (counts.get(node.parentId) ?? 0) + 1);
                }
            });
            return filtered.map((node) => ({ ...node, childCount: counts.get(node.id) ?? 0 }));
        },
    },
    watch: {
        isLoading() {
            void this.openSelection();
        },
        activeNodeId() {
            void this.openSelection();
        },
        filteredNodes() {
            void this.openSelection();
        },
    },
    mounted() {
        void this.openSelection();
    },
    methods: {
        getOrganizationId(node: NodeData): string {
            return node.unit ? node.unit.organizationId : (node.organization?.id ?? '');
        },
        getName(entity: Organization | Unit, fallback: string): string {
            return (entity.name ?? entity.translated?.name) || (fallback ? this.$t(fallback) : '');
        },
        async openSelection(): Promise<void> {
            await this.$nextTick();
            const tree = this.$refs.tree as TreeRef | undefined;
            if (this.searchTerm.trim()) {
                this.filteredNodes.filter((node) => node.childCount > 0).forEach((node) => tree?.openTreeById(node.id));
            } else {
                const selected = this.nodes.find((node) => node.id === this.activeNodeId);
                const expandId = selected?.childCount ? selected.id : selected?.parentId;
                if (expandId) {
                    tree?.openTreeById(expandId);
                }
            }
        },
        isSelected(node: TreeNode): boolean {
            return node.id === this.activeNodeId;
        },
        isNew(node: TreeNode): boolean {
            return !!(node.data.unit ?? node.data.organization)?.isNew?.();
        },
        getItemUrl(node: TreeNode): string {
            return this.$router.resolve({
                name: 'sw.settings.organization.detail',
                params: { id: node.data.unit?.organizationId ?? node.data.organization?.id },
                query: node.data.unit ? { unitId: node.data.unit.id } : {},
            }).href;
        },
        onSelectNode(node: TreeNode): void {
            if (this.disabled) {
                return;
            }
            if (node.data.unit) {
                this.$emit('select-unit', node.data.unit);
            } else {
                this.$emit('select-organization', node.data.organization);
            }
        },
        onAdd(node: TreeNode): void {
            if (this.disabled || this.isNew(node) || !this.acl.can('organization.creator')) {
                return;
            }
            if (node.data.unit) {
                this.$emit('add-child', node.data.unit);
            } else {
                this.$emit('add-root', node.data.organization);
            }
        },
        onDelete(node: TreeNode): void {
            if (this.disabled || this.isNew(node) || !this.acl.can('organization.deleter')) {
                return;
            }
            if (node.data.unit) {
                this.$emit('delete-unit', node.data.unit);
            } else {
                this.$emit('delete-organization', node.data.organization);
            }
        },
        onLoadNode(id: string): void {
            if (
                id.startsWith('organization:') &&
                !this.organizations.find((organization) => `organization:${organization.id}` === id)?.isNew?.()
            ) {
                this.$emit('load-organization', id.slice('organization:'.length));
            }
        },
    },
});
