/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';

import template from './sw-loyalty-plan-list.html.twig';
import './sw-loyalty-plan-list.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;

type MembershipPlan = EntitySchema.Entity<'membership_plan'>;

interface MembershipPlanColumn {
    property: string;
    label: string;
    routerLink?: string;
    primary?: boolean;
}

interface MembershipPlanListingRef {
    deleteId: string | null;
    selection: Record<string, MembershipPlan>;
    resetSelection: () => void;
    doSearch: () => void;
}

/**
 * The membership plans a customer can buy in the storefront. A plan is one price for one tier and one
 * duration - the tier itself is managed next door and stays untouched when a plan is retired.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [Mixin.getByName('listing'), Mixin.getByName('notification')],

    data(): {
        isLoading: boolean;
        limit: number;
        plans: EntityCollection<'membership_plan'> | null;
        sortBy: string;
        sortDirection: 'ASC' | 'DESC';
    } {
        return {
            isLoading: false,
            limit: 10,
            plans: null,
            sortBy: 'name',
            sortDirection: 'ASC',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        planRepository(): RepositoryType<'membership_plan'> {
            return this.repositoryFactory.create('membership_plan');
        },

        columns(): MembershipPlanColumn[] {
            return [
                {
                    property: 'name',
                    label: 'sw-loyalty.plan.list.columnName',
                    routerLink: 'sw.settings.loyalty.planDetail',
                    primary: true,
                },
                {
                    property: 'loyaltyTier',
                    label: 'sw-loyalty.plan.list.columnTier',
                },
                {
                    property: 'price',
                    label: 'sw-loyalty.plan.list.columnPrice',
                },
                {
                    property: 'durationDays',
                    label: 'sw-loyalty.plan.list.columnDurationDays',
                },
                {
                    property: 'active',
                    label: 'sw-loyalty.plan.list.columnActive',
                },
            ];
        },

        planCriteria(): CriteriaType {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term ?? '');
            criteria.addAssociation('loyaltyTier');
            criteria.addAssociation('currency');
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            return criteria;
        },

        currencyFilter() {
            return Shopwell.Filter.getByName('currency');
        },

        showEmptyState(): boolean {
            return !this.isLoading && this.total === 0;
        },
    },

    created() {
        void this.getList();
    },

    methods: {
        async getList(): Promise<void> {
            this.isLoading = true;

            const criteria = (await this.addQueryScores(this.term ?? '', this.planCriteria)) as CriteriaType;

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            try {
                const result = await this.planRepository.search(criteria);

                this.total = result.total ?? 0;
                this.plans = result;
            } finally {
                this.isLoading = false;
            }
        },

        formatPrice(plan: MembershipPlan): string {
            const isoCode = plan.currency?.isoCode ?? 'CNY';

            return this.currencyFilter(plan.price, isoCode) as string;
        },

        onDeletePlan(plan: MembershipPlan): void {
            const listing = this.$refs.listing as MembershipPlanListingRef;
            listing.deleteId = null;

            void this.planRepository.delete(plan.id).then(() => {
                listing.resetSelection();
                listing.doSearch();
            });
        },

        onDeletePlans(): void {
            const listing = this.$refs.listing as MembershipPlanListingRef;

            const criteria = new Criteria(1, this.limit);
            criteria.addFilter(Criteria.equalsAny('id', Object.values(listing.selection).map((plan) => plan.id)));

            void this.planRepository.search(criteria).then((response) => {
                response.forEach((plan) => {
                    void this.planRepository.delete(plan.id);
                });

                listing.resetSelection();
                listing.doSearch();
            });
        },

        onContextMenuDelete(plan: MembershipPlan): void {
            (this.$refs.listing as MembershipPlanListingRef).deleteId = plan.id;
        },
    },
});
