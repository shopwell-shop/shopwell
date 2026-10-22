/**
 * @sw-package framework
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-position-list.html.twig';
import './sw-settings-position-list.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

type PositionEntity = EntitySchema.Entity<'position'>;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification'), Mixin.getByName('listing'), Mixin.getByName('placeholder')],

    data(): {
        entityName: string;
        positions: EntityCollection<'position'> | null;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        isLoading: boolean;
        showEditModal: boolean;
        showDeleteModal: string | null;
        editedPosition: PositionEntity | null;
        isNewPosition: boolean;
    } {
        return {
            entityName: 'position',
            positions: null,
            sortBy: 'position',
            sortDirection: 'ASC',
            isLoading: false,
            showEditModal: false,
            showDeleteModal: null,
            editedPosition: null,
            isNewPosition: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        positionRepository(): RepositoryType<'position'> {
            return this.repositoryFactory.create('position');
        },

        positionColumns(): Array<Record<string, unknown>> {
            return [
                {
                    property: 'name',
                    dataIndex: 'name',
                    label: 'sw-settings-position.list.columnName',
                    primary: true,
                },
                {
                    property: 'position',
                    dataIndex: 'position',
                    label: 'sw-settings-position.list.columnPosition',
                    align: 'right',
                },
                {
                    property: 'active',
                    label: 'sw-settings-position.list.columnActive',
                    align: 'center',
                },
            ];
        },

        modalTitle(): string {
            return this.isNewPosition
                ? this.$t('sw-settings-position.list.modalTitleCreate')
                : this.$t('sw-settings-position.list.modalTitleEdit');
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

            try {
                const result = await this.positionRepository.search(criteria);

                this.positions = result;
                this.total = result.total ?? 0;
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-position.list.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onChangeLanguage(): void {
            void this.getList();
        },

        onAddPosition(): void {
            this.editedPosition = this.positionRepository.create();
            this.editedPosition.position = this.nextFreePosition();
            this.editedPosition.active = true;
            this.isNewPosition = true;
            this.showEditModal = true;
        },

        onEditPosition(item: PositionEntity): void {
            this.editedPosition = item;
            this.isNewPosition = false;
            this.showEditModal = true;
        },

        nextFreePosition(): number {
            const values = (this.positions ?? []).map((item) => item.position ?? 0);

            return values.length > 0 ? Math.max(...values) + 10 : 10;
        },

        onCloseEditModal(): void {
            this.showEditModal = false;
            this.editedPosition = null;
            this.isNewPosition = false;
        },

        async onEditModalSave(): Promise<void> {
            if (!this.editedPosition) {
                return;
            }

            if (!this.editedPosition.name || this.editedPosition.name.trim() === '') {
                this.createNotificationError({
                    message: this.$t('sw-settings-position.list.messageNameRequired'),
                });

                return;
            }

            try {
                await this.positionRepository.save(this.editedPosition);
                this.onCloseEditModal();
                void this.getList();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-position.list.messageSaveError'),
                });
            }
        },

        async onActiveChange(item: PositionEntity, value: boolean): Promise<void> {
            const previous = item.active;

            item.active = value;

            try {
                await this.positionRepository.save(item);
            } catch {
                item.active = previous;
                this.createNotificationError({
                    message: this.$t('sw-settings-position.list.messageSaveError'),
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
                await this.positionRepository.delete(id as EntitySchema.PositionId);
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-settings-position.list.messageDeleteError'),
                });
            } finally {
                void this.getList();
            }
        },
    },
});
