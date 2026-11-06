/**
 * @sw-package fundamentals@framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-system-user-list.html.twig';
import './sw-system-user-list.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

type FilterDefinition = {
    property: string;
    label: string;
    placeholder?: string;
};

type User = EntitySchema.Entity<'user'>;

/**
 * The filters the listing sends to the DAL, the type is not exported by the criteria module.
 */
type Filter = CriteriaType['filters'][number];

type FilterFactory = {
    create: (entityName: string, filters: Record<string, FilterDefinition>) => FilterDefinition[];
};

type FilterService = {
    mergeWithStoredFilters: (storeKey: string, criteria: CriteriaType) => Promise<CriteriaType>;
};

/**
 * The filter factory is injected but not part of the typed service container yet.
 */
function filterFactoryOf(vm: unknown): FilterFactory {
    return (vm as { filterFactory?: FilterFactory }).filterFactory as FilterFactory;
}

type DeleteContext = Parameters<RepositoryType<'user'>['delete']>[1];

/**
 * The administration user list. The layout follows the shape of every other list of the
 * administration: a search bar, a full page entity listing and a sidebar filter panel.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- the page is extended by plugins through the component factory
export default defineComponent({
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'ssoSettingsService',
        'filterFactory',
    ],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data(): {
        users: EntityCollection<'user'> | null;
        roles: EntityCollection<'acl_role'> | null;
        isLoading: boolean;
        isSso: boolean;
        itemToDelete: User | null;
        isConfirmDeleteModalOpen: boolean;
        isConfirmingPasswordModalOpen: boolean;
        showInvitationModal: boolean;
        filterCriteria: Filter[];
        defaultFilters: string[];
        storeKey: string;
        activeFilterNumber: number;
        sortBy: string;
    } {
        return {
            users: null,
            roles: null,
            isLoading: false,
            isSso: false,
            itemToDelete: null,
            isConfirmDeleteModalOpen: false,
            isConfirmingPasswordModalOpen: false,
            showInvitationModal: false,
            filterCriteria: [],
            defaultFilters: ['role-filter', 'status-filter'],
            storeKey: 'grid.filter.system-user',
            activeFilterNumber: 0,
            sortBy: 'username',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        userRepository(): RepositoryType<'user'> {
            return this.repositoryFactory.create('user');
        },

        roleRepository(): RepositoryType<'acl_role'> {
            return this.repositoryFactory.create('acl_role');
        },

        currentUser(): User | null {
            return Shopwell.Store.get('session').currentUser as User | null;
        },

        userDetailRoute(): string {
            return this.isSso ? 'sw.system.user.sso.detail' : 'sw.system.user.detail';
        },

        userDetailRouterLink(): string {
            return this.userDetailRoute;
        },

        defaultCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            const term = this.term ?? '';

            if (this.isValidTerm(term)) {
                criteria.setTerm(term);
            }

            this.filterCriteria.forEach((filter) => {
                criteria.addFilter(filter);
            });

            if (this.sortBy) {
                const sortDirection = this.sortDirection === 'DESC' ? 'DESC' : 'ASC';

                criteria.addSorting(Criteria.sort(this.sortBy, sortDirection));
            }

            criteria.addAssociation('aclRoles');
            criteria.addAssociation('avatarMedia');

            return criteria;
        },

        listFilterOptions(): Record<string, FilterDefinition> {
            return {
                'role-filter': {
                    property: 'aclRoles',
                    label: this.$t('sw-system.users.filter.labelRole'),
                    placeholder: this.$t('sw-system.users.filter.labelRole'),
                },
                'status-filter': {
                    property: 'active',
                    label: this.$t('sw-system.users.filter.labelStatus'),
                },
            };
        },

        listFilters(): FilterDefinition[] {
            const factory = filterFactoryOf(this);

            return factory.create('user', this.listFilterOptions);
        },

        hasActiveSearchOrFilter(): boolean {
            return this.activeFilterNumber > 0 || Boolean(this.isValidTerm(this.term ?? ''));
        },

        userColumns(): Array<Record<string, unknown>> {
            const columns = [
                {
                    property: 'aclRoles',
                    sortable: false,
                    label: this.$t('sw-system.users.user-grid.labelRoles'),
                    allowResize: true,
                },
                {
                    property: 'email',
                    label: this.$t('sw-system.users.user-grid.labelEmail'),
                    allowResize: true,
                },
                {
                    property: 'active',
                    label: this.$t('sw-system.users.user-grid.status'),
                    allowResize: true,
                },
                {
                    property: 'createdAt',
                    label: this.$t('sw-system.users.user-grid.labelCreatedAt'),
                    allowResize: true,
                },
            ];

            if (this.isSso) {
                return [
                    {
                        ...columns[0],
                        primary: true,
                    },
                    ...columns.slice(1),
                ];
            }

            return [
                {
                    property: 'username',
                    label: this.$t('sw-system.users.user-grid.labelUser'),
                    primary: true,
                    allowResize: true,
                },
                ...columns,
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            void this.ssoSettingsService.isSso().then((response: { isSso: boolean }) => {
                this.isSso = response.isSso;
            });

            this.loadRoles();
        },

        loadRoles(): void {
            const criteria = new Criteria(1, 500);
            criteria.addFilter(Criteria.equals('app.id', null));
            criteria.addFilter(Criteria.equals('deletedAt', null));
            criteria.addSorting(Criteria.sort('name', 'ASC'));

            void this.roleRepository.search(criteria).then((roles) => {
                this.roles = roles;
            });
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            const filterService = Shopwell.Service('filterService') as FilterService;
            const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.defaultCriteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.userRepository
                .search(criteria)
                .then((users) => {
                    this.total = users.total ?? 0;
                    this.users = users;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onDelete(user: User): void {
            this.itemToDelete = user;
            this.isConfirmDeleteModalOpen = true;
        },

        onCloseDeleteModal(): void {
            this.isConfirmDeleteModalOpen = false;
            this.itemToDelete = null;
        },

        onConfirmDelete(user: User): void {
            if (user.id === this.currentUser?.id) {
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

        deleteUser(context: DeleteContext): void {
            const user = this.itemToDelete as User;
            const username = user.name;

            this.isConfirmingPasswordModalOpen = false;

            this.userRepository
                .delete(user.id, context)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$t('global.default.success'),
                        message: this.$t(
                            'sw-system.users.user-grid.notification.deleteSuccess.message',
                            { name: username },
                            0,
                        ),
                    });
                    this.getList();
                })
                .catch(() => {
                    this.createNotificationError({
                        title: this.$t('global.default.error'),
                        message: this.$t(
                            'sw-system.users.user-grid.notification.deleteError.message',
                            { name: username },
                            0,
                        ),
                    });
                });

            this.itemToDelete = null;
        },

        onToggleActive(user: User): void {
            if (user.id === this.currentUser?.id) {
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
                            ? this.$t(
                                  'sw-system.users.user-grid.notification.enableSuccess.message',
                                  { name: user.username },
                                  0,
                              )
                            : this.$t(
                                  'sw-system.users.user-grid.notification.disableSuccess.message',
                                  { name: user.username },
                                  0,
                              ),
                    });
                    this.getList();
                })
                .catch(() => {
                    user.active = !nextActive;
                    this.createNotificationError({
                        title: this.$t('global.default.error'),
                        message: this.$t(
                            'sw-system.users.user-grid.notification.toggleActiveError.message',
                            { name: user.username },
                            0,
                        ),
                    });
                });
        },

        onUserInvited(): void {
            this.getList();
            this.closeInvitationModal();
        },

        openInvitationModal(): void {
            this.showInvitationModal = true;
        },

        closeInvitationModal(): void {
            this.showInvitationModal = false;
        },

        invitationFailed(): void {
            this.createNotificationError({
                title: this.$t('global.default.error'),
                message: this.$t('sw-system.sso.error.cannotInviteUser'),
            });
        },

        onCloseConfirmPasswordModal(): void {
            this.isConfirmingPasswordModalOpen = false;
        },
    },
});
