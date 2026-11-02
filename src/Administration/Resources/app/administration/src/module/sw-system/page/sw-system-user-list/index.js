/**
 * @sw-package fundamentals@framework
 */
import template from './sw-system-user-list.html.twig';
import './sw-system-user-list.scss';

const { Data, Mixin } = Shopwell;
const { Criteria } = Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'userService',
        'repositoryFactory',
        'acl',
        'ssoSettingsService',
    ],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data() {
        return {
            users: [],
            roles: [],
            isLoading: false,
            isSso: false,
            itemToDelete: null,
            isConfirmDeleteModalOpen: false,
            isConfirmingPasswordModalOpen: false,
            showInvitationModal: false,
            roleFilter: '',
            statusFilter: '',
            sortBy: 'username',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        userRepository() {
            return this.repositoryFactory.create('user');
        },

        roleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        currentUser() {
            return Shopwell.Store.get('session').currentUser;
        },

        userDetailRouterLink() {
            return this.isSso ? 'sw.system.user.sso.detail' : 'sw.system.user.detail';
        },

        userCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            if (this.roleFilter) {
                criteria.addFilter(Criteria.equals('aclRoles.id', this.roleFilter));
            }

            if (this.statusFilter !== '') {
                criteria.addFilter(Criteria.equals('active', this.statusFilter === 'active'));
            }

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection || 'ASC'));
            }

            criteria.addAssociation('aclRoles');
            criteria.addAssociation('avatarMedia');

            return criteria;
        },

        roleFilterOptions() {
            return [
                { value: '', label: this.$t('sw-system.users.filter.allRoles') },
                ...this.roles.map((role) => ({ value: role.id, label: role.name })),
            ];
        },

        statusFilterOptions() {
            return [
                { value: '', label: this.$t('sw-system.users.filter.allStatus') },
                { value: 'active', label: this.$t('sw-system.users.filter.statusActive') },
                { value: 'inactive', label: this.$t('sw-system.users.filter.statusInactive') },
            ];
        },

        userColumns() {
            const columns = [
                {
                    property: 'aclRoles',
                    sortable: false,
                    label: this.$t('sw-system.users.user-grid.labelRoles'),
                },
                {
                    property: 'email',
                    label: this.$t('sw-system.users.user-grid.labelEmail'),
                },
                {
                    property: 'active',
                    label: this.$t('sw-system.users.user-grid.status'),
                },
            ];

            if (this.isSso) {
                return columns;
            }

            return [
                {
                    property: 'username',
                    label: this.$t('sw-system.users.user-grid.labelUser'),
                },
                ...columns,
            ];
        },

        hasUsers() {
            return this.users.length > 0;
        },

        emptyStateTitle() {
            if (this.term || this.roleFilter || this.statusFilter !== '') {
                return this.$t('sw-system.users.emptyState.noResultTitle');
            }

            return this.$t('sw-system.users.emptyState.noUserTitle');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.ssoSettingsService.isSso().then((response) => {
                this.isSso = response.isSso;
            });

            this.loadRoles();
            this.getList();
        },

        loadRoles() {
            const criteria = new Criteria(1, 500);
            criteria.addFilter(Criteria.equals('app.id', null));
            criteria.addFilter(Criteria.equals('deletedAt', null));
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            this.roleRepository.search(criteria).then((roles) => {
                this.roles = roles;
            });
        },

        getList() {
            this.isLoading = true;

            return this.userRepository
                .search(this.userCriteria)
                .then((users) => {
                    this.total = users.total;
                    this.users = users;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSearch(value) {
            this.term = value;
            this.page = 1;

            this.getList();
        },

        onFilterChange() {
            this.page = 1;

            this.getList();
        },

        onRoleFilterChange(value) {
            this.roleFilter = value ?? '';
            this.onFilterChange();
        },

        onStatusFilterChange(value) {
            this.statusFilter = value ?? '';
            this.onFilterChange();
        },

        onRefresh() {
            this.loadRoles();
            this.getList();
        },

        getItemToDelete(item) {
            if (!this.itemToDelete) {
                return false;
            }

            return this.itemToDelete.id === item.id;
        },

        onDelete(user) {
            this.itemToDelete = user;
            this.isConfirmDeleteModalOpen = true;
        },

        onCloseDeleteModal() {
            this.isConfirmDeleteModalOpen = false;
            this.itemToDelete = null;
        },

        onConfirmDelete(user) {
            if (user.id === this.currentUser.id) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-system.users.user-grid.notification.deleteUserLoggedInError.message'),
                });

                this.onCloseDeleteModal();

                return;
            }

            this.isConfirmDeleteModalOpen = false;

            if (this.isSso) {
                this.deleteUser({ ...Shopwell.Context.api });

                return;
            }

            this.isConfirmingPasswordModalOpen = true;
        },

        deleteUser(context) {
            const user = this.itemToDelete;
            const username = user.name;

            this.isConfirmingPasswordModalOpen = false;

            this.userRepository
                .delete(user.id, context)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$t('global.default.success'),
                        message: this.$t('sw-system.users.user-grid.notification.deleteSuccess.message', { name: username }, 0),
                    });
                    this.getList();
                })
                .catch(() => {
                    this.createNotificationError({
                        title: this.$t('global.default.error'),
                        message: this.$t('sw-system.users.user-grid.notification.deleteError.message', { name: username }, 0),
                    });
                });

            this.itemToDelete = null;
        },

        onToggleActive(user) {
            if (user.id === this.currentUser.id) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-system.users.user-grid.notification.toggleActiveSelfError.message'),
                });

                return;
            }

            const nextActive = !user.active;
            user.active = nextActive;

            this.userRepository
                .save(user)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$t('global.default.success'),
                        message: nextActive
                            ? this.$t('sw-system.users.user-grid.notification.enableSuccess.message', { name: user.username }, 0)
                            : this.$t('sw-system.users.user-grid.notification.disableSuccess.message', { name: user.username }, 0),
                    });
                    this.getList();
                })
                .catch(() => {
                    user.active = !nextActive;
                    this.createNotificationError({
                        title: this.$t('global.default.error'),
                        message: this.$t('sw-system.users.user-grid.notification.toggleActiveError.message', { name: user.username }, 0),
                    });
                });
        },

        onUserInvited() {
            this.getList();
            this.closeInvitationModal();
        },

        openInvitationModal() {
            this.showInvitationModal = true;
        },

        closeInvitationModal() {
            this.showInvitationModal = false;
        },

        invitationFailed() {
            this.createNotificationError({
                title: this.$t('global.default.error'),
                message: this.$t('sw-system.sso.error.cannotInviteUser'),
            });
        },

        onCloseConfirmPasswordModal() {
            this.isConfirmingPasswordModalOpen = false;
        },
    },
};
