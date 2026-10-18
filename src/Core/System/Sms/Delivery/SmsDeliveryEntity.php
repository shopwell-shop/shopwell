<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Delivery;

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
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * One attempt to send a message: what went out, over which gateway and with which outcome.
 *
 * The body is deliberately not stored - neither the plain text of an international message nor the
 * parameters of an otp - so the table cannot turn into a store of plain text credentials. The number
 * is kept masked with a hash next to it, the parameters can be reconstructed from the template and
 * the log entry when an incident is investigated.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('sms_delivery', since: '6.7.18.0', collectionClass: SmsDeliveryCollection::class)]
class SmsDeliveryEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'sms_delivery';

    final public const STATUS_QUEUED = 'queued';
    final public const STATUS_SENT = 'sent';
    final public const STATUS_FAILED = 'failed';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    /**
     * Kept as a historical reference, a deleted gateway does not delete its deliveries.
     */
    #[ForeignKey(entity: 'sms_gateway', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $gatewayId = null;

    /**
     * Redundant with the gateway, so the deliveries stay groupable after the gateway was deleted.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $technicalName;

    /**
     * The number in a masked form, e.g. +86138****0000.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $phoneMasked;

    /**
     * A hash of the number for deduplication and auditing without keeping the plain number.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $phoneHash;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 2)]
    public ?string $countryCode = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $scene = null;

    #[ForeignKey(entity: 'sms_template', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $templateId = null;

    /**
     * The name of the logical template as a snapshot, so a deleted template does not make the
     * deliveries unreadable.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public ?string $templateRef = null;

    /**
     * The template id and the signature that were actually used, the first thing to look at when a
     * message did not arrive.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $templateCode = null;

    /**
     * A hash of the body, the body itself is not stored.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $contentHash = null;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $status = self::STATUS_QUEUED;

    /**
     * The id the provider handed back, the anchor to reconcile the delivery state.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $providerMessageId = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $errorCode = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 512)]
    public ?string $errorMessage = null;

    /**
     * The estimated number of billable parts of the message.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public ?int $segments = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $sentAt = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $failedAt = null;

    #[ManyToOne(entity: 'sms_gateway', api: ['admin-api' => true, 'store-api' => false])]
    public ?SmsGatewayEntity $gateway = null;

    #[ManyToOne(entity: 'sms_template', api: ['admin-api' => true, 'store-api' => false])]
    public ?SmsTemplateEntity $template = null;
}
