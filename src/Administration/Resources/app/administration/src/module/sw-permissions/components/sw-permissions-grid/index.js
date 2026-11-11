/**
 * @sw-package fundamentals@framework
 */
import template from './sw-permissions-grid.html.twig';
import './sw-permissions-grid.scss';

const { Mixin } = Shopwell;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['privileges'],

    mixins: [Mixin.getByName('notification')],

    props: {
        title: { type: String, default: '' },
        permissionKeys: {
            type: Array,
            default: null,
        },
        role: {
            type: Object,
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    watch: {
        role: {
            handler() {
                this.reconcileSelection();
            },
            immediate: true,
        },
    },

    computed: {
        permissionsWithParents() {
            const permissionsWithParents = [];

            this.parents.forEach((parent) => {
                permissionsWithParents.push({
                    type: 'parent',
                    value: parent,
                });

                const children = this.getPermissionsForParent(parent);

                children.forEach((child, index) => {
                    permissionsWithParents.push({ ...child, groupIndex: index });
                });
            });

            return permissionsWithParents;
        },

        permissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return privileges
                .filter(
                    (privilege) =>
                        privilege.category === 'permissions' &&
                        (this.permissionKeys === null || this.permissionKeys.includes(privilege.key)),
                )
                .sort((a, b) => {
                    const labelA = this.$t(`sw-privileges.permissions.${a.key}.label`);
                    const labelB = this.$t(`sw-privileges.permissions.${b.key}.label`);

                    return labelA.localeCompare(labelB);
                });
        },

        parents() {
            return this.permissions
                .reduce((parents, privilege) => {
                    if (parents.includes(privilege.parent)) {
                        return parents;
                    }

                    return [...parents, privilege.parent];
                }, [])
                .sort((a, b) => this.compareParents(a, b));
        },

        selectedPrivileges() {
            return this.role?.privileges ?? [];
        },

        /**
         * Identifiers that are required by another selected privilege. The dependency graph is owned by
         * `acl/index.js`, so it is resolved through the privileges service instead of being duplicated here.
         */
        usedDependencies() {
            const dependencies = new Set();

            this.selectedPrivileges.forEach((privilegeKey) => {
                this.dependenciesOf(privilegeKey).forEach((dependency) => {
                    dependencies.add(dependency);
                });
            });

            return [...dependencies];
        },

        roles() {
            return [
                'viewer',
                'editor',
                'creator',
                'deleter',
            ];
        },

        // Mirrors the order of the main navigation; unknown parents follow alphabetically, "other" is last.
        parentOrder() {
            return [
                'catalogues',
                'orders',
                'customers',
                'content',
                'marketing',
                'settings',
                'system',
            ];
        },

        // Parents whose label lives under a snippet key that differs from the privilege parent itself.
        parentSnippetKeys() {
            return {
                catalogues: 'products',
            };
        },
    },

    methods: {
        parentLabel(parentValue) {
            const key = parentValue || 'other';
            const snippetKey = Object.hasOwn(this.parentSnippetKeys, key) ? this.parentSnippetKeys[key] : key;

            return this.$t(`sw-privileges.permissions.parents.${snippetKey}`);
        },

        compareParents(a, b) {
            const keyA = a || 'other';
            const keyB = b || 'other';

            const rank = (key) => {
                if (key === 'other') {
                    return this.parentOrder.length + 1;
                }

                const index = this.parentOrder.indexOf(key);

                return index === -1 ? this.parentOrder.length : index;
            };

            const rankDiff = rank(keyA) - rank(keyB);
            if (rankDiff !== 0) {
                return rankDiff;
            }

            return this.parentLabel(a).localeCompare(this.parentLabel(b));
        },

        dependenciesOf(identifier) {
            return this.privileges.getPrivilegeRole(identifier)?.dependencies ?? [];
        },

        /**
         * Whether another selected privilege depends on the given one. Such a privilege can not be
         * unchecked on its own — it only disappears together with the privileges requiring it.
         */
        isRequiredBySelection(identifier) {
            return this.usedDependencies.includes(identifier);
        },

        /**
         * A locked privilege is selected and required by another selection at the same time. Required but
         * unselected identifiers are not locked: they may exist in roles stored before the dependency was
         * introduced, and the user has to be able to restore the closure by selecting them.
         */
        isLocked(identifier) {
            return this.selectedPrivileges.includes(identifier) && this.isRequiredBySelection(identifier);
        },

        /**
         * Roles stored before a dependency was declared (or written by an extension) can miss it. Aligning
         * the selection right after loading keeps the checkboxes in sync with what saving would store
         * anyway: `getPrivilegesForAdminPrivilegeKeys()` expands exactly these dependencies. The added
         * privileges are visible in the grid before the user saves, so nothing is granted behind their back.
         */
        reconcileSelection() {
            if (!this.role?.privileges) {
                return;
            }

            [...this.role.privileges].forEach((identifier) => {
                this.addDependenciesForRole(identifier);
            });
        },

        changePermission(permissionKey, permissionRole) {
            const identifier = `${permissionKey}.${permissionRole}`;

            if (this.isPermissionSelected(permissionKey, permissionRole)) {
                this.removePermission(identifier);
            } else {
                this.addPermission(identifier);
            }
        },

        addPermission(identifier) {
            if (this.role.privileges.includes(identifier)) {
                return;
            }

            this.role.privileges.push(identifier);

            this.addDependenciesForRole(identifier);
        },

        addDependenciesForRole(identifier) {
            this.dependenciesOf(identifier).forEach((dependencyIdentifier) => {
                this.addPermission(dependencyIdentifier);
            });
        },

        removePermission(identifier) {
            this.role.privileges = this.role.privileges.filter((privilege) => {
                return privilege !== identifier;
            });
        },

        /**
         * Removes a batch of privileges and restores every privilege that is still required by one of the
         * remaining selections afterwards. Without the restore step a batch action ("uncheck this row",
         * "uncheck all roles of this entity") could break the dependency closure — e.g. drop `viewer`
         * while `editor` stays selected.
         */
        removePermissionsWithClosure(identifiers) {
            identifiers.forEach((identifier) => {
                this.removePermission(identifier);
            });

            const kept = identifiers.filter((identifier) => this.isRequiredBySelection(identifier));

            kept.forEach((identifier) => {
                this.addPermission(identifier);
            });

            if (kept.length > 0) {
                this.createNotificationInfo({
                    message: this.$t(
                        'sw-permissions.roles.grid.notification.keptDependencies.message',
                        { count: kept.length },
                        0,
                    ),
                });
            }

            return kept;
        },

        isPermissionSelected(permissionKey, permissionRole) {
            return this.role.privileges.some((privilege) => {
                return privilege === `${permissionKey}.${permissionRole}`;
            });
        },

        isPermissionDisabled(permissionKey, permissionRole) {
            return this.isLocked(`${permissionKey}.${permissionRole}`);
        },

        privilegeTooltip(permissionKey, permissionRole) {
            const operationMap = {
                viewer: 'read',
                editor: 'update',
                creator: 'create',
                deleter: 'delete',
            };
            return `${permissionKey}:${operationMap[permissionRole] ?? permissionRole}`;
        },

        /**
         * Roles of the selections currently requiring the given identifier, e.g. `['editor']` for a
         * locked `product.viewer`.
         */
        dependantsOf(identifier) {
            const dependants = this.selectedPrivileges
                .filter((selected) => this.dependenciesOf(selected).includes(identifier))
                .map((selected) => selected.split('.')[1]);

            return [...new Set(dependants)];
        },

        disabledPrivilegeTooltip(permissionKey, permissionRole) {
            const dependants = this.dependantsOf(`${permissionKey}.${permissionRole}`).map((permissionRoleKey) => {
                return this.$t(`sw-privileges.roles.${permissionRoleKey}`);
            });

            return this.$t('sw-permissions.roles.grid.disabledCheckboxMessage', {
                roles: dependants.join(', '),
            });
        },

        parentRoleTooltip(parentValue, role) {
            return this.$t('sw-permissions.roles.grid.tooltipParentRole', {
                role: this.$t(`sw-privileges.roles.${role}`),
                parent: this.parentLabel(parentValue),
            });
        },

        allRolesLabel() {
            return this.roles.map((r) => this.$t(`sw-privileges.roles.${r}`)).join(', ');
        },

        parentAllTooltip(parentValue) {
            return this.$t('sw-permissions.roles.grid.tooltipParentAll', {
                parent: this.parentLabel(parentValue),
                roles: this.allRolesLabel(),
            });
        },

        entityAllTooltip(permissionKey) {
            return this.$t('sw-permissions.roles.grid.tooltipEntityAll', {
                entity: this.$t(`sw-privileges.permissions.${permissionKey}.label`),
                roles: this.allRolesLabel(),
            });
        },

        changeAllPermissionsForKey(permissionKey) {
            const identifiers = this.identifiersForKey(permissionKey);

            if (this.allPermissionsForKeySelected(permissionKey)) {
                this.removePermissionsWithClosure(identifiers);
                return;
            }

            identifiers.forEach((identifier) => {
                this.addPermission(identifier);
            });
        },

        allPermissionsForKeySelected(permissionKey) {
            return this.identifiersForKey(permissionKey).every((identifier) => {
                return this.selectedPrivileges.includes(identifier);
            });
        },

        somePermissionsForKeySelected(permissionKey) {
            if (this.allPermissionsForKeySelected(permissionKey)) {
                return false;
            }

            return this.identifiersForKey(permissionKey).some((identifier) => {
                return this.selectedPrivileges.includes(identifier);
            });
        },

        /**
         * Every existing privilege of the entity, in role order. Privileges that are not registered
         * (e.g. an entity with a viewer role only) must never be written into the role.
         */
        identifiersForKey(permissionKey, permissionRoles = this.roles) {
            return permissionRoles
                .map((permissionRole) => `${permissionKey}.${permissionRole}`)
                .filter((identifier) => this.privileges.existsPrivilege(identifier));
        },

        getPermissionsForParent(parentKey) {
            return this.permissions.filter((permission) => {
                return permission.parent === parentKey;
            });
        },

        /**
         * Identifiers of one parent row. `permissionRoles` narrows the selection to a single role column.
         */
        identifiersForParent(parentKey, permissionRoles = this.roles) {
            return permissionRoles.reduce((identifiers, permissionRole) => {
                const roleIdentifiers = this.getPermissionsForParent(parentKey)
                    .filter((permission) => {
                        return permission.roles[permissionRole] !== undefined;
                    })
                    .map((permission) => `${permission.key}.${permissionRole}`);

                return [...identifiers, ...roleIdentifiers];
            }, []);
        },

        areAllChildrenRolesSelected(parentKey, roleKey) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);

            const hasUnselected = permissionsForParent.some((permission) => {
                if (permission.roles[roleKey] === undefined) {
                    return false;
                }

                return !this.isPermissionSelected(permission.key, roleKey);
            });

            return !hasUnselected;
        },

        areAllChildrenWithAllRolesSelected(parentKey) {
            return this.roles.every((roleKey) => {
                return this.areAllChildrenRolesSelected(parentKey, roleKey);
            });
        },

        areSomeChildrenRolesSelected(parentKey, roleKey, ignoreMissingPrivilege = true) {
            const permissionsForParent = this.getPermissionsForParent(parentKey);

            return permissionsForParent.some((permission) => {
                if (!ignoreMissingPrivilege) {
                    const privilegeExists = this.privileges.existsPrivilege(`${permission.key}.${roleKey}`);

                    if (!privilegeExists) {
                        return true;
                    }
                }

                return this.isPermissionSelected(permission.key, roleKey);
            });
        },

        areSomeChildrenWithAllRolesSelected(parentKey) {
            return this.roles.every((roleKey) => {
                return this.areSomeChildrenRolesSelected(parentKey, roleKey, false);
            });
        },

        /**
         * A row can only be unchecked as a whole when at least one of its privileges is not locked.
         * Otherwise the checkbox is disabled instead of silently doing nothing.
         */
        isParentRoleDisabled(parentKey, roleKey) {
            const identifiers = this.identifiersForParent(parentKey, [roleKey]);

            return identifiers.length > 0 && identifiers.every((identifier) => this.isLocked(identifier));
        },

        isParentAllRolesDisabled(parentKey) {
            const identifiers = this.identifiersForParent(parentKey);

            return identifiers.length > 0 && identifiers.every((identifier) => this.isLocked(identifier));
        },

        isEntityAllRolesDisabled(permissionKey) {
            const identifiers = this.identifiersForKey(permissionKey);

            return identifiers.length > 0 && identifiers.every((identifier) => this.isLocked(identifier));
        },

        toggleAllChildrenWithRole(parentKey, roleKey) {
            const identifiers = this.identifiersForParent(parentKey, [roleKey]);

            if (this.areAllChildrenRolesSelected(parentKey, roleKey)) {
                this.removePermissionsWithClosure(identifiers);
                return;
            }

            identifiers.forEach((identifier) => {
                this.addPermission(identifier);
            });
        },

        toggleAllChildrenWithAllRoles(parentKey) {
            const identifiers = this.identifiersForParent(parentKey);

            if (this.areAllChildrenWithAllRolesSelected(parentKey)) {
                this.removePermissionsWithClosure(identifiers);
                return;
            }

            identifiers.forEach((identifier) => {
                this.addPermission(identifier);
            });
        },

        parentRoleHasChildRoles(parentKey, roleKey) {
            return this.getPermissionsForParent(parentKey).some((currentRole) => {
                return currentRole.roles[roleKey] !== undefined;
            });
        },
    },
};
