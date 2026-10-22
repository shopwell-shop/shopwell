<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnLineItem;

use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ReferenceVersion;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * One position of an return: which order position goes back, how many of it and for how much.
 *
 * The position points into the line item of the order instead of copying the label and the price -
 * the order is what the customer bought, and the return is only a claim against it. The amount is
 * repeated here on purpose: a partial return of a position may refund less than the proportional
 * price, and a shop has to be able to decide that per position.
 *
 * `restocked` remembers whether the goods were booked back into the stock, so a repeated reception
 * never doubles the stock.
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[Entity('order_return_line_item', since: '6.7.18.0', collectionClass: OrderReturnLineItemCollection::class)]
class OrderReturnLineItemEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'order_return_line_item';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => true])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'order_return', api: ['admin-api' => true, 'store-api' => false])]
    public string $orderReturnId;

    #[Required]
    #[ForeignKey(entity: 'order_line_item', api: ['admin-api' => true, 'store-api' => true])]
    public string $orderLineItemId;

    #[ReferenceVersion(entity: 'order_line_item')]
    public string $orderLineItemVersionId;

    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => true])]
    public int $quantity;

    /**
     * The amount that goes back for this position, in the smallest unit of the currency.
     */
    #[Required]
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => true])]
    public int $refundAmount = 0;

    /**
     * Whether the goods of this position were booked back into the stock.
     *
     * The dal derives the required flag from the php type, and a required field that the payload
     * does not carry is written as null instead of falling back to the ddl default - so every
     * writer has to spell `restocked` out, exactly like OrderReturnService::create() does.
     */
    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $restocked = false;

    /**
     * Whether the shop accepted the goods of this position. A position may come back damaged, so the
     * decision is per position and not per return.
     *
     * Required by the dal for the same reason as `restocked`, see above.
     */
    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $accepted = true;

    /**
     * The position belongs to its return, the cascade is declared on the reverse side in
     * OrderReturnEntity, so the many to one side stays without a delete flag.
     */
    #[ManyToOne(entity: 'order_return', api: ['admin-api' => true, 'store-api' => false])]
    public ?OrderReturnEntity $orderReturn = null;

    #[ManyToOne(entity: 'order_line_item', api: ['admin-api' => true, 'store-api' => true])]
    public ?OrderLineItemEntity $orderLineItem = null;
}
