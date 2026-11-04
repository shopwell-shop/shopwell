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
    persistLoyaltyTier: () => Promise<void>;
    $router: Router;
    $t: (key: string) => string;
    createNotificationError: (config: { message: string }) => void;
}

// The page inherits the detail page and only decides how the first save of a tier ends: a create has
// no id to stay on, so it navigates to the detail page it just produced.
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- a page of this module, registered in index.ts
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
                // the inherited save writes the tier and the benefits it grants, so a tier created with a
                // multiplier does not lose it on the way to the detail page
                await this.persistLoyaltyTier();

                this.isSaveSuccessful = true;

                void this.$router.push({
                    name: 'sw.settings.membership.detail',
                    params: { id: this.loyaltyTier.id },
                });
            } catch (_err) {
                this.isLoading = false;

                this.createNotificationError({
                    message: this.$t('sw-membership.detail.notificationErrorMessage'),
                });
            }
        },
    },
};
