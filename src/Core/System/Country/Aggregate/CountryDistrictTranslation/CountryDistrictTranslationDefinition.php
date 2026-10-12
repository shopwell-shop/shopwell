<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Aggregate\CountryDistrictTranslation;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class CountryDistrictTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'country_district_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CountryDistrictTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return CountryDistrictTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.17.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return CountryDistrictDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
