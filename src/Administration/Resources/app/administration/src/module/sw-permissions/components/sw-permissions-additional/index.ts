/**
 * @sw-package fundamentals@framework
 */
import type { PropType } from 'vue';
import type PrivilegesService from 'src/app/service/privileges.service';
import template from './sw-permissions-additional.html.twig';
import './sw-permissions-additional.scss';

type Mapping = ReturnType<PrivilegesService['getPrivilegesMappings']>[number];

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['privileges'],

    props: {
        role: {
            type: Object as PropType<{ privileges: string[] } | null>,
            required: false,
            default: null,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        privilegeIds: {
            type: Array as PropType<string[] | null>,
            default: null,
        },
        relatedPages: {
            type: Object as PropType<Record<string, string[]>>,
            default: () => ({}),
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return { term: '', expandedScopes: [] as string[] };
    },

    watch: {
        privilegeIds() {
            this.term = '';
            this.expandedScopes = [];
        },
        role: {
            handler() {
                this.reconcileSelection();
            },
            immediate: true,
        },
    },

    computed: {
        additionalPermissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return this.filterPermissions(
                privileges.filter((privilege) => privilege.category === 'additional_permissions' && privilege.key !== 'app'),
            );
        },

        appPermissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            const apps = privileges.filter(
                (privilege) => privilege.category === 'additional_permissions' && privilege.key === 'app',
            );
            if (!apps.some((entry) => Object.keys(entry.roles).some((name) => name !== 'all'))) return [];
            return this.filterPermissions(apps);
        },

        selectedPrivileges() {
            return this.role?.privileges ?? [];
        },

        /**
         * Identifiers required by another selection. Additional permissions may depend on each other or on
         * a privilege of the permissions grid — both grids write into the same `role.privileges` array, so
         * the dependency can be satisfied from here as well.
         */
        usedDependencies() {
            const dependencies = new Set<string>();

            this.selectedPrivileges.forEach((privilegeKey) => {
                this.dependenciesOf(privilegeKey).forEach((dependency) => {
                    dependencies.add(dependency);
                });
            });

            return [...dependencies];
        },
    },

    methods: {
        toggleScope(identifier: string) {
            this.expandedScopes = this.expandedScopes.includes(identifier)
                ? this.expandedScopes.filter((id) => id !== identifier)
                : [...this.expandedScopes, identifier];
        },
        filterPermissions(entries: Mapping[]) {
            const term = this.term.trim().toLocaleLowerCase();
            return entries
                .map((entry) => {
                    const groupMatches = this.$t(`sw-privileges.additional_permissions.${entry.key}.label`)
                        .toLocaleLowerCase()
                        .includes(term);
                    const roles = Object.fromEntries(
                        Object.entries(entry.roles).filter(([name]) => {
                            const identifier = `${entry.key}.${name}`;
                            return (
                                (this.privilegeIds === null || this.privilegeIds.includes(identifier)) &&
                                (!term || groupMatches || this.privilegeLabel(identifier).toLocaleLowerCase().includes(term))
                            );
                        }),
                    );
                    return { ...entry, roles };
                })
                .filter((entry) => Object.keys(entry.roles).length > 0);
        },
        privilegeLabel(identifier: string) {
            const [key, role] = identifier.split('.');
            if (key === 'app' && role !== 'all') return role;
            const entry = this.privileges.getPrivilegesMappings().find((mapping) => mapping.key === key);
            return entry?.category === 'additional_permissions'
                ? this.$t(`sw-privileges.additional_permissions.${key}.${role}`)
                : `${this.$t(`sw-privileges.permissions.${key}.label`)} · ${this.$t(`sw-privileges.roles.${role}`)}`;
        },

        dependenciesOf(identifier: string) {
            return this.privileges.getPrivilegeRole(identifier)?.dependencies ?? [];
        },

        isRequiredBySelection(identifier: string) {
            return this.usedDependencies.includes(identifier);
        },

        isPrivilegeSelected(privilegeKey: string) {
            if (!this.role?.privileges) {
                return false;
            }

            return this.role.privileges.includes(privilegeKey);
        },

        isPrivilegeLocked(privilegeKey: string) {
            return this.isPrivilegeSelected(privilegeKey) && this.isRequiredBySelection(privilegeKey);
        },

        lockedPrivilegeTooltip(privilegeKey: string) {
            const dependants = this.selectedPrivileges
                .filter((selected) => this.dependenciesOf(selected).includes(privilegeKey))
                .map((selected) => this.privilegeLabel(selected));

            return this.$t('sw-permissions.roles.grid.disabledCheckboxMessage', {
                roles: [...new Set(dependants)].join(', '),
            });
        },

        /**
         * Aligns a stored selection with the declared dependencies once the role is loaded, so that the
         * switches show what saving would store anyway (`getPrivilegesForAdminPrivilegeKeys()` expands
         * the same list). Idempotent — the permissions grid runs the same reconciliation.
         */
        reconcileSelection() {
            if (!this.role?.privileges) {
                return;
            }

            [...this.role.privileges].forEach((privilegeKey) => {
                this.addPrivilegeWithDependencies(privilegeKey);
            });
        },

        onSelectPrivilege(privilegeKey: string, isSelected: boolean) {
            if (this.disabled || !this.role) return;
            if (isSelected) {
                this.addPrivilegeWithDependencies(privilegeKey);
                return;
            }

            if (this.isRequiredBySelection(privilegeKey)) {
                return;
            }

            this.role.privileges = this.role.privileges.filter((p) => p !== privilegeKey);
        },

        addPrivilegeWithDependencies(privilegeKey: string, visited = new Set<string>()) {
            if (!this.role || visited.has(privilegeKey)) return;
            visited.add(privilegeKey);
            if (!this.role.privileges.includes(privilegeKey)) this.role.privileges.push(privilegeKey);
            this.dependenciesOf(privilegeKey).forEach((dependency) => {
                this.addPrivilegeWithDependencies(dependency, visited);
            });
        },

        changeAllAppPermissionsForKey(permissionKey: string, isSelected: boolean) {
            if (this.disabled || !this.role) return;
            if (isSelected) {
                this.addPrivilegeWithDependencies(permissionKey);
                return;
            }
            if (this.isRequiredBySelection(permissionKey)) return;
            this.role.privileges = this.role.privileges.filter((key) => key !== permissionKey);
            this.privileges
                .getPrivilegesMappings()
                .filter((entry) => entry.category === 'additional_permissions' && entry.key === 'app')
                .forEach((permission) => {
                    Object.keys(permission.roles)
                        .filter((role) => role !== 'all')
                        .forEach((role) => {
                            const identifier = `app.${role}`;
                            if (this.role && !this.isRequiredBySelection(identifier)) {
                                this.role.privileges = this.role.privileges.filter((key) => key !== identifier);
                            }
                        });
                });
        },
    },
});
