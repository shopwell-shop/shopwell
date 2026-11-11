/**
 * @sw-package fundamentals@framework
 */
import template from './sw-permissions-role-detail.html.twig';
import './sw-permissions-role-detail.scss';

const { Mixin } = Shopwell;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'feature',
        'repositoryFactory',
        'privileges',
        'userService',
        'loginService',
        'acl',
        'appAclService',
        'ssoSettingsService',
    ],

    mixins: [Mixin.getByName('notification')],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            isLoading: true,
            isSaveSuccessful: false,
            role: null,
            confirmPasswordModal: false,
            detailedPrivileges: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        languageId() {
            return Shopwell.Store.get('session').languageId;
        },

        roleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        roleId() {
            return this.$route.params.id?.toLowerCase();
        },

        roleDetailTabs() {
            const createRouteTab = (label, routeName) => {
                const route = {
                    name: routeName,
                    params: { id: this.$route.params.id },
                };

                return {
                    label: this.$t(label),
                    name: route.name,
                    onClick: () => {
                        void this.$router.push(route);
                    },
                };
            };

            return [
                createRouteTab('sw-permissions.roles.tabs.menus', 'sw.permissions.role.detail.menus'),
                createRouteTab('sw-permissions.roles.tabs.general', 'sw.permissions.role.detail.general'),
                createRouteTab('sw-permissions.roles.tabs.detailed', 'sw.permissions.role.detail.detailed-privileges'),
            ];
        },

        /**
         * Selections whose dependencies are missing. While the user interacts with the grid this stays
         * empty, because every selection reconciles its dependencies. It can only become non-empty when a
         * dependency was registered after the role was loaded (e.g. by a plugin), so the save is blocked
         * instead of silently granting the missing privileges.
         */
        unresolvedDependencies() {
            if (!this.role) {
                return [];
            }

            const selected = new Set(this.role.privileges);

            return this.role.privileges.filter((identifier) => {
                return this.privileges.getPrivilegeRole(identifier)?.dependencies.some((dependency) => {
                    return !selected.has(dependency);
                });
            });
        },
    },

    watch: {
        languageId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopwell.ExtensionAPI.publishData({
                id: 'sw-permissions-role-detail__detailedPrivileges',
                path: 'detailedPrivileges',
                scope: this,
            });
            Shopwell.ExtensionAPI.publishData({
                id: 'sw-permissions-role-detail__role',
                path: 'role',
                scope: this,
            });
            if (!this.roleId) {
                this.createNewRole();
                return;
            }

            this.isLoading = true;
            this.getRole().finally(() => {
                this.isLoading = false;
            });
        },

        createNewRole() {
            this.isLoading = true;

            this.role = this.roleRepository.create();

            this.role.name = '';
            this.role.description = '';
            this.role.privileges = [];
            this.role.menuIds = [];
            this.detailedPrivileges = [...this.privileges.getDefaultUserPrivileges()];

            this.isLoading = false;
        },

        updateRole(patch) {
            if (!this.acl.can('permissions.editor') || !this.role) return;
            Object.assign(this.role, patch);
        },

        async getRole() {
            await this.appAclService.addAppPermissions();

            const criteria = new Shopwell.Data.Criteria();
            criteria.addAssociation('adminMenuItems');
            this.role = await this.roleRepository.get(this.roleId, Shopwell.Context.api, criteria);

            const filteredPrivileges = this.privileges.filterPrivilegesRoles(this.role.privileges);
            const allGeneralPrivileges = this.privileges.getPrivilegesForAdminPrivilegeKeys(filteredPrivileges);

            this.detailedPrivileges = this.role.privileges.filter((privilege) => {
                return !allGeneralPrivileges.includes(privilege);
            });
            this.role.privileges = filteredPrivileges;
        },

        async onSave() {
            if (!this.acl.can('permissions.editor') || this.isLoading) return;
            let isSso = false;

            try {
                this.isLoading = true;
                const response = await this.ssoSettingsService.isSso();

                isSso = response.isSso;
            } finally {
                this.isLoading = false;
            }

            if (isSso) {
                await this.saveRole({ ...Shopwell.Context.api });
                return;
            }

            this.confirmPasswordModal = true;
        },

        async saveRole(context) {
            if (this.unresolvedDependencies.length > 0) {
                this.isSaveSuccessful = false;
                this.isLoading = false;
                this.confirmPasswordModal = false;

                this.createNotificationError({
                    message: this.$t('sw-permissions.roles.detail.notification.dependencyOutOfSync.message'),
                });

                return;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;
            this.confirmPasswordModal = false;

            this.role.privileges = [
                ...this.privileges.getPrivilegesForAdminPrivilegeKeys(this.role.privileges),
                ...this.detailedPrivileges,
            ].sort();

            try {
                await this.roleRepository.save(this.role, context);
                await this.updateCurrentUser();

                if (this.role.isNew()) {
                    this.$router.push({
                        name: 'sw.permissions.role.detail',
                        params: { id: this.role.id },
                    });
                }

                await this.getRole();
                this.isSaveSuccessful = true;
            } catch {
                this.createNotificationError({
                    message: this.$t(
                        'global.notification.notificationSaveErrorMessage',
                        {
                            entityName: this.role.name,
                        },
                        0,
                    ),
                });

                this.role.privileges = this.privileges.filterPrivilegesRoles(this.role.privileges);
            } finally {
                this.isLoading = false;
            }
        },

        async updateCurrentUser() {
            const { data } = await this.userService.getUser();

            delete data.password;

            Shopwell.Store.get('session').setCurrentUser(data);
            await Shopwell.Store.get('adminMenuConfig').loadVisibleTree(true);
        },

        onCloseConfirmPasswordModal() {
            this.confirmPasswordModal = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onCancel() {
            this.$router.push({ name: 'sw.permissions.role' });
        },
    },
};
