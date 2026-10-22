/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-organization-list.html.twig';
import './sw-settings-organization-list.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

type OrganizationEntity = EntitySchema.Entity<'organization'>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('listing'), Mixin.getByName('placeholder')],

    data(): {
        entityName: string;
        organizations: EntityCollection<'organization'> | null;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        isLoading: boolean;
        showCreateModal: boolean;
        showDeleteModal: string | null;
        newOrganization: OrganizationEntity | null;
    } {
        return {
            entityName: 'organization',
            organizations: null,
            sortBy: 'name',
            sortDirection: 'ASC',
            isLoading: false,
            showCreateModal: false,
            showDeleteModal: null,
            newOrganization: null,
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

        organizationColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: 'sw-settings-organization.list.columnName',
                    routerLink: 'sw.settings.organization.detail',
                    primary: true,
                },
                {
                    property: 'units',
                    label: 'sw-settings-organization.list.columnUnits',
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
            criteria.addAssociation('units');

            try {
                const result = await this.organizationRepository.search(criteria);

                this.organizations = result;
                this.total = result.total ?? 0;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.list.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        getUnitCount(item: OrganizationEntity): number {
            return (item.units as unknown[] | undefined)?.length ?? 0;
        },

        onChangeLanguage(): void {
            void this.createdComponent();
        },

        onAddOrganization(): void {
            this.newOrganization = this.organizationRepository.create();
            this.newOrganization.active = true;
            this.showCreateModal = true;
        },

        onCloseCreateModal(): void {
            this.showCreateModal = false;
            this.newOrganization = null;
        },

        async onCreateSave(): Promise<void> {
            if (!this.newOrganization) {
                return;
            }

            if (!this.newOrganization.name || this.newOrganization.name.trim() === '') {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.list.messageNameRequired'),
                });

                return;
            }

            try {
                await this.organizationRepository.save(this.newOrganization);

                const newOrganizationId = this.newOrganization.id;

                this.onCloseCreateModal();

                await this.$router.push({
                    name: 'sw.settings.organization.detail',
                    params: { id: newOrganizationId },
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.list.messageSaveError'),
                });
            }
        },

        onInlineEditSave(promise: Promise<void>, item: OrganizationEntity): Promise<void> {
            return promise
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-organization.list.messageSaveSuccess', { name: item.name }, 0),
                    });
                })
                .catch(() => {
                    void this.createdComponent();
                    this.createNotificationError({
                        message: this.$t('sw-settings-organization.list.messageSaveError'),
                    });
                });
        },

        async onActiveChange(item: OrganizationEntity, value: boolean): Promise<void> {
            const previous = item.active;

            item.active = value;

            try {
                await this.organizationRepository.save(item);
            } catch {
                item.active = previous;
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.list.messageSaveError'),
                });
            }
        },

        onDelete(id: string): void {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal(): void {
            this.showDeleteModal = null;
        },

        async onConfirmDelete(id: string): Promise<void> {
            this.showDeleteModal = null;

            try {
                await this.organizationRepository.delete(id as EntitySchema.OrganizationId);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-organization.list.messageDeleteError'),
                });
            } finally {
                this.createdComponent();
            }
        },
    },
});
