/**
 * @sw-package framework
 */
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-sms-gateway-list.html.twig';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

/**
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-gateway-list',

    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('notification')],

    data(): {
        gateways: EntityCollection<'sms_gateway'> | null;
        isLoading: boolean;
        page: number;
        limit: number;
        total: number;
        term: string;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
        naturalSorting: boolean;
        removeTarget: EntitySchema.Entity<'sms_gateway'> | null;
        isRemoving: boolean;
    } {
        return {
            gateways: null,
            isLoading: true,
            page: 1,
            limit: 25,
            total: 0,
            term: '',
            sortBy: 'priority',
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
        gatewayRepository(): RepositoryType<'sms_gateway'> {
            return this.repositoryFactory.create('sms_gateway');
        },

        columns(): Array<{ property: string; label: string; primary?: boolean }> {
            return [
                {
                    property: 'technicalName',
                    label: 'sw-settings-sms.gateway.columnTechnicalName',
                    primary: true,
                },
                {
                    property: 'label',
                    label: 'sw-settings-sms.gateway.columnLabel',
                },
                {
                    property: 'enabled',
                    label: 'sw-settings-sms.gateway.columnEnabled',
                },
                {
                    property: 'priority',
                    label: 'sw-settings-sms.gateway.columnPriority',
                },
                {
                    property: 'strategy',
                    label: 'sw-settings-sms.gateway.columnStrategy',
                },
            ];
        },

        createButtonTooltip() {
            return {
                message: this.$t('sw-privileges.tooltip.warning'),
                disabled: this.acl.can('sms_gateway.creator'),
                showOnDisabledElements: true,
            };
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        /**
         * A provider is code and ships its own name as a snippet of the platform, a provider an
         * extension added may ship none - then its technical name is the honest answer.
         */
        providerLabel(technicalName: string): string {
            const snippet = `sms.gateway.${technicalName}`;

            return this.$te(snippet) ? this.$t(snippet) : technicalName;
        },

        strategyLabel(strategy: string): string {
            const snippet = `sw-settings-sms.gateway.strategy.${strategy}`;

            return this.$te(snippet) ? this.$t(snippet) : strategy;
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            if (this.term !== '') {
                criteria.setTerm(this.term);
            }

            try {
                const result = await this.gatewayRepository.search(criteria);

                this.gateways = result;
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

        onOpenRemoveModal(target: EntitySchema.Entity<'sms_gateway'>): void {
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
                await this.gatewayRepository.delete(this.removeTarget.id);

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
