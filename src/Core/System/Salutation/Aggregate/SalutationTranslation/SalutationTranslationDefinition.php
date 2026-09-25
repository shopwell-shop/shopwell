<?php declare(strict_types=1);

namespace Shopwell\Core\System\Salutation\Aggregate\SalutationTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Salutation\SalutationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class SalutationTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'salutation_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalutationTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalutationTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return SalutationDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('display_name', 'displayName'))->addFlags(new ApiAware(), new Required()),
            (new StringField('letter_name', 'letterName'))->addFlags(new ApiAware(), new Required()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
