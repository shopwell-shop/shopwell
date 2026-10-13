/**
 * @sw-package framework
 */
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-sms-template-list.html.twig';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

/**
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-template-list',

    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification')],

    data(): {
        templates: EntityCollection<'sms_template'> | null;
        isLoading: boolean;
        page: number;
        limit: number;
        total: number;
        term: string;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        naturalSorting: boolean;
        removeTarget: EntitySchema.Entity<'sms_template'> | null;
        isRemoving: boolean;
    } {
        return {
            templates: null,
            isLoading: true,
            page: 1,
            limit: 25,
            total: 0,
            term: '',
            sortBy: 'technicalName',
            sortDirection: 'ASC',
            naturalSorting: true,
            removeTarget: null,
            isRemoving: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        templateRepository(): RepositoryType<'sms_template'> {
            return this.repositoryFactory.create('sms_template');
        },

        columns(): Array<{ property: string; label: string; primary?: boolean }> {
            return [
                {
                    property: 'technicalName',
                    label: 'sw-settings-sms.template.columnTechnicalName',
                    primary: true,
                },
                {
                    property: 'scene',
                    label: 'sw-settings-sms.template.columnScene',
                },
                {
                    property: 'maxLength',
                    label: 'sw-settings-sms.template.columnMaxLength',
                },
                {
                    property: 'enabled',
                    label: 'sw-settings-sms.template.columnEnabled',
                },
            ];
        },

        createButtonTooltip() {
            return {
                message: this.$t('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('sms_template.creator'),
                showOnDisabledElements: true,
            };
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        sceneLabel(scene: string): string {
            const snippet = `sw-settings-sms.template.scene.${scene}`;

            return this.$te(snippet) ? this.$t(snippet) : scene;
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            if (this.term !== '') {
                criteria.setTerm(this.term);
            }

            try {
                const result = await this.templateRepository.search(criteria);

                this.templates = result;
                this.total = result.total ?? 0;
            } finally {
                this.isLoading = false;
            }
        },

        onSearch(term: string): void {
            this.term = term;
            this.page = 1;

            void this.getList();
        },

        onPageChange({ page, limit }: { page: number; limit: number }): void {
            this.page = page;
            this.limit = limit;

            void this.getList();
        },

        onSortColumn({ sortBy, sortDirection }: { sortBy: string; sortDirection: 'ASC' | 'DESC' }): void {
            this.sortBy = sortBy;
            this.sortDirection = sortDirection;

            void this.getList();
        },

        onOpenRemoveModal(target: EntitySchema.Entity<'sms_template'>): void {
            this.removeTarget = target;
        },

        onCloseRemoveModal(): void {
            this.removeTarget = null;
        },

        onRemoveModalChange(isOpen: boolean): void {
            if (!isOpen) {
                this.onCloseRemoveModal();
            }
        },

        async onConfirmRemove(): Promise<void> {
            if (this.removeTarget === null) {
                return;
            }

            this.isRemoving = true;

            try {
                await this.templateRepository.delete(this.removeTarget.id);

                this.onCloseRemoveModal();

                this.createNotificationSuccess({
                    message: this.$t('sw-settings-sms.notification.removeSuccess'),
                });

                await this.getList();
            } catch (error) {
                this.createNotificationError({
                    message: (error as Error).message,
                });
            } finally {
                this.isRemoving = false;
            }
        },
    },
});
