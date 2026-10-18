/**
 * @sw-package checkout
 *
 * @private
 */
import type RepositoryType from 'src/core/data/repository.data';
import type { Router } from 'vue-router';

type MembershipPlan = EntitySchema.Entity<'membership_plan'>;

interface MembershipPlanCreateContext {
    isLoading: boolean;
    isSaveSuccessful: boolean;
    plan: MembershipPlan;
    planRepository: RepositoryType<'membership_plan'>;
    $router: Router;
    $t: (key: string) => string;
    createNotificationError: (config: { message: string }) => void;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    methods: {
        createdComponent(this: MembershipPlanCreateContext): void {
            this.isLoading = true;
            Shopwell.Store.get('context').resetLanguageToDefault();
            this.plan = this.planRepository.create();
            this.plan.active = true;
            this.plan.durationDays = 365;
            this.isLoading = false;
        },

        async onSave(this: MembershipPlanCreateContext): Promise<void> {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            try {
                await this.planRepository.save(this.plan);

                this.isSaveSuccessful = true;

                void this.$router.push({
                    name: 'sw.settings.loyalty.planDetail',
                    params: { id: this.plan.id },
                });
            } catch (_err) {
                this.isLoading = false;

                this.createNotificationError({
                    message: this.$t('sw-loyalty.plan.detail.notificationErrorMessage'),
                });
            }
        },
    },
};
