/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';

import template from './sw-membership-tier-list.html.twig';
import './sw-membership-tier-list.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

type LoyaltyTier = EntitySchema.Entity<'loyalty_tier'>;

interface LoyaltyTierColumn {
    property: string;
    label: string;
    inlineEdit: string;
    routerLink?: string;
    primary?: boolean;
}

interface LoyaltyTierListingRef {
    deleteId: string | null;
    selection: Record<string, LoyaltyTier>;
    resetSelection: () => void;
    doSearch: () => void;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('placeholder'), Mixin.getByName('notification')],

    data(): {
        isLoading: boolean;
        sortBy: string;
        limit: number;
        loyaltyTiers: EntityCollection<'loyalty_tier'> | null;
        sortDirection: 'ASC' | 'DESC';
        searchConfigEntity: string;
    } {
        return {
            isLoading: false,
            sortBy: 'level',
            limit: 10,
            loyaltyTiers: null,
            sortDirection: 'ASC',
            searchConfigEntity: 'loyalty_tier',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        columns(): LoyaltyTierColumn[] {
            return this.getColumns();
        },

        loyaltyTierRepository(): RepositoryType<'loyalty_tier'> {
            return this.repositoryFactory.create('loyalty_tier');
        },

        showEmptyState(): boolean {
            return !this.isLoading && !this.total && Boolean(this.isValidTerm(this.term ?? ''));
        },

        allLoyaltyTiersCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term ?? '');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            void this.getList();
        },

        async getList(): Promise<void> {
            this.isLoading = true;

            const criteria = (await this.addQueryScores(this.term ?? '', this.allLoyaltyTiersCriteria)) as CriteriaType;

            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            await this.loyaltyTierRepository.search(criteria).then((searchResult) => {
                this.total = searchResult.total ?? 0;
                this.loyaltyTiers = searchResult;
                this.isLoading = false;
            });
        },

        getColumns(): LoyaltyTierColumn[] {
            return [
                {
                    property: 'name',
                    label: 'sw-membership.list.columnName',
                    inlineEdit: 'string',
                    routerLink: 'sw.settings.membership.detail',
                    primary: true,
                },
                {
                    property: 'level',
                    label: 'sw-membership.list.columnLevel',
                    inlineEdit: 'number',
                },
                {
                    property: 'minTierPoints',
                    label: 'sw-membership.list.columnMinTierPoints',
                    inlineEdit: 'number',
                },
                {
                    property: 'pointsMultiplier',
                    label: 'sw-membership.list.columnPointsMultiplier',
                    inlineEdit: 'number',
                },
                {
                    property: 'active',
                    label: 'sw-membership.list.columnActive',
                    inlineEdit: 'boolean',
                },
            ];
        },

        deleteLoyaltyTier(loyaltyTier: LoyaltyTier): void {
            const listing = this.$refs.listing as LoyaltyTierListingRef;
            listing.deleteId = null;

            void this.loyaltyTierRepository.delete(loyaltyTier.id).then(() => {
                listing.resetSelection();
                listing.doSearch();
            });
        },

        deleteLoyaltyTiers(): void {
            const listing = this.$refs.listing as LoyaltyTierListingRef;
            const selectedIds = Object.values(listing.selection).map((currentProxy) => {
                return currentProxy.id;
            });

            const criteria = new Criteria(1, this.limit);
            criteria.addFilter(Criteria.equalsAny('id', selectedIds));

            void this.loyaltyTierRepository.search(criteria).then((response) => {
                response.forEach((loyaltyTier) => {
                    void this.loyaltyTierRepository.delete(loyaltyTier.id);
                });

                listing.resetSelection();
                listing.doSearch();
            });
        },

        onContextMenuDelete(loyaltyTier: LoyaltyTier): void {
            (this.$refs.listing as LoyaltyTierListingRef).deleteId = loyaltyTier.id;
        },
    },
});
