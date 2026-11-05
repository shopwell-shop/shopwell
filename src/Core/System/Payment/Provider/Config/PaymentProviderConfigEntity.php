<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Provider\Config;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * Platform provider admission and the explicit account used by provider configuration.
 * Merchant identity and encrypted credentials live on the referenced payment account.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_provider_config', since: '6.7.18.0', collectionClass: PaymentProviderConfigCollection::class)]
class PaymentProviderConfigEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'payment_provider_config';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $providerTechnicalName;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $enabled;

    #[Required]
    #[ForeignKey(entity: 'payment_account', api: ['admin-api' => true, 'store-api' => false])]
    public string $accountId;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $settings = null;
}
