/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';

import template from './sw-loyalty-plan-detail.html.twig';
import './sw-loyalty-plan-detail.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;
const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();

type MembershipPlan = EntitySchema.Entity<'membership_plan'>;
type MembershipPlanEntity = MembershipPlan & { isNew: () => boolean };

interface DeviceHelper {
    getSystemKey: () => string;
}

/**
 * One purchasable membership: which tier it grants, for how long, in which currency and at which
 * price. The price is a plain amount in the currency of the plan, the storefront converts it for the
 * customer.
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('plan'),
    ],

    props: {
        planId: {
            type: String,
            required: false,
            default: null,
        },
    },

    shortcuts: {
        'SYSTEMKEY+S': {
            active(): boolean {
                return (this as unknown as { allowSave: boolean }).allowSave;
            },
            method: 'onSave',
        },

        ESCAPE: 'onCancel',
    },

    data(): {
        isLoading: boolean;
        isSaveSuccessful: boolean;
        plan: MembershipPlan | null;
    } {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            plan: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier(): string {
            if (!this.plan) {
                return '';
            }

            return this.placeholder(this.plan, 'name', '') as string;
        },

        planRepository(): RepositoryType<'membership_plan'> {
            return this.repositoryFactory.create('membership_plan');
        },

        planCriteria(): CriteriaType {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('loyaltyTier');
            criteria.addAssociation('currency');

            return criteria;
        },

        entityDescription(): string {
            if (!this.plan) {
                return this.$t('sw-loyalty.plan.detail.placeholderNewPlan');
            }

            return this.placeholder(
                this.plan,
                'name',
                this.$t('sw-loyalty.plan.detail.placeholderNewPlan'),
            ) as string;
        },

        tooltipSave() {
            if (!this.allowSave) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.allowSave,
                    showOnDisabledElements: true,
                };
            }

            const device = this as unknown as { $device: DeviceHelper };

            return {
                message: `${device.$device.getSystemKey()} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        ...mapPropertyErrors('plan', ['name', 'technicalName', 'price', 'durationDays']),

        allowSave(): boolean {
            if (!this.plan) {
                return false;
            }

            return (this.plan as MembershipPlanEntity).isNew()
                ? this.acl.can('loyalty.creator')
                : this.acl.can('loyalty.editor');
        },
    },

    watch: {
        planId() {
            if (!this.planId) {
                this.createdComponent();
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            this.isLoading = true;

            if (!this.planId) {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationLoadingDataErrorMessage'),
                });

                this.isLoading = false;

                return;
            }

            void this.loadPlan();
        },

        async loadPlan(): Promise<void> {
            this.isLoading = true;

            try {
                this.plan = await this.planRepository.get(
                    this.planId as MembershipPlan['id'],
                    Shopwell.Context.api,
                    this.planCriteria,
                );
            } finally {
                this.isLoading = false;
            }
        },

        onChangeLanguage(): void {
            this.createdComponent();
        },

        onCancel(): void {
            void this.$router.push({ name: 'sw.settings.loyalty.plans' });
        },

        onSave(): Promise<void> {
            if (!this.plan) {
                return Promise.resolve();
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.planRepository
                .save(this.plan)
                .then(() => {
                    this.isSaveSuccessful = true;

                    void this.loadPlan();

                    this.createNotificationSuccess({
                        message: this.$t('sw-loyalty.plan.detail.messageSaveSuccess'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-loyalty.plan.detail.notificationErrorMessage'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
