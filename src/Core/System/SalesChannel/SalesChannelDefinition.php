<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroupRegistrationSalesChannel\CustomerGroupRegistrationSalesChannelDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistDefinition;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelDefinition;
use Shopwell\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Cms\CmsPageDefinition;
use Shopwell\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopwell\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\ProductExport\ProductExportDefinition;
use Shopwell\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopwell\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopwell\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryDefinition;
use Shopwell\Core\System\Currency\CurrencyDefinition;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Shopwell\Core\System\SystemConfig\SystemConfigDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'sales_channel';
    final public const CALCULATION_TYPE_VERTICAL = 'vertical';
    final public const CALCULATION_TYPE_HORIZONTAL = 'horizontal';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalesChannelEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'taxCalculationType' => self::CALCULATION_TYPE_HORIZONTAL,
            'homeEnabled' => true,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of sales channel.'),
            (new FkField('type_id', 'typeId', SalesChannelTypeDefinition::class))->addFlags(new Required())->setDescription('Unique identity of type.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of language used.'),
            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of customer group.'),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of currency used.'),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of payment method used.'),
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of shipping method.'),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of country.'),
            (new FkField('analytics_id', 'analyticsId', SalesChannelAnalyticsDefinition::class))->setDescription('Unique identity of country.'),

            (new FkField('navigation_category_id', 'navigationCategoryId', CategoryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of navigation category.'),
            (new ReferenceVersionField(CategoryDefinition::class, 'navigation_category_version_id'))->addFlags(new ApiAware(), new Required()),
            (new IntField('navigation_category_depth', 'navigationCategoryDepth', 1))->addFlags(new ApiAware())->setDescription('It determines the number of levels of subcategories in the storefront category menu.'),
            (new FkField('footer_category_id', 'footerCategoryId', CategoryDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of footer category.'),
            (new ReferenceVersionField(CategoryDefinition::class, 'footer_category_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('service_category_id', 'serviceCategoryId', CategoryDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of service category.'),
            (new ReferenceVersionField(CategoryDefinition::class, 'service_category_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('mail_header_footer_id', 'mailHeaderFooterId', MailHeaderFooterDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of mail header and footer.'),
            (new FkField('hreflang_default_domain_id', 'hreflangDefaultDomainId', SalesChannelDomainDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of hreflangDefaultDomain.'),
            (new MeasurementUnitsField('measurement_units', 'measurementUnits'))->addFlags(new ApiAware(), new Since('6.7.1.0')),
            (new TimeZoneField('business_time_zone', 'businessTimeZone'))->addFlags(new ApiAware(), new Since('6.7.13.0'))->setDescription('Business timezone used for sales-channel-specific rendering.'),
            (new TranslatedField('name'))->addFlags(new ApiAware()),
            (new StringField('short_name', 'shortName'))->addFlags(new ApiAware())->setDescription('A short name for sales channel.'),
            (new StringField('tax_calculation_type', 'taxCalculationType'))->addFlags(new ApiAware())->setDescription('Tax calculation types are `horizontal` and `vertical`.'),
            (new StringField('access_key', 'accessKey'))->addFlags(new Required())->setDescription('Access key to store api.'),
            (new JsonField('configuration', 'configuration'))->addFlags(new ApiAware())->setDescription('Internal field.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the sales channel is enabled.'),
            (new BoolField('hreflang_active', 'hreflangActive'))->addFlags(new ApiAware())->setDescription('When set to true, the sales channel pages are available in different languages.'),
            (new BoolField('maintenance', 'maintenance'))->addFlags(new ApiAware())->setDescription('When `true`, it indicates that the sales channel is undergoing maintenance, and shopping is temporarily unavailable during this period.'),
            (new ListField('maintenance_ip_allowlist', 'maintenanceIpAllowlist', StringField::class))->setDescription('List of IP addresses allowed to access the sales channel while the maintenance mode is active.'),
            // @deprecated tag:v6.8.0 - Will be removed, use `maintenanceIpAllowlist` instead.
            (new ListField('maintenance_ip_whitelist', 'maintenanceIpWhitelist', StringField::class))->addFlags(new Deprecated('v6.7.13.0', 'v6.8.0.0', 'maintenanceIpAllowlist'))->setDescription('List of IP addresses used when the maintenance mode is active.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(SalesChannelTranslationDefinition::class, 'sales_channel_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('currencies', CurrencyDefinition::class, SalesChannelCurrencyDefinition::class, 'sales_channel_id', 'currency_id'),
            new ManyToManyAssociationField('languages', LanguageDefinition::class, SalesChannelLanguageDefinition::class, 'sales_channel_id', 'language_id'),
            new ManyToManyAssociationField('countries', CountryDefinition::class, SalesChannelCountryDefinition::class, 'sales_channel_id', 'country_id'),
            new ManyToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, SalesChannelPaymentMethodDefinition::class, 'sales_channel_id', 'payment_method_id'),
            (new ManyToManyIdField('payment_method_ids', 'paymentMethodIds', 'paymentMethods'))->setDescription('Unique identity of payment method.'),
            new ManyToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, SalesChannelShippingMethodDefinition::class, 'sales_channel_id', 'shipping_method_id'),
            new ManyToOneAssociationField('type', 'type_id', SalesChannelTypeDefinition::class, 'id', false),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Default language for the sales channel'),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, 'id', false),
            (new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Default currency for the sales channel'),
            (new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Default payment method for the sales channel'),
            (new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Default shipping method for the sales channel'),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Default country for the sales channel'),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'sales_channel_id', 'id'),

            new OneToManyAssociationField('customers', CustomerDefinition::class, 'sales_channel_id', 'id'),

            (new FkField('home_cms_page_id', 'homeCmsPageId', CmsPageDefinition::class))->setDescription('Unique identity of home CMS page.'),
            (new ReferenceVersionField(CmsPageDefinition::class, 'home_cms_page_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('homeCmsPage', 'home_cms_page_id', CmsPageDefinition::class, 'id', false),
            new TranslatedField('homeSlotConfig'),
            new TranslatedField('homeEnabled'),
            new TranslatedField('homeName'),
            new TranslatedField('homeMetaTitle'),
            new TranslatedField('homeMetaDescription'),
            new TranslatedField('homeKeywords'),

            (new OneToManyAssociationField('domains', SalesChannelDomainDefinition::class, 'sales_channel_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Domain URLs configured for the sales channel'),

            (new OneToManyAssociationField('systemConfigs', SystemConfigDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new ManyToOneAssociationField('navigationCategory', 'navigation_category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Root category for navigation menu'),
            (new ManyToOneAssociationField('footerCategory', 'footer_category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Root category for footer navigation'),
            (new ManyToOneAssociationField('serviceCategory', 'service_category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Root category for service pages'),
            (new OneToManyAssociationField('productVisibilities', ProductVisibilityDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToOneAssociationField('hreflangDefaultDomain', 'hreflang_default_domain_id', 'id', SalesChannelDomainDefinition::class, false))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('mailHeaderFooter', 'mail_header_footer_id', MailHeaderFooterDefinition::class, 'id', false),
            new OneToManyAssociationField('newsletterRecipients', NewsletterRecipientDefinition::class, 'sales_channel_id', 'id'),
            (new OneToManyAssociationField('numberRangeSalesChannels', NumberRangeSalesChannelDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('promotionSalesChannels', PromotionSalesChannelDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('documentBaseConfigSalesChannels', DocumentBaseConfigSalesChannelDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('productReviews', ProductReviewDefinition::class, 'sales_channel_id', 'id'),
            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('seoUrlTemplates', SeoUrlTemplateDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'sales_channel_id', 'id'),
            (new OneToManyAssociationField('salesChannelFiles', SalesChannelFileDefinition::class, 'sales_channel_id', 'id'))->addFlags(new ApiAware(AdminApiSource::class), new CascadeDelete()),
            (new OneToOneAssociationField('analytics', 'analytics_id', 'id', SalesChannelAnalyticsDefinition::class, false))->addFlags(new CascadeDelete()),
            new ManyToManyAssociationField('customerGroupsRegistrations', CustomerGroupDefinition::class, CustomerGroupRegistrationSalesChannelDefinition::class, 'sales_channel_id', 'customer_group_id', 'id', 'id'),
            new ManyToManyAssociationField('landingPages', LandingPageDefinition::class, LandingPageSalesChannelDefinition::class, 'sales_channel_id', 'landing_page_id', 'id', 'id'),
            new OneToManyAssociationField('boundCustomers', CustomerDefinition::class, 'bound_sales_channel_id', 'id'),
            (new OneToManyAssociationField('wishlists', CustomerWishlistDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
        ]);

        return $fields;
    }
}
