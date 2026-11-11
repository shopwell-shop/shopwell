/** @sw-package fundamentals@framework */
import type { PropType } from 'vue';
import type { MenuTreeNode } from 'src/app/store/admin-menu-config.store';
import type { MenuDraft } from '../../page/sw-permissions-menu-list/menu-draft';
import { createMenuPresentation } from '../../page/sw-permissions-menu-list/menu-presentation';
import { collectBranchIds, filterTreeByTerm, findNode } from '../../page/sw-permissions-menu-list/tree-filter';
import {
    ancestorMenuPrivileges,
    menuLeafIds,
    operationIdsForMenus,
    operationMenuLabels,
    permissionKeysForMenus,
    requiredMenuPrivileges,
    toggleMenuSelection,
} from './role-menu-permissions';
import template from './sw-permissions-role-view-menus.html.twig';
import './sw-permissions-role-view-menus.scss';

type Role = Entity<'acl_role'> & { menuIds: string[] };

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,
    inject: ['acl', 'privileges'],
    emits: ['update-role'],
    props: {
        role: { type: Object as PropType<Role | null>, default: null },
        isLoading: { type: Boolean, default: false },
    },
    data() {
        return { term: '', selectedId: null as string | null, expandedIds: [] as string[] };
    },
    computed: {
        store() {
            return Shopwell.Store.get('adminMenuConfig');
        },
        disabled() {
            return this.isLoading || !this.acl.can('permissions.editor');
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
        filteredTree() {
            return filterTreeByTerm(this.tree, this.term);
        },
        visibleExpandedIds() {
            return this.term ? collectBranchIds(this.filteredTree) : this.expandedIds;
        },
        selectedItem() {
            return findNode(this.tree, this.selectedId);
        },
        selectedMenus() {
            return this.selectedItem ? [this.selectedItem] : [];
        },
        allPermissionKeys(): string[] {
            return this.privileges
                .getPrivilegesMappings()
                .flatMap((entry) => (entry.category === 'permissions' && typeof entry.key === 'string' ? [entry.key] : []));
        },
        linkedPermissionKeys() {
            return permissionKeysForMenus(this.tree, this.store.routeAllowlist, this.allPermissionKeys);
        },
        permissionKeys() {
            if (this.selectedId === 'other')
                return this.allPermissionKeys.filter((key) => !this.linkedPermissionKeys.includes(key));
            return this.selectedItem
                ? permissionKeysForMenus(this.selectedMenus, this.store.routeAllowlist, this.allPermissionKeys)
                : this.allPermissionKeys;
        },
        operationPages(): Record<string, string[]> {
            return operationMenuLabels(this.tree, this.store.routeAllowlist, this.privileges.getPrivilegesMappings());
        },
        selectedAdditionalPrivilegeIds(): string[] {
            const mappings = this.privileges.getPrivilegesMappings();
            if (!this.selectedItem) {
                return mappings.flatMap((entry) =>
                    entry.category === 'additional_permissions'
                        ? Object.keys(entry.roles)
                              .map((name) => `${entry.key}.${name}`)
                              .filter((id) => this.selectedId !== 'other' || !this.operationPages[id]?.length)
                        : [],
                );
            }
            return operationIdsForMenus(
                this.selectedMenus,
                this.store.routeAllowlist,
                mappings,
                ancestorMenuPrivileges(this.tree, this.selectedId ?? ''),
            );
        },
        effectivePrivileges() {
            return this.privileges.getPrivilegesForAdminPrivilegeKeys(
                (this.role?.privileges ?? []).filter((key): key is string => typeof key === 'string'),
            );
        },
        missingPrivileges() {
            return [
                ...new Set([
                    ...requiredMenuPrivileges(this.selectedMenus, this.store.routeAllowlist),
                    ...ancestorMenuPrivileges(this.tree, this.selectedId ?? ''),
                ]),
            ].filter((key) => !this.effectivePrivileges.includes(key));
        },
        canGrantRequired() {
            return this.missingPrivileges.every((key) => this.privileges.existsPrivilege(key));
        },
        permissionTitle() {
            return (
                this.selectedItem?.label ??
                this.$t(
                    this.selectedId === 'other'
                        ? 'sw-permissions.roles.menus.otherPermissions'
                        : 'sw-permissions.roles.menus.allPermissions',
                )
            );
        },
    },
    created() {
        if (!this.store.isLoaded) void this.loadMenus();
    },
    methods: {
        async loadMenus() {
            try {
                await this.store.load();
            } catch {
                /* The retry banner preserves the role draft. */
            }
        },
        toggle(item: MenuTreeNode) {
            this.expandedIds = this.expandedIds.includes(item.id)
                ? this.expandedIds.filter((id) => id !== item.id)
                : [...this.expandedIds, item.id];
        },
        selectionState(item: MenuTreeNode) {
            const fullItem = findNode(this.tree, item.id) ?? item;
            const ids = menuLeafIds([fullItem]);
            const selected = ids.filter((id) => this.role?.menuIds?.includes(id)).length;
            return {
                checked: ids.length > 0 && selected === ids.length,
                partial: selected > 0 && selected < ids.length,
                disabled: ids.length === 0,
            };
        },
        toggleSelection(item: MenuTreeNode, checked: boolean) {
            if (this.disabled) return;
            this.$emit('update-role', {
                menuIds: toggleMenuSelection(this.role?.menuIds ?? [], [findNode(this.tree, item.id) ?? item], checked),
            });
        },
        grantRequired() {
            if (this.disabled || !this.canGrantRequired) return;
            const grid = this.$refs.permissions as { addPermission: (key: string) => void } | undefined;
            this.missingPrivileges.forEach((key) => grid?.addPermission(key));
        },
    },
});
