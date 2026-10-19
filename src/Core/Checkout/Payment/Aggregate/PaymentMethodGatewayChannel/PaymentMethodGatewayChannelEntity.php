<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel;

use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * The gateway channel a checkout payment method collects through, e.g. `alipay` / `alipay_h5`.
 *
 * It is the edge between the two halves of the platform that have no other connection: a
 * `payment_method` is what the shopper picks in the storefront, a channel is what the gateway knows
 * how to talk to. The binding is one to one in both directions and both directions are enforced by
 * the database, not by the admin - a second channel on the same method and the same channel on a
 * second method are both configuration errors that would silently charge the wrong account.
 *
 * Only redirect and qr code channels may be bound: a channel that is pulled up by an sdk belongs to
 * an app, which renders it itself.
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
#[Entity('payment_method_gateway_channel', since: '6.7.18.0', collectionClass: PaymentMethodGatewayChannelCollection::class)]
class PaymentMethodGatewayChannelEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'payment_method_gateway_channel';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    /**
     * The payment method the shopper picks. Unique, because one method collects through exactly one
     * channel.
     */
    #[Required]
    #[ForeignKey(entity: 'payment_method', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentMethodId;

    /**
     * The interface that is charged, e.g. `alipay`.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $providerTechnicalName;

    /**
     * The channel of that interface, e.g. `alipay_h5`. Unique together with the interface, because a
     * channel that two payment methods point at would make the shopper's choice meaningless.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $channelTechnicalName;

    /**
     * The binding does not outlive the payment method, the cascade is declared here and not left to
     * the reverse side: a dangling binding would make the next method with the same id collect
     * through a channel nobody configured.
     */
    #[ManyToOne(entity: 'payment_method', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentMethodEntity $paymentMethod = null;
}
