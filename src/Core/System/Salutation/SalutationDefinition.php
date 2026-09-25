<?php declare(strict_types=1);

namespace Shopwell\Core\System\Salutation;

use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class SalutationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'salutation';

    final public const NOT_SPECIFIED = 'not_specified';

    final public const MR = 'mr';

    final public const MRS = 'mrs';

    final public const DEFAULT_POSITION = 100;

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalutationCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalutationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'position' => self::DEFAULT_POSITION,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of salutation.'),
            (new StringField('salutation_key', 'salutationKey'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Technical name given to salutation. For example: mr'),
            (new TranslatedField('displayName'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('letterName'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new IntField('position', 'position'))->addFlags(new ApiAware())->setDescription('Numerical value that indicates the order in which the defined salutations must be displayed in the frontend.'),

            (new TranslationsAssociationField(SalutationTranslationDefinition::class, 'salutation_id'))->addFlags(new Required()),

            // Reverse Associations, not available in sales-channel-api
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'salutation_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('customerAddresses', CustomerAddressDefinition::class, 'salutation_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('orderCustomers', OrderCustomerDefinition::class, 'salutation_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('orderAddresses', OrderAddressDefinition::class, 'salutation_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('newsletterRecipients', NewsletterRecipientDefinition::class, 'salutation_id', 'id'))->addFlags(new SetNullOnDelete()),
        ]);
    }
}
