/**
 * @sw-package framework
 */
import useNotification from 'src/app/composables/use-notification';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-organization-list.html.twig';
import './sw-settings-organization-list.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

type OrganizationEntity = EntitySchema.Entity<'organization'>;

/** @private */
export default Shopwell.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('placeholder')],

    data(): {
        entityName: string;
        organizations: EntityCollection<'organization'> | null;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        isLoading: boolean;
        showDeleteModal: string | null;
        savingIds: string[];
        isDeleting: boolean;
    } {
        return {
            entityName: 'organization',
            organizations: null,
            sortBy: 'name',
            sortDirection: 'ASC',
            isLoading: false,
            showDeleteModal: null,
            savingIds: [],
            isDeleting: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        organizationRepository(): RepositoryType<'organization'> {
            return this.repositoryFactory.create('organization');
        },

        organizationToDelete(): OrganizationEntity | null {
            return this.organizations?.find((organization) => organization.id === this.showDeleteModal) ?? null;
        },

        organizationColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: 'sw-settings-organization.list.columnName',
                    routerLink: 'sw.settings.organization.detail',
                    primary: true,
                    inlineEdit: 'string',
                },
                {
                    property: 'units',
                    label: 'sw-settings-organization.list.columnUnits',
                    sortable: false,
                    align: 'right',
                },
                {
                    property: 'active',
                    label: 'sw-settings-organization.list.columnActive',
                    align: 'center',
                },
            ];
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            void this.getList();
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            const criteria: CriteriaType = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAggregation(
                Criteria.terms('unitCounts', 'id', null, null, Criteria.count('unitCount', 'organization.units.id')),
            );

            try {
                const result = await this.organizationRepository.search(criteria);

                this.organizations = result;
                this.total = result.total ?? 0;
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('sw-settings-organization.list.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        getUnitCount(item: OrganizationEntity): number {
            const aggregation = this.organizations?.aggregations?.unitCounts as
                | { buckets: Array<{ key: string; unitCount: { count: number } }> }
                | undefined;
            return aggregation?.buckets.find((bucket) => bucket.key === item.id)?.unitCount.count ?? 0;
        },

        onChangeLanguage(): void {
            void this.createdComponent();
        },

        onAddOrganization(): void {
            if (this.acl.can('organization.creator')) {
                void this.$router.push({ name: 'sw.settings.organization.create' });
            }
        },

        onInlineEditSave(promise: Promise<void>, item: OrganizationEntity): Promise<void> {
            return promise
                .then(() => {
                    useNotification().createNotificationSuccess({
                        message: this.$t('sw-settings-organization.list.messageSaveSuccess', { name: item.name }, 0),
                    });
                })
                .catch(() => {
                    void this.createdComponent();
                    useNotification().createNotificationError({
                        message: this.$t('sw-settings-organization.list.messageSaveError'),
                    });
                });
        },

        async onActiveChange(item: OrganizationEntity, value: boolean): Promise<void> {
            if (!this.acl.can('organization.editor') || this.savingIds.includes(item.id)) {
                return;
            }
            this.savingIds.push(item.id);
            const previous = item.active;

            item.active = value;

            try {
                await this.organizationRepository.save(item);
                void this.getList();
            } catch {
                item.active = previous;
                useNotification().createNotificationError({
                    message: this.$t('sw-settings-organization.list.messageSaveError'),
                });
            } finally {
                this.savingIds = this.savingIds.filter((id) => id !== item.id);
            }
        },

        onDelete(id: string): void {
            if (this.acl.can('organization.deleter') && !this.isDeleting) {
                this.showDeleteModal = id;
            }
        },

        onCloseDeleteModal(): void {
            if (!this.isDeleting) {
                this.showDeleteModal = null;
            }
        },

        async onConfirmDelete(id: string): Promise<void> {
            if (!this.acl.can('organization.deleter') || this.isDeleting) {
                return;
            }
            this.isDeleting = true;

            try {
                await this.organizationRepository.delete(id as EntitySchema.OrganizationId);
                this.showDeleteModal = null;
                void this.getList();
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('sw-settings-organization.list.messageDeleteError'),
                });
            } finally {
                this.isDeleting = false;
            }
        },
    },
});
