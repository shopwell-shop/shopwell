<?php declare(strict_types=1);

namespace Shopwell\Core\System\Country\Aggregate\CountryCity;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryCityTranslation\CountryCityTranslationDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryDistrict\CountryDistrictDefinition;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopwell\Core\System\Country\CountryDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
class CountryCityDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'country_city';

    public const MAX_LENGTH_CODE = 12;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CountryCityCollection::class;
    }

    public function getEntityClass(): string
    {
        return CountryCityEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.17.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CountryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the country\'s city.'),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the country.'),
            (new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the country\'s state the city belongs to.'),
            (new StringField('code', 'code', self::MAX_LENGTH_CODE))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Official administrative division code of the city.'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('Numerical value that indicates the order in which the defined cities must be displayed in the frontend.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the city is available for selection in the storefront.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false),
            (new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(CountryCityTranslationDefinition::class, 'country_city_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('districts', CountryDistrictDefinition::class, 'country_city_id', 'id'))->addFlags(new SetNullOnDelete()),
            // Reverse Associations, not available in sales-channel-api
            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'country_city_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'country_city_id', 'id'))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
