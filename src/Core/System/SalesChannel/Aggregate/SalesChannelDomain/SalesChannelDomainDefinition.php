<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain;

use Shopwell\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopwell\Core\Content\ProductExport\ProductExportDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyDefinition;
use Shopwell\Core\System\Language\LanguageDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelDefinition;
use Shopwell\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelDomainDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'sales_channel_domain';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SalesChannelDomainEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelDomainCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return SalesChannelDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of sales channel domain.'),

            (new StringField('url', 'url', 255))->addFlags(new ApiAware(), new Required())->setDescription('URL of the sales channel domain.'),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of sales channel.'),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of language used.'),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of currency.'),
            (new FkField('snippet_set_id', 'snippetSetId', SnippetSetDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of snippet set.'),
            (new MeasurementUnitsField('measurement_units', 'measurementUnits'))->addFlags(new ApiAware(), new Since('6.7.1.0')),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('snippetSet', 'snippet_set_id', SnippetSetDefinition::class, 'id', false),
            (new OneToOneAssociationField('salesChannelDefaultHreflang', 'id', 'hreflang_default_domain_id', SalesChannelDefinition::class, false))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'sales_channel_domain_id', 'id'))->addFlags(new RestrictDelete()),
            (new BoolField('hreflang_use_only_locale', 'hreflangUseOnlyLocale'))->addFlags(new ApiAware())->setDescription('This is used to toggle the language configurations, say between DE and DE-DE for instance.'),
            (new BoolField('is_external_storefront', 'isExternalStorefront'))->addFlags(new ApiAware())->setDescription('Whether the domain points to an external (headless) storefront.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
