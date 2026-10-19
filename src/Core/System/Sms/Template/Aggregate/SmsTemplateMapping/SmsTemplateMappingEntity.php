<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping;

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
use Shopwell\Core\System\Sms\Gateway\Audit\TemplateApprovalStatus;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayEntity;
use Shopwell\Core\System\Sms\Template\SmsTemplateEntity;

/**
 * Lands a logical template on the concrete template of a provider, e.g. customer.otp.login becomes
 * SMS_123456789 of an aliyun account.
 *
 * The mapping is a table of its own instead of a json field of the gateway because the template code
 * of the Chinese providers is isolated per account, because a logical template maps to many gateways
 * and because the audit state of the provider has to be kept and checked before sending.
 *
 * A null gateway_id means the mapping holds for every account of the provider, which the providers
 * with a shared template pool need.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('sms_template_mapping', since: '6.7.18.0', collectionClass: SmsTemplateMappingCollection::class)]
class SmsTemplateMappingEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'sms_template_mapping';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'sms_template', api: ['admin-api' => true, 'store-api' => false])]
    public string $templateId;

    /**
     * The technical name of the provider, the mapping of a provider account of the same type is a
     * separate row.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $technicalName;

    /**
     * Null means the mapping is valid for every account of the provider.
     */
    #[ForeignKey(entity: 'sms_gateway', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $gatewayId = null;

    /**
     * The template id at the provider, e.g. SMS_123456789.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $templateCode = null;

    /**
     * The signature of the message. It lives here and not on the gateway because one account can own
     * several signatures.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $signName = null;

    /**
     * Maps the parameter names of the template to the ones of the provider, only needed when they
     * differ, e.g. ['code' => 'code', 'ttl' => 'minutes'].
     *
     * @var array<string, string>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $paramsMap = null;

    /**
     * The locally kept snapshot of the audit state of the provider, one of the values of
     * TemplateApprovalStatus. A message is only sent over a mapping that is approved.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $auditStatus = TemplateApprovalStatus::PENDING;

    /**
     * The body that was handed to the provider for the audit. It is a snapshot for the reconciliation
     * command and never takes part in building a message.
     */
    #[Field(type: FieldType::TEXT, api: ['admin-api' => true, 'store-api' => false])]
    public ?string $reportedContent = null;

    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $reportedAt = null;

    #[ManyToOne(entity: 'sms_template', api: ['admin-api' => true, 'store-api' => false])]
    public ?SmsTemplateEntity $template = null;

    #[ManyToOne(entity: 'sms_gateway', api: ['admin-api' => true, 'store-api' => false])]
    public ?SmsGatewayEntity $gateway = null;
}
