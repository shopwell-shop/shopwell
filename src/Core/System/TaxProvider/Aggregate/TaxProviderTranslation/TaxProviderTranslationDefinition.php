<?php declare(strict_types=1);

namespace Shopwell\Core\System\TaxProvider\Aggregate\TaxProviderTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\TaxProvider\TaxProviderDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class TaxProviderTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'tax_provider_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return TaxProviderTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return TaxProviderTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.5.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return TaxProviderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
