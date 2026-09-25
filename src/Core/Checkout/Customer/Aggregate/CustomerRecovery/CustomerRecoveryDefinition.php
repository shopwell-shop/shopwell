<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery;

use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerRecoveryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'customer_recovery';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CustomerRecoveryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CustomerRecoveryCollection::class;
    }

    public function since(): ?string
    {
        return '6.1.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CustomerDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of the customer recovery account.'),
            (new StringField('hash', 'hash'))->addFlags(new Required())->setDescription('Password hash for customer\'s account recovery.'),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required())->setDescription('Unique identity of the customer.'),
            new OneToOneAssociationField('customer', 'customer_id', 'id', CustomerDefinition::class, false),
        ]);
    }
}
