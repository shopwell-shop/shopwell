<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Security;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;

/**
 * The payum token of one payment attempt, stored in the database so that a gateway execution can be
 * resumed on any node of a multi instance installation.
 *
 * A token is the reference an interface redirects the shopper back to: it carries the hash that is
 * looked up on return, the order it belongs to and the urls payum has to continue with. The row is
 * written and read by {@see PaymentGatewayTokenStorage}, never through the generic entity endpoints -
 * a token is a bearer credential and must not be enumerable through the admin or store api.
 *
 * The table is deliberately *not* the `payment_token` of the checkout: that one is the id of a
 * return url jwt, is written by the raw connection of the token factory, is single use and is
 * deleted by the cleanup task once it expires. Payum keeps its token across the whole gateway
 * execution, including a notification that may arrive long after the shopper left, so the two have
 * neither the same columns nor the same lifetime - one table would let the cleanup of the checkout
 * delete the credentials a running gateway still needs.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_gateway_token', since: '6.7.18.0', collectionClass: PaymentGatewayTokenCollection::class)]
class PaymentGatewayTokenEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'payment_gateway_token';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: false)]
    public string $id;

    /**
     * The value the interface sends back. Unique, because it is the only thing that identifies the
     * payment attempt on return.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: false, maxLength: 128)]
    public string $hash;

    /**
     * The payment order the token was issued for. A token is a credential, so it must not outlive
     * what it grants access to - which is why the column is a real reference and not a loose id.
     */
    #[ForeignKey(entity: 'payment_order', api: false)]
    public ?string $orderId = null;

    #[Field(type: FieldType::STRING, api: false, maxLength: 2048)]
    public ?string $targetUrl = null;

    #[Field(type: FieldType::STRING, api: false, maxLength: 2048)]
    public ?string $afterUrl = null;

    #[Field(type: FieldType::STRING, api: false, maxLength: 64)]
    public ?string $gatewayName = null;

    #[Field(type: FieldType::DATETIME, api: false)]
    public ?\DateTimeInterface $createdAt = null;

    /**
     * The reverse side of the reference: it is what turns `order_id` into a database level constraint.
     * The token is deleted together with its order, a credential that outlives what it grants access to
     * is worse than no credential at all.
     */
    #[ManyToOne(entity: 'payment_order', api: false)]
    public ?PaymentOrderEntity $order = null;
}
