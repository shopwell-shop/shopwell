/**
 * @sw-package framework
 */
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import template from './sw-settings-sms-delivery-list.html.twig';
import './sw-settings-sms-delivery-list.scss';

const { Criteria } = Shopwell.Data;

const STATUSES = ['queued', 'sent', 'failed'];

type FilterDefinition = {
    property: string;
    label: string;
    type?: string;
    placeholder?: string;
    options?: Array<{ value: string; label: string }>;
    valueProperty?: string;
    labelProperty?: string;
    dateType?: string;
    showTimeframe?: boolean;
    fromPlaceholder?: string;
    toPlaceholder?: string;
};

interface FilterFactory {
    create(entity: string, filters: Record<string, FilterDefinition>): FilterDefinition[];
}

interface FilterService {
    mergeWithStoredFilters(storeKey: string, criteria: CriteriaType): Promise<CriteriaType>;
}

/**
 * @private
 */
export default Shopwell.Component.wrapComponentConfig({
    name: 'sw-settings-sms-delivery-list',

    template,

    inject: ['repositoryFactory', 'acl', 'filterFactory'],

    data(): {
        deliveries: EntityCollection<'sms_delivery'> | null;
        isLoading: boolean;
        page: number;
        limit: number;
        total: number;
        term: string;
        filterCriteria: CriteriaType['filters'];
        activeFilterNumber: number;
        storeKey: string;
    } {
        return {
            deliveries: null,
            isLoading: true,
            page: 1,
            limit: 25,
            total: 0,
            term: '',
            filterCriteria: [],
            activeFilterNumber: 0,
            storeKey: 'grid.filter.sms_delivery',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        dateFilter(): (value: string, options?: Intl.DateTimeFormatOptions) => string {
            return Shopwell.Filter.getByName('date') as (value: string, options?: Intl.DateTimeFormatOptions) => string;
        },

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

        listFilters(): FilterDefinition[] {
            const filters = {
                'status-filter': {
                    property: 'status',
                    type: 'multi-select-filter',
                    label: this.$t('sw-settings-sms.delivery.filterStatus'),
                    placeholder: this.$t('sw-settings-sms.delivery.filterStatus'),
                    options: this.statusFilterOptions,
                    valueProperty: 'value',
                    labelProperty: 'label',
                },
                ...(this.acl.can('sms_gateway.viewer')
                    ? {
                          'gateway-filter': {
                              property: 'gateway',
                              label: this.$t('sw-settings-sms.delivery.filterGateway'),
                              placeholder: this.$t('sw-settings-sms.delivery.filterGateway'),
                              labelProperty: 'label',
                          },
                      }
                    : {}),
                'created-at-filter': {
                    property: 'createdAt',
                    label: this.$t('sw-settings-sms.delivery.columnCreatedAt'),
                    dateType: 'datetime',
                    showTimeframe: true,
                    fromPlaceholder: this.$t('sw-settings-sms.delivery.filterFrom'),
                    toPlaceholder: this.$t('sw-settings-sms.delivery.filterUntil'),
                },
            };

            const filterFactory: FilterFactory = this.filterFactory;

            return filterFactory.create('sms_delivery', filters);
        },

        defaultFilters(): string[] {
            return ['status-filter', 'gateway-filter', 'created-at-filter'];
        },

        listCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC', true));
            criteria.setTerm(this.term);
            this.filterCriteria.forEach((filter) => criteria.addFilter(filter));

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
                const filterService = Shopwell.Service('filterService') as FilterService;
                const criteria = await filterService.mergeWithStoredFilters(this.storeKey, this.listCriteria);
                this.activeFilterNumber = criteria.filters.length;

                const result = await this.deliveryRepository.search(criteria);

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

        updateCriteria(filters: CriteriaType['filters']): void {
            this.filterCriteria = filters;
            this.page = 1;

            void this.getList();
        },
    },
});
