<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Gateway;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Sms\Delivery\SmsDeliveryEntity;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;

/**
 * One usable configuration of an sms provider: the credentials and the connection options.
 *
 * A row is what the resolver picks when a message has to go out. It carries no template information,
 * the mapping from a logical template to the template id of the provider lives in
 * sms_template_mapping, because the template codes of the Chinese providers are isolated per account.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('sms_gateway', since: '6.7.18.0', collectionClass: SmsGatewayCollection::class)]
class SmsGatewayEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'sms_gateway';

    final public const STRATEGY_EXCLUSIVE = 'exclusive';
    final public const STRATEGY_FAILOVER = 'failover';
    final public const STRATEGY_ROUND_ROBIN = 'round_robin';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    /**
     * The technical name of the provider, e.g. aliyun, twilio or null.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $technicalName;

    /**
     * A name for the administrators, e.g. "Aliyun main account".
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $label = null;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $enabled = false;

    /**
     * Null means the gateway is available in every sales channel.
     */
    #[ForeignKey(entity: 'sales_channel', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $salesChannelId = null;

    /**
     * The order of the candidates during failover, the lower the earlier.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $priority = 0;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $strategy = self::STRATEGY_EXCLUSIVE;

    /**
     * The credentials of the provider, e.g. ['accessKeyId' => '…', 'accessKeySecret' => '…'].
     *
     * The structure is declared by the credential definitions of the gateway factory, the fields a
     * factory marks as secret are rendered as password inputs by the administration.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $credentials = null;

    /**
     * Connection options like the timeout or the sandbox flag. Deliberately no template information.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $options = null;

    /**
     * @var array<string, SmsTemplateMappingEntity>|null
     */
    #[OneToMany(entity: 'sms_template_mapping', ref: 'gateway_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $templateMappings = null;

    /**
     * @var array<string, SmsDeliveryEntity>|null
     */
    #[OneToMany(entity: 'sms_delivery', ref: 'gateway_id', onDelete: OnDelete::SET_NULL, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $deliveries = null;
}
