/** @sw-package fundamentals@framework */
import type { PropType } from 'vue';
import type { MenuDraft } from '../../page/sw-permissions-menu-list/menu-draft';
import { createMenuPresentation } from '../../page/sw-permissions-menu-list/menu-presentation';
import { collectBranchIds, filterTreeByTerm } from '../../page/sw-permissions-menu-list/tree-filter';
import { toggleMenuSelection } from './role-menu-permissions';
import {
    createPermissionRows,
    dependencyClosure,
    independentPrivileges,
    revokeMenuOperations,
    rowMenuIds,
    selectedMenuRequirements,
    type PermissionRow,
} from './permission-tree';
import template from './sw-permissions-role-view-menus.html.twig';
import './sw-permissions-role-view-menus.scss';

type Role = Entity<'acl_role'> & { menuIds: string[] };
const signature = (ids: string[]) => JSON.stringify([...new Set(ids)].sort());

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['acl', 'privileges'],
    emits: ['update-role'],
    props: {
        role: { type: Object as PropType<Role | null>, default: null },
        isLoading: { type: Boolean, default: false },
        searchTerm: { type: String, default: '' },
    },
    data() {
        return {
            expandedIds: [] as string[],
            expansionInitialized: false,
            explicitPrivileges: [] as string[],
            expectedSelection: '',
        };
    },
    computed: {
        store() {
            return Shopwell.Store.get('adminMenuConfig');
        },
        disabled(): boolean {
            return this.isLoading || !this.role || !this.acl.can('permissions.editor');
        },
        tree() {
            return createMenuPresentation({
                items: this.store.items,
                tree: this.store.tree,
                routeAllowlist: this.store.routeAllowlist,
                draft: null as MenuDraft | null,
                languageId: Shopwell.Context.api.languageId ?? Shopwell.Defaults.systemLanguageId,
                translate: (key: string) => this.$t(key),
            }).tree;
        },
        permissionRows(): PermissionRow[] {
            return createPermissionRows(this.tree, this.store.routeAllowlist, this.privileges, (key) => this.$t(key));
        },
        isAllExpanded(): boolean {
            const ids = collectBranchIds(this.permissionRows);
            return ids.length > 0 && ids.every((id) => this.expandedIds.includes(id));
        },
        visibleRows(): { item: PermissionRow; depth: number }[] {
            const filtered = filterTreeByTerm(this.permissionRows, this.searchTerm);
            const expanded = this.searchTerm.trim() ? collectBranchIds(filtered) : this.expandedIds;
            const walk = (items: PermissionRow[], depth: number): { item: PermissionRow; depth: number }[] =>
                items.flatMap((item) => [
                    { item, depth },
                    ...(expanded.includes(item.id) ? walk(item.children, depth + 1) : []),
                ]);
            return walk(filtered, 0);
        },
        selectedPrivileges(): string[] {
            return (this.role?.privileges ?? []).filter((id): id is string => typeof id === 'string');
        },
        effectivePrivileges(): string[] {
            return dependencyClosure(this.selectedPrivileges, this.privileges);
        },
        menuRequirements(): string[] {
            return selectedMenuRequirements(this.tree, this.role?.menuIds ?? [], this.store.routeAllowlist);
        },
        lockedPrivileges(): string[] {
            const dependencies = this.selectedPrivileges.flatMap((id) =>
                dependencyClosure(this.privileges.getPrivilegeRole(id)?.dependencies ?? [], this.privileges),
            );
            return [...new Set([...dependencies, ...this.menuRequirements])];
        },
    },
    watch: {
        role() {
            this.expectedSelection = '';
            this.explicitPrivileges = [];
            this.syncPrivileges();
        },
        'role.privileges': {
            immediate: true,
            deep: true,
            handler() {
                this.syncPrivileges();
            },
        },
        permissionRows: {
            immediate: true,
            handler(rows: PermissionRow[]) {
                if (this.expansionInitialized || !this.tree.length) return;
                this.expandedIds = rows.filter((row) => row.menu && row.children.length).map((row) => row.id);
                this.expansionInitialized = true;
            },
        },
    },
    created() {
        if (!this.store.isLoaded) void this.loadMenus();
    },
    methods: {
        syncPrivileges() {
            if (!this.role || signature(this.selectedPrivileges) === this.expectedSelection) return;
            this.explicitPrivileges = independentPrivileges(this.selectedPrivileges, this.privileges);
            const normalized = dependencyClosure(this.selectedPrivileges, this.privileges);
            this.expectedSelection = signature(normalized);
            if (this.acl.can('permissions.editor') && signature(this.selectedPrivileges) !== this.expectedSelection) {
                this.$emit('update-role', { privileges: normalized });
            }
        },
        async loadMenus() {
            try {
                await this.store.load();
            } catch {
                // Keep the unsaved role available while the menu load is retried.
            }
        },
        toggleExpanded(id: string) {
            this.expandedIds = this.expandedIds.includes(id)
                ? this.expandedIds.filter((value) => value !== id)
                : [...this.expandedIds, id];
        },
        toggleAll() {
            this.expandedIds = this.isAllExpanded ? [] : collectBranchIds(this.permissionRows);
        },
        selectionState(row: PermissionRow) {
            const ids = rowMenuIds(row);
            const selected = ids.filter((id) => this.role?.menuIds?.includes(id)).length;
            return { checked: ids.length > 0 && selected === ids.length, partial: selected > 0 && selected < ids.length };
        },
        canSelectMenu(row: PermissionRow): boolean {
            if (!row.menu || !rowMenuIds(row).length) return false;
            const menuIds = toggleMenuSelection(this.role?.menuIds ?? [], [row.menu], true);
            const requirements = selectedMenuRequirements(this.tree, menuIds, this.store.routeAllowlist);
            return requirements.every((id) => this.privileges.existsPrivilege(id) || this.effectivePrivileges.includes(id));
        },
        isOperationDisabled(row: PermissionRow, id: string): boolean {
            if (this.disabled || this.lockedPrivileges.includes(id)) return true;
            if (this.privileges.getPrivilegeRole(id)?.menuIndependent) return false;
            const menuId = row.menu?.id ?? row.ownerMenuId;
            return !!menuId && !this.role?.menuIds?.includes(menuId) && !this.effectivePrivileges.includes(id);
        },
        emitSelection(roots: string[], menuIds: string[]) {
            const required = selectedMenuRequirements(this.tree, menuIds, this.store.routeAllowlist);
            const privileges = dependencyClosure([...roots, ...required], this.privileges);
            this.explicitPrivileges = roots;
            this.expectedSelection = signature(privileges);
            this.$emit('update-role', { privileges, menuIds });
        },
        toggleSelection(row: PermissionRow, checked: boolean) {
            if (this.disabled || !row.menu || (checked && !this.canSelectMenu(row))) return;
            const ids = rowMenuIds(row);
            const menuIds = toggleMenuSelection(this.role?.menuIds ?? [], [row.menu], checked);
            const roots = checked
                ? this.explicitPrivileges
                : revokeMenuOperations(this.explicitPrivileges, this.permissionRows, ids, menuIds);
            this.emitSelection(roots, menuIds);
        },
        toggleOperation(row: PermissionRow, id: string, checked: boolean) {
            if (this.isOperationDisabled(row, id)) return;
            const roots = checked
                ? [...new Set([...this.explicitPrivileges, id])]
                : this.explicitPrivileges.filter((value) => value !== id);
            this.emitSelection(roots, this.role?.menuIds ?? []);
        },
        hasMenuSelection(row: PermissionRow): boolean {
            return rowMenuIds(row).length > 0;
        },
        menuLabel(row: PermissionRow): string {
            return this.$t('sw-permissions.roles.assignment.selectMenu', { name: row.label });
        },
        operationLabel(row: PermissionRow, label: string): string {
            return `${row.label} · ${label}`;
        },
    },
});
