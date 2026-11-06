/**
 * @sw-package checkout
 */
import { defineComponent } from 'vue';
import type CriteriaType from 'src/core/data/criteria.data';
import type RepositoryType from 'src/core/data/repository.data';
import { systemKey } from '../../service/device.helper';
import { shortcutAllowsSave } from '../../service/shortcut.helper';

import template from './sw-membership-tier-detail.html.twig';
import './sw-membership-tier-detail.scss';

const { Mixin } = Shopwell;
const { Criteria } = Shopwell.Data;
const { mapPropertyErrors } = Shopwell.Component.getComponentHelper();

type LoyaltyTier = EntitySchema.Entity<'loyalty_tier'>;
type LoyaltyTierEntity = LoyaltyTier & { isNew: () => boolean };
type LoyaltyTierBenefit = EntitySchema.Entity<'loyalty_tier_benefit'>;

/**
 * What a tier grants is a row of `loyalty_tier_benefit`, not a column - the PHP side is
 * `Shopwell\Core\Checkout\Loyalty\LoyaltyBenefitTypes`, and the type strings below are its constants.
 *
 * `neutral` is the value that means "this tier grants nothing". A form value equal to it writes no
 * row, and an existing row is removed when the operator sets the field back to it. That keeps one
 * representation: the absence of a row is the absence of the benefit, and no separate "enabled" switch
 * can contradict the value next to it.
 */
const BENEFIT_TYPES = {
    EARN_MULTIPLIER: 'earn_multiplier',
    CART_DISCOUNT: 'cart_discount',
    FREE_SHIPPING: 'free_shipping',
    BIRTHDAY_GIFT: 'birthday_gift',
    POINTS_EXPIRY_DAYS: 'points_expiry_days',
} as const;

interface BenefitForm {
    earnMultiplier: number;
    cartDiscount: number;
    grantsFreeShipping: boolean;
    birthdayGift: number;
    pointsExpiryDays: number | null;
}

interface BenefitDescriptor {
    type: string;
    position: number;
    neutral: number;
    value: (form: BenefitForm) => number;
}

/**
 * One entry per type the shop knows, in the order the storefront prints them. `position` is written
 * from this order and is not operator editable: the schema allows one row per type, so the list has a
 * fixed set of entries and dragging them around would only reorder five rows nobody can add to.
 */
const BENEFIT_DESCRIPTORS: BenefitDescriptor[] = [
    {
        type: BENEFIT_TYPES.EARN_MULTIPLIER,
        position: 10,
        neutral: 1,
        value: (form) => form.earnMultiplier,
    },
    {
        type: BENEFIT_TYPES.CART_DISCOUNT,
        position: 20,
        neutral: 0,
        value: (form) => form.cartDiscount,
    },
    {
        type: BENEFIT_TYPES.FREE_SHIPPING,
        position: 30,
        neutral: 0,
        value: (form) => (form.grantsFreeShipping ? 1 : 0),
    },
    {
        type: BENEFIT_TYPES.BIRTHDAY_GIFT,
        position: 40,
        neutral: 0,
        value: (form) => form.birthdayGift,
    },
    {
        type: BENEFIT_TYPES.POINTS_EXPIRY_DAYS,
        position: 50,
        neutral: 0,
        value: (form) => form.pointsExpiryDays ?? 0,
    },
];

