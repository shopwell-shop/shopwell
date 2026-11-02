/**
 * @sw-package fundamentals@framework
 */
import template from './sw-system-additional-permissions.html.twig';
import './sw-system-additional-permissions.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['privileges'],

    props: {
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
        additionalPermissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return privileges.filter(
                (privilege) => privilege.category === 'additional_permissions' && privilege.key !== 'app',
            );
        },

        appPermissions() {
            const privileges = this.privileges.getPrivilegesMappings();

            return privileges.filter(
                (privilege) => privilege.category === 'additional_permissions' && privilege.key === 'app',
            );
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
            const dependencies = new Set();

            this.selectedPrivileges.forEach((privilegeKey) => {
                this.dependenciesOf(privilegeKey).forEach((dependency) => {
                    dependencies.add(dependency);
                });
            });

            return [...dependencies];
        },
    },

    methods: {
        dependenciesOf(identifier) {
            return this.privileges.getPrivilegeRole(identifier)?.dependencies ?? [];
        },

        isRequiredBySelection(identifier) {
            return this.usedDependencies.includes(identifier);
        },

        isPrivilegeSelected(privilegeKey) {
            if (!this.role.privileges) {
                return false;
            }

            return this.role.privileges.includes(privilegeKey);
        },

        isPrivilegeLocked(privilegeKey) {
            return this.isPrivilegeSelected(privilegeKey) && this.isRequiredBySelection(privilegeKey);
        },

        lockedPrivilegeTooltip(privilegeKey) {
            const dependants = this.selectedPrivileges
                .filter((selected) => this.dependenciesOf(selected).includes(privilegeKey))
                .map((selected) => this.$t(`sw-privileges.roles.${selected.split('.')[1]}`));

            return this.$t('sw-system.roles.grid.disabledCheckboxMessage', {
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

        onSelectPrivilege(privilegeKey, isSelected) {
            if (isSelected) {
                this.addPrivilegeWithDependencies(privilegeKey);
                return;
            }

            if (this.isRequiredBySelection(privilegeKey)) {
                return;
            }

            this.role.privileges = this.role.privileges.filter((p) => p !== privilegeKey);
        },

        addPrivilegeWithDependencies(privilegeKey) {
            if (this.role.privileges.includes(privilegeKey)) {
                return;
            }

            this.role.privileges.push(privilegeKey);

            this.dependenciesOf(privilegeKey).forEach((dependency) => {
                this.addPrivilegeWithDependencies(dependency);
            });
        },

        changeAllAppPermissionsForKey(permissionKey, isSelected) {
            this.appPermissions.forEach((permission) => {
                Object.keys(permission.roles).forEach((role) => {
                    const identifier = `app.${role}`;

                    if (isSelected) {
                        if (this.role.privileges.includes(identifier)) {
                            return;
                        }

                        this.role.privileges.push(identifier);
                    } else {
                        this.role.privileges = this.role.privileges.filter((p) => p !== identifier);
                    }
                });
            });
        },
    },
};
