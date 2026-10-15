/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';

import template from './sw-loyalty-tier-detail.html.twig';
import './sw-loyalty-tier-detail.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;
const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();

type LoyaltyTier = EntitySchema.Entity<'loyalty_tier'>;
type LoyaltyTierEntity = LoyaltyTier & { isNew: () => boolean };

interface DeviceHelper {
    getSystemKey: () => string;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default defineComponent({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('loyaltyTier'),
    ],

    props: {
        loyaltyTierId: {
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
        loyaltyTier: LoyaltyTier | null;
        isSaveSuccessful: boolean;
    } {
        return {
            isLoading: false,
            loyaltyTier: null,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier(): string {
            return this.placeholder(this.loyaltyTier, 'name', '') as string;
        },

        loyaltyTierRepository(): RepositoryType<'loyalty_tier'> {
            return this.repositoryFactory.create('loyalty_tier');
        },

        loyaltyTierCriteria(): CriteriaType {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('customerGroup');

            return criteria;
        },

        entityDescription(): string {
            return this.placeholder(
                this.loyaltyTier,
                'name',
                this.$t('sw-loyalty.detail.placeholderNewLoyaltyTier'),
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

            const systemKey: string = (this.$device as DeviceHelper).getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        ...mapPropertyErrors('loyaltyTier', ['name', 'technicalName', 'level']),

        allowSave(): boolean {
            if (!this.loyaltyTier) {
                return false;
            }

            return (this.loyaltyTier as LoyaltyTierEntity).isNew()
                ? this.acl.can('loyalty.creator')
                : this.acl.can('loyalty.editor');
        },
    },

    watch: {
        loyaltyTierId() {
            if (!this.loyaltyTierId) {
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

            if (!this.loyaltyTierId) {
                this.createNotificationError({
                    message: this.$t('global.notification.notificationLoadingDataErrorMessage'),
                });

                this.isLoading = false;

                return;
            }

            void this.loadLoyaltyTier();
        },

        async loadLoyaltyTier(): Promise<void> {
            this.isLoading = true;

            try {
                this.loyaltyTier = await this.loyaltyTierRepository.get(
                    this.loyaltyTierId,
                    Shopwell.Context.api,
                    this.loyaltyTierCriteria,
                );
            } finally {
                this.isLoading = false;
            }
        },

        onChangeLanguage(): void {
            this.createdComponent();
        },

        onCancel(): void {
            void this.$router.push({ name: 'sw.settings.loyalty.index' });
        },

        onSave(): Promise<void> {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.loyaltyTierRepository
                .save(this.loyaltyTier)
                .then(() => {
                    this.isSaveSuccessful = true;

                    void this.loadLoyaltyTier();

                    this.createNotificationSuccess({
                        message: this.$t('sw-loyalty.detail.messageSaveSuccess'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-loyalty.detail.notificationErrorMessage'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