function emptyBenefitForm(): BenefitForm {
    return {
        earnMultiplier: 1,
        cartDiscount: 0,
        grantsFreeShipping: false,
        birthdayGift: 0,
        pointsExpiryDays: null,
    };
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- registered by the module, extended through its twig blocks
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
            // the shortcut registry calls this without typing `this`, so the computed it reads is
            // reached through the shared helper instead of an assertion in every page
            active(): boolean {
                return shortcutAllowsSave(this);
            },
            method: 'onSave',
        },

        ESCAPE: 'onCancel',
    },

    data(): {
        isLoading: boolean;
        loyaltyTier: LoyaltyTier | null;
        isSaveSuccessful: boolean;
        benefits: BenefitForm;
        benefitRows: Record<string, LoyaltyTierBenefit>;
    } {
        return {
            isLoading: false,
            loyaltyTier: null,
            isSaveSuccessful: false,
            benefits: emptyBenefitForm(),
            benefitRows: {},
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier(): string {
            if (!this.loyaltyTier) {
                return '';
            }

            return this.placeholder(this.loyaltyTier, 'name', '') as string;
        },

        loyaltyTierRepository(): RepositoryType<'loyalty_tier'> {
            return this.repositoryFactory.create('loyalty_tier');
        },

        benefitRepository(): RepositoryType<'loyalty_tier_benefit'> {
            return this.repositoryFactory.create('loyalty_tier_benefit');
        },

        loyaltyTierCriteria(): CriteriaType {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('customerGroup');

            return criteria;
        },

        entityDescription(): string {
            if (!this.loyaltyTier) {
                return this.$t('sw-membership.detail.placeholderNewLoyaltyTier');
            }

            return this.placeholder(
                this.loyaltyTier,
                'name',
                this.$t('sw-membership.detail.placeholderNewLoyaltyTier'),
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

            return {
                message: `${systemKey(this)} + S`,
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
            if (!this.loyaltyTierId) {
                this.isLoading = false;

                return;
            }

            void this.loadLoyaltyTier();
        },

        async loadLoyaltyTier(): Promise<void> {
            this.isLoading = true;

            try {
                const loyaltyTier = await this.loyaltyTierRepository.get(
                    this.loyaltyTierId as LoyaltyTier['id'],
                    Shopwell.Context.api,
                    this.loyaltyTierCriteria,
                );

                this.loyaltyTier = loyaltyTier;

                if (loyaltyTier) {
                    await this.loadBenefits(loyaltyTier.id);
                }
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Reads the grants of the tier as their own query instead of as an association of the tier: the
         * form is saved through the tier entity, and an entity that carries a loaded collection writes it
         * back - which would turn "the operator removed this benefit" into "nothing happened".
         */
        async loadBenefits(tierId: LoyaltyTier['id']): Promise<void> {
            const criteria = new Criteria(1, 50);
            criteria.addFilter(Criteria.equals('loyaltyTierId', tierId));

            const rows = await this.benefitRepository.search(criteria);
            const byType: Record<string, LoyaltyTierBenefit> = {};

            rows.forEach((row) => {
                byType[row.type] = row;
            });

            this.benefitRows = byType;
            this.benefits = {
                earnMultiplier: byType[BENEFIT_TYPES.EARN_MULTIPLIER]?.value ?? 1,
                cartDiscount: byType[BENEFIT_TYPES.CART_DISCOUNT]?.value ?? 0,
                grantsFreeShipping: (byType[BENEFIT_TYPES.FREE_SHIPPING]?.value ?? 0) > 0,
                birthdayGift: byType[BENEFIT_TYPES.BIRTHDAY_GIFT]?.value ?? 0,
                pointsExpiryDays: byType[BENEFIT_TYPES.POINTS_EXPIRY_DAYS]?.value ?? null,
            };
        },

        /**
         * Writes the form back to the benefit table: a granted value becomes a row, a neutral one
         * removes the row. Types the shop does not know are never touched - a benefit an extension
         * invented stays stored even though this form cannot show it.
         */
        async syncBenefits(tierId: LoyaltyTier['id']): Promise<void> {
            const saves: LoyaltyTierBenefit[] = [];
            const deletions: LoyaltyTierBenefit['id'][] = [];

            BENEFIT_DESCRIPTORS.forEach((descriptor) => {
                const value = descriptor.value(this.benefits);
                const row = this.benefitRows[descriptor.type];

                if (value === descriptor.neutral) {
                    if (row) {
                        deletions.push(row.id);

                        delete this.benefitRows[descriptor.type];
                    }

                    return;
                }

                const entity = row ?? this.benefitRepository.create(Shopwell.Context.api);

                entity.loyaltyTierId = tierId;
                entity.type = descriptor.type;
                entity.value = value;
                entity.position = descriptor.position;

                saves.push(entity);
                this.benefitRows[descriptor.type] = entity;
            });

            await Promise.all([
                ...deletions.map((id) => this.benefitRepository.delete(id)),
                ...saves.map((entity) => this.benefitRepository.save(entity)),
            ]);
        },

        /**
         * Saves the tier and then what it grants. The tier comes first: a new tier has no id in the
         * database before that, and a benefit row needs one.
         */
        async persistLoyaltyTier(): Promise<void> {
            if (!this.loyaltyTier) {
                return;
            }

            await this.loyaltyTierRepository.save(this.loyaltyTier);
            await this.syncBenefits(this.loyaltyTier.id);
        },

        onChangeLanguage(): void {
            this.createdComponent();
        },

        onCancel(): void {
            void this.$router.push({ name: 'sw.settings.membership.index' });
        },

        onSave(): Promise<void> {
            if (!this.loyaltyTier) {
                return Promise.resolve();
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.persistLoyaltyTier()
                .then(() => {
                    this.isSaveSuccessful = true;

                    void this.loadLoyaltyTier();

                    this.createNotificationSuccess({
                        message: this.$t('sw-membership.detail.messageSaveSuccess'),
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('sw-membership.detail.notificationErrorMessage'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
