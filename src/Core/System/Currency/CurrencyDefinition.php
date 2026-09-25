<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency;

use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceDefinition;
use Shopwell\Core\Content\ProductExport\ProductExportDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Shopwell\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@framework')]
class CurrencyDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'currency';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CurrencyCollection::class;
    }

    public function getEntityClass(): string
    {
        return CurrencyEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of currency.'),
            (new FloatField('factor', 'factor'))->addFlags(new ApiAware(), new Required())->setDescription('Currency exchange rate.'),
            (new StringField('symbol', 'symbol'))->addFlags(new ApiAware(), new Required())->setDescription('A currency symbol is a graphical representation used as shorthand for a currency\'s name, for example US Dollar - $'),
            (new StringField('iso_code', 'isoCode', 3))->addFlags(new ApiAware(), new Required())->setDescription('Standard international three digit code to represent currency. For example, USD.'),
            (new TranslatedField('shortName'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('The order of the tabs for multiple currencies defined.'),
            (new BoolField('is_system_default', 'isSystemDefault'))->addFlags(new ApiAware(), new Runtime()),
            (new FloatField('tax_free_from', 'taxFreeFrom'))->addFlags(new ApiAware())->setDescription('The value from which the tax must be exempted.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(CurrencyTranslationDefinition::class, 'currency_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelCurrencyDefinition::class, 'currency_id', 'sales_channel_id'),
            (new OneToManyAssociationField('salesChannelDomains', SalesChannelDomainDefinition::class, 'currency_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('promotionDiscountPrices', PromotionDiscountPriceDefinition::class, 'currency_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete()),
            (new CashRoundingConfigField('item_rounding', 'itemRounding'))->addFlags(new ApiAware(), new Required())->setDescription('The cash rounding applied on net prices.'),
            (new CashRoundingConfigField('total_rounding', 'totalRounding'))->addFlags(new ApiAware(), new Required())->setDescription('The cash rounding applied on cart’s total net prices.'),
            (new OneToManyAssociationField('countryRoundings', CurrencyCountryRoundingDefinition::class, 'currency_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
