/**
 * @sw-package framework
 */
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-sms-delivery-list.html.twig';
import './sw-settings-sms-delivery-list.scss';

const { Criteria } = Shopwell.Data;

const STATUSES = ['queued', 'sent', 'failed'];

/**
 * The record of what the shop tried to send.
 *
 * The table it reads is written by the sending path, the administration only reads it - nothing here
 * changes an attempt that was made.
 *
 * @sw-package framework
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-delivery-list',

    template,

    inject: ['repositoryFactory', 'acl'],

    data(): {
        deliveries: EntityCollection<'sms_delivery'> | null;
        isLoading: boolean;
        page: number;
        limit: number;
        total: number;
        term: string;
        statusFilter: string | null;
    } {
        return {
            deliveries: null,
            isLoading: true,
            page: 1,
            limit: 25,
            total: 0,
            term: '',
            statusFilter: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        deliveryRepository(): RepositoryType<'sms_delivery'> {
            return this.repositoryFactory.create('sms_delivery');
        },

        columns(): Array<{ property: string; label: string; primary?: boolean }> {
            return [
                {
                    property: 'createdAt',
                    label: 'sw-settings-sms.delivery.columnCreatedAt',
                    primary: true,
                },
                {
                    property: 'templateRef',
                    label: 'sw-settings-sms.delivery.columnTemplateRef',
                },
                {
                    property: 'technicalName',
                    label: 'sw-settings-sms.delivery.columnTechnicalName',
                },
                {
                    property: 'phoneMasked',
                    label: 'sw-settings-sms.delivery.columnPhoneMasked',
                },
                {
                    property: 'status',
                    label: 'sw-settings-sms.delivery.columnStatus',
                },
                {
                    property: 'errorCode',
                    label: 'sw-settings-sms.delivery.columnErrorCode',
                },
            ];
        },

        statusFilterOptions(): Array<{ value: string; label: string }> {
            return STATUSES.map((status) => {
                return {
                    value: status,
                    label: this.$t(`sw-settings-sms.delivery.status.${status}`),
                };
            });
        },

        listCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC', true));

            if (this.term !== '') {
                criteria.setTerm(this.term);
            }

            if (this.statusFilter !== null) {
                criteria.addFilter(Criteria.equals('status', this.statusFilter));
            }

            return criteria;
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        statusLabel(status: string): string {
            const snippet = `sw-settings-sms.delivery.status.${status}`;

            return this.$te(snippet) ? this.$t(snippet) : status;
        },

        statusVariant(status: string): string {
            switch (status) {
                case 'sent':
                    return 'positive';
                case 'failed':
                    return 'critical';
                default:
                    return 'attention';
            }
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            try {
                const result = await this.deliveryRepository.search(this.listCriteria);

                this.deliveries = result;
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

        onFilterStatus(status: string | null): void {
            this.statusFilter = status;
            this.page = 1;

            void this.getList();
        },
    },
});
