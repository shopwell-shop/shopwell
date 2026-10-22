<?php declare(strict_types=1);

namespace Shopwell\Core\System\Organization\Aggregate\OrganizationTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Organization\OrganizationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class OrganizationTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'organization_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrganizationTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrganizationTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return OrganizationDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
