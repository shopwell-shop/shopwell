/**
 * @sw-package checkout
 *
 * @private
 */
import type RepositoryType from 'src/core/data/repository.data';
import type { Router } from 'vue-router';

type LoyaltyTier = EntitySchema.Entity<'loyalty_tier'>;

interface LoyaltyTierCreateContext {
    isLoading: boolean;
    isSaveSuccessful: boolean;
    loyaltyTier: LoyaltyTier;
    loyaltyTierRepository: RepositoryType<'loyalty_tier'>;
    $router: Router;
    $t: (key: string) => string;
    createNotificationError: (config: { message: string }) => void;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    methods: {
        createdComponent(this: LoyaltyTierCreateContext): void {
            this.isLoading = true;
            Shopwell.Store.get('context').resetLanguageToDefault();
            this.loyaltyTier = this.loyaltyTierRepository.create();
            this.isLoading = false;
        },

        async onSave(this: LoyaltyTierCreateContext): Promise<void> {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            try {
                await this.loyaltyTierRepository.save(this.loyaltyTier);

                this.isSaveSuccessful = true;

                void this.$router.push({
                    name: 'sw.settings.loyalty.detail',
                    params: { id: this.loyaltyTier.id },
                });
            } catch (_err) {
                this.isLoading = false;

                this.createNotificationError({
                    message: this.$t('sw-loyalty.detail.notificationErrorMessage'),
                });
            }
        },
    },
};
