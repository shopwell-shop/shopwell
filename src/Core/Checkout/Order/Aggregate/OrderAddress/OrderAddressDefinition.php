<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderAddress;

use Shopwell\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopwell\Core\System\Country\CountryDefinition;
use Shopwell\Core\System\Salutation\SalutationDefinition;

#[Package('checkout')]
class OrderAddressDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'order_address';

    public const MAX_LENGTH_FIRST_NAME = 255;

    public const MAX_LENGTH_LAST_NAME = 255;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderAddressCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderAddressEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of order\'s address.'),
            (new VersionField())->addFlags(new ApiAware()),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of country.'),
            (new FkField('country_state_id', 'countryStateId', CountryStateDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of state.'),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required())->setDescription('Unique identity of order.'),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),

            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->setDescription('Unique identity of salutation.'),
            (new StringField('first_name', 'firstName', self::MAX_LENGTH_FIRST_NAME))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING))->setDescription('First name of the customer.'),
            (new StringField('last_name', 'lastName', self::MAX_LENGTH_LAST_NAME))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Last name of the customer.'),
            (new StringField('street', 'street'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Street address'),
            (new StringField('zipcode', 'zipcode'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Zip code of the country.'),
            (new StringField('city', 'city'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Name of the city.'),
            (new StringField('company', 'company'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Name of the company.'),
            (new StringField('department', 'department'))->addFlags(new ApiAware())->setDescription('Name of the department.'),
            (new StringField('title', 'title'))->addFlags(new ApiAware())->setDescription('Title name given to customer like DR. , Prof., etc.'),
            (new StringField('phone_number', 'phoneNumber'))->addFlags(new ApiAware())->setDescription('Phone number of the customer.'),
            (new StringField('additional_address_line1', 'additionalAddressLine1'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Additional address input if necessary.'),
            (new StringField('additional_address_line2', 'additionalAddressLine2'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING))->setDescription('Additional address input if necessary.'),
            (new StringField('hash', 'hash'))->addFlags(new ApiAware(), new Runtime()),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('countryState', 'country_state_id', CountryStateDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false))->addFlags(new RestrictDelete()),
            // We need to cascade delete the order deliveries, because when deleting an order, the cascade delete will be triggered first
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_order_address_id', 'id'))->addFlags(new CascadeDelete()),
            (new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, 'id', false))->addFlags(new ApiAware()),
        ]);

        if (!Feature::isActive('v6.8.0.0')) {
            $fields->add(
                (new StringField('vat_id', 'vatId'))->addFlags(new ApiAware(), new Deprecated('v6.7.6.0', 'v6.8.0.0'))->setDescription('Unique identity of VAT.'),
            );
        } else {
            $fields->add(
                new OneToOneAssociationField('billingAddressOrder', 'id', 'billing_address_id', OrderDefinition::class, false)
            );
        }

        return $fields;
    }
}
