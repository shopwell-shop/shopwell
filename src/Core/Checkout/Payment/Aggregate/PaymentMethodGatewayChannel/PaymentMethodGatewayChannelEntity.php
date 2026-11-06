<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel;

use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;

/**
 * A checkout payment method chooses a global gateway method and a routing policy for one app.
 * Product and merchant account are selected only when the payment context is known.
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
     * The app whose merchant account the payment is collected on - the tenant of the platform for the
     * shop's own checkout, an onboarded app for a payment method that collects on its own account.
     * Unique together with the channel: the same pair may not collect for two payment methods.
     */
    #[Required]
    #[ForeignKey(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentAppId;

    /**
     * The channel of that app, e.g. `alipay_h5`. The interface it belongs to is derived from it and is
     * therefore not stored.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $methodTechnicalName;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $routingPolicy = 'priority';

    /**
     * The binding does not outlive the payment method, the cascade is declared here and not left to
     * the reverse side: a dangling binding would make the next method with the same id collect
     * through a channel nobody configured.
     */
    #[ManyToOne(entity: 'payment_method', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentMethodEntity $paymentMethod = null;

    /**
     * The app that is charged. Deleted with its app: an app that is gone cannot collect anymore, and a
     * binding that points at it would be a payment method that fails on selection.
     */
    #[ManyToOne(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAppEntity $paymentApp = null;
}
