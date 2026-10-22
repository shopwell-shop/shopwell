/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-dictionary-list.html.twig';
import './sw-settings-dictionary-list.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

type DictionaryTypeEntity = EntitySchema.Entity<'dictionary_type'>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'dictionaryService'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('listing'), Mixin.getByName('placeholder')],

    data(): {
        entityName: string;
        dictionaryTypes: EntityCollection<'dictionary_type'> | null;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        isLoading: boolean;
        showCreateModal: boolean;
        showDeleteModal: string | null;
        newType: DictionaryTypeEntity | null;
    } {
        return {
            entityName: 'dictionary_type',
            dictionaryTypes: null,
            sortBy: 'technicalName',
            sortDirection: 'ASC',
            isLoading: false,
            showCreateModal: false,
            showDeleteModal: null,
            newType: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        dictionaryTypeRepository(): RepositoryType<'dictionary_type'> {
            return this.repositoryFactory.create('dictionary_type');
        },

        dictionaryTypeColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'technicalName',
                    dataIndex: 'technicalName',
                    label: 'sw-settings-dictionary.typeList.columnTechnicalName',
                    routerLink: 'sw.settings.dictionary.detail',
                    primary: true,
                },
                {
                    property: 'label',
                    dataIndex: 'label',
                    label: 'sw-settings-dictionary.typeList.columnLabel',
                },
                {
                    property: 'entries',
                    label: 'sw-settings-dictionary.typeList.columnEntries',
                    align: 'right',
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

            const criteria = this.getCriteria();

            try {
                const result = await this.dictionaryTypeRepository.search(criteria);

                this.dictionaryTypes = result;
                this.total = result.total ?? 0;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.typeList.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        getCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            criteria.addAssociation('entries');

            return criteria;
        },

        getEntryCount(item: DictionaryTypeEntity): number {
            return (item.entries as unknown[] | undefined)?.length ?? 0;
        },

        onAddType(): void {
            this.newType = this.dictionaryTypeRepository.create();
            this.showCreateModal = true;
        },

        onCloseCreateModal(): void {
            this.showCreateModal = false;
            this.newType = null;
        },

        async onCreateSave(): Promise<void> {
            if (!this.newType) {
                return;
            }

            if (!this.newType.technicalName || this.newType.technicalName.trim() === '') {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.typeList.messageTechnicalNameRequired'),
                });

                return;
            }

            this.newType.technicalName = this.newType.technicalName?.trim() ?? '';

            try {
                await this.dictionaryTypeRepository.save(this.newType);

                this.dictionaryService.invalidateCaches();
                this.onCloseCreateModal();
                this.createdComponent();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.typeList.messageSaveError'),
                });
            }
        },

        onChangeLanguage(): void {
            void this.createdComponent();
        },

        onInlineEditSave(promise: Promise<void>, item: DictionaryTypeEntity): Promise<void> {
            return promise
                .then(() => {
                    this.dictionaryService.invalidateCaches();
                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-dictionary.typeList.messageSaveSuccess', { name: item.technicalName }, 0),
                    });
                })
                .catch(() => {
                    this.createdComponent();
                    this.createNotificationError({
                        message: this.$t('sw-settings-dictionary.typeList.messageSaveError'),
                    });
                });
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
                await this.dictionaryTypeRepository.delete(id as EntitySchema.DictionaryTypeId);
                this.dictionaryService.invalidateCaches();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.typeList.messageDeleteError'),
                });
            } finally {
                this.createdComponent();
            }
        },
    },
});
