/**
 * @sw-package fundamentals@framework
 */
import template from './sw-permissions-role-list.html.twig';
import './sw-permissions-role-list.scss';

const { Data, Mixin } = Shopwell;
const { Criteria } = Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'ssoSettingsService',
    ],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data() {
        return {
            roles: [],
            isLoading: false,
            itemToDelete: null,
            affectedUserCount: 0,
            isConfirmDeleteModalOpen: false,
            isConfirmingPasswordModalOpen: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        roleRepository() {
            return this.repositoryFactory.create('acl_role');
        },

        userRepository() {
            return this.repositoryFactory.create('user');
        },

        rolesColumns() {
            return [
                {
                    property: 'name',
                    label: this.$t('sw-permissions.roles.role-grid.labelName'),
                },
                {
                    property: 'description',
                    label: this.$t('sw-permissions.roles.role-grid.labelDescription'),
                },
                {
                    property: 'privileges',
                    sortable: false,
                    label: this.$t('sw-permissions.roles.role-grid.labelPrivileges'),
                },
            ];
        },

        roleCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            // roles created by apps are managed by the app and must not be edited here
            criteria.addFilter(Criteria.equals('app.id', null));
            criteria.addFilter(Criteria.equals('deletedAt', null));

            if (this.term) {
                criteria.setTerm(this.term);
            }

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection || 'ASC'));
            }

            return criteria;
        },

        emptyStateTitle() {
            if (this.term) {
                return this.$t('sw-permissions.roles.role-grid.noResultTitle');
            }

            return this.$t('sw-permissions.roles.role-grid.messageEmptyTitle');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            return this.roleRepository
                .search(this.roleCriteria)
                .then((roles) => {
                    this.total = roles.total;
                    this.roles = roles;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onSearch(searchTerm) {
            this.term = searchTerm;
            this.page = 1;

            this.getList();
        },

        getItemToDelete(item) {
            if (!this.itemToDelete) {
                return false;
            }

            return this.itemToDelete.id === item.id;
        },

        onDelete(role) {
            this.itemToDelete = role;
            this.affectedUserCount = 0;
            this.isConfirmDeleteModalOpen = true;

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('aclRoles.id', role.id));

            this.userRepository.searchIds(criteria).then((result) => {
                this.affectedUserCount = result.total;
            });
        },

        onCloseDeleteModal() {
            this.isConfirmDeleteModalOpen = false;
            this.itemToDelete = null;
        },

        onConfirmDelete() {
            this.isLoading = true;
            this.isConfirmDeleteModalOpen = false;

            this.ssoSettingsService.isSso().then((response) => {
                this.isLoading = false;

                if (response.isSso) {
                    this.deleteRole({ ...Shopwell.Context.api });

                    return;
                }

                this.isConfirmingPasswordModalOpen = true;
            });
        },

        deleteRole(context) {
            this.isConfirmingPasswordModalOpen = false;
            const role = this.itemToDelete;
            this.itemToDelete = null;
            this.isLoading = true;

            this.roleRepository
                .delete(role.id, context)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t(
                            'sw-permissions.roles.role-grid.notification.deleteSuccess.message',
                            { name: role.name },
                            0,
                        ),
                    });

                    this.getList();
                })
                .catch(() => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$t(
                            'sw-permissions.roles.role-grid.notification.deleteError.message',
                            { name: role.name },
                            0,
                        ),
                    });
                });
        },

        onCreateRole() {
            this.$router.push({ name: 'sw.permissions.role.detail' });
        },

        onDuplicateRole(role) {
            this.$router.push({
                name: 'sw.permissions.role.detail',
                query: { copyFrom: role.id },
            });
        },

        onCloseConfirmPasswordModal() {
            this.isConfirmingPasswordModalOpen = false;
        },
    },
};
