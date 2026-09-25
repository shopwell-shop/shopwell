<?php declare(strict_types=1);

namespace Shopwell\Core\System\Tax\Aggregate\TaxRule;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\CountryDefinition;
use Shopwell\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeDefinition;
use Shopwell\Core\System\Tax\TaxDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class TaxRuleDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'tax_rule';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TaxRuleCollection::class;
    }

    public function getEntityClass(): string
    {
        return TaxRuleEntity::class;
    }

    public function since(): ?string
    {
        return '6.1.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of tax rule.'),
            (new FkField('tax_rule_type_id', 'taxRuleTypeId', TaxRuleTypeDefinition::class))->addFlags(new Required())->setDescription('Unique identity of tax rule type.'),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new Required())->setDescription('Unique identity of country.'),
            (new FloatField('tax_rate', 'taxRate'))->addFlags(new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Rate of tax defined for a tax rule.'),
            (new JsonField('data', 'data', [
                new ListField('states', 'states'),
                new StringField('zipCode', 'zipCode'),
                new StringField('fromZipCode', 'fromZipCode'),
                new StringField('toZipCode', 'toZipCode'),
            ]))->setDescription('Parameter that designates to which zip code the tax rule is applicable.'),
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->addFlags(new Required())->setDescription('Unique identity of tax.'),
            (new DateTimeField('active_from', 'activeFrom'))->setDescription('Date and time when the tax rule is enabled.'),
            new ManyToOneAssociationField('type', 'tax_rule_type_id', TaxRuleTypeDefinition::class, 'id'),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id'),
            new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, 'id'),
        ]);
    }
}
