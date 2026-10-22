/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-dictionary-detail.html.twig';
import './sw-settings-dictionary-detail.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

type DictionaryTypeEntity = EntitySchema.Entity<'dictionary_type'>;
type DictionaryEntryEntity = EntitySchema.Entity<'dictionary_entry'>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl', 'dictionaryService'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('placeholder')],

    props: {
        dictionaryTypeId: {
            type: String,
            required: true,
        },
    },

    data(): {
        dictionaryType: DictionaryTypeEntity | null;
        entries: EntityCollection<'dictionary_entry'> | null;
        isLoading: boolean;
        showCreateModal: boolean;
        showDeleteModal: string | null;
        newEntry: DictionaryEntryEntity | null;
    } {
        return {
            dictionaryType: null,
            entries: null,
            isLoading: false,
            showCreateModal: false,
            showDeleteModal: null,
            newEntry: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.dictionaryType?.translated?.label),
        };
    },

    computed: {
        dictionaryTypeRepository(): RepositoryType<'dictionary_type'> {
            return this.repositoryFactory.create('dictionary_type');
        },

        entryRepository(): RepositoryType<'dictionary_entry'> {
            return this.repositoryFactory.create('dictionary_entry');
        },

        entryColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'position',
                    dataIndex: 'position',
                    label: 'sw-settings-dictionary.entryList.columnPosition',
                    align: 'right',
                    inlineEdit: 'number',
                },
                {
                    property: 'label',
                    dataIndex: 'label',
                    label: 'sw-settings-dictionary.entryList.columnLabel',
                    primary: true,
                    inlineEdit: 'string',
                },
                {
                    property: 'technicalName',
                    dataIndex: 'technicalName',
                    label: 'sw-settings-dictionary.entryList.columnTechnicalName',
                },
                {
                    property: 'active',
                    label: 'sw-settings-dictionary.entryList.columnActive',
                    align: 'center',
                },
            ];
        },
    },

    watch: {
        dictionaryTypeId() {
            void this.createdComponent();
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            this.isLoading = true;

            try {
                this.dictionaryType = await this.dictionaryTypeRepository.get(this.dictionaryTypeId as EntitySchema.DictionaryTypeId);
                await this.loadEntries();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.entryList.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        loadEntries(): Promise<EntityCollection<'dictionary_entry'>> {
            const criteria: CriteriaType = new Criteria(1, 500);

            criteria.addFilter(Criteria.equals('dictionaryTypeId', this.dictionaryTypeId));
            criteria.addSorting(Criteria.sort('position', 'ASC'));

            return this.entryRepository.search(criteria).then((result) => {
                this.entries = result;

                return result;
            });
        },

        onChangeLanguage(): void {
            void this.createdComponent();
        },

        onAddEntry(): void {
            this.newEntry = this.entryRepository.create();
            this.newEntry.dictionaryTypeId = this.dictionaryTypeId as EntitySchema.DictionaryEntryId;
            this.newEntry.position = this.nextFreePosition();
            this.newEntry.active = true;
            this.showCreateModal = true;
        },

        nextFreePosition(): number {
            const positions = (this.entries ?? []).map((entry) => entry.position ?? 0);

            return positions.length > 0 ? Math.max(...positions) + 10 : 10;
        },

        onCloseCreateModal(): void {
            this.showCreateModal = false;
            this.newEntry = null;
        },

        async onCreateSave(): Promise<void> {
            const newEntry = this.newEntry;

            if (!newEntry) {
                return;
            }

            if (!newEntry.label || newEntry.label.trim() === '') {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.entryList.messageLabelRequired'),
                });

                return;
            }

            if (!newEntry.technicalName || newEntry.technicalName.trim() === '') {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.entryList.messageTechnicalNameRequired'),
                });

                return;
            }

            const isDuplicate = (this.entries ?? []).some(
                (entry) => entry.technicalName === newEntry.technicalName,
            );

            if (isDuplicate) {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.entryList.messageTechnicalNameDuplicate'),
                });

                return;
            }

            try {
                await this.entryRepository.save(newEntry);
                this.dictionaryService.invalidateCaches();
                this.onCloseCreateModal();
                await this.loadEntries();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.entryList.messageSaveError'),
                });
            }
        },

        onInlineEditSave(promise: Promise<void>, item: DictionaryEntryEntity): Promise<void> {
            return promise
                .then(() => {
                    this.dictionaryService.invalidateCaches();
                    this.createNotificationSuccess({
                        message: this.$t('sw-settings-dictionary.entryList.messageSaveSuccess', { name: item.technicalName }, 0),
                    });
                })
                .catch(() => {
                    void this.loadEntries();
                    this.createNotificationError({
                        message: this.$t('sw-settings-dictionary.entryList.messageSaveError'),
                    });
                });
        },

        async onEntryActiveChange(item: DictionaryEntryEntity, value: boolean): Promise<void> {
            const previous = item.active;

            item.active = value;

            try {
                await this.entryRepository.save(item);
                this.dictionaryService.invalidateCaches();
            } catch {
                item.active = previous;
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.entryList.messageSaveError'),
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
                await this.entryRepository.delete(id as EntitySchema.DictionaryEntryId);
                this.dictionaryService.invalidateCaches();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-dictionary.entryList.messageDeleteError'),
                });
            } finally {
                await this.loadEntries();
            }
        },
    },
});
