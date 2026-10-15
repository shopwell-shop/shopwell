<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Notification;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;

/**
 * An incoming asynchronous notification of a channel. The raw body is stored before it is processed
 * so that a notification is never lost and can be replayed.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_notification', since: '6.7.18.0', collectionClass: PaymentNotificationCollection::class)]
class PaymentNotificationEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const RESULT_OK = 'ok';
    final public const RESULT_DUPLICATE = 'duplicate';
    final public const RESULT_IGNORED = 'ignored';
    final public const RESULT_INVALID_SIGNATURE = 'invalid_signature';
    final public const RESULT_UNMATCHED = 'unmatched';
    final public const RESULT_AMOUNT_MISMATCH = 'amount_mismatch';
    final public const RESULT_ERROR = 'error';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[ForeignKey(entity: 'payment_order', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $paymentOrderId = null;

    /**
     * The payment interface that sent the notification: it is the interface, not the channel, that
     * signs a notification and therefore decides which credentials verify it.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $providerTechnicalName;

    #[Field(type: FieldType::TEXT, api: ['admin-api' => true, 'store-api' => false])]
    public ?string $rawBody = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $headers = null;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $signatureValid = false;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $processed = false;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public ?string $processResult = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 512)]
    public ?string $errorMessage = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 45)]
    public ?string $remoteIp = null;

    /**
     * The notification may arrive before the payment order is known (the reference is nullable), the
     * cascade is declared on the reverse side in PaymentOrderEntity.
     */
    #[ManyToOne(entity: 'payment_order', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentOrderEntity $paymentOrder = null;
}
