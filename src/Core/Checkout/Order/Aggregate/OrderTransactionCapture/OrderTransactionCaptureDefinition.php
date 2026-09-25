<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class OrderTransactionCaptureDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'order_transaction_capture';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.4.12.0';
    }

    public function getEntityClass(): string
    {
        return OrderTransactionCaptureEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OrderTransactionCaptureCollection::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderTransactionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of order transaction capture.'),
            (new VersionField())->addFlags(new ApiAware()),
            (new FkField('order_transaction_id', 'orderTransactionId', OrderTransactionDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of order transaction.'),
            (new ReferenceVersionField(OrderTransactionDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new StateMachineStateField('state_id', 'stateId', OrderTransactionCaptureStates::STATE_MACHINE))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of order state.'),
            (new ManyToOneAssociationField('stateMachineState', 'state_id', StateMachineStateDefinition::class, 'id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('transaction', 'order_transaction_id', OrderTransactionDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('refunds', OrderTransactionCaptureRefundDefinition::class, 'capture_id'))->addFlags(new ApiAware(), new CascadeDelete()),

            (new StringField('external_reference', 'externalReference'))->addFlags(new ApiAware())->setDescription('External payment provider token.'),
            (new CalculatedPriceField('amount', 'amount'))->addFlags(new ApiAware(), new Required())->setDescription('Number of items of each product.'),
            (new CustomFields())->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
