<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * The platform wide merchant credentials of a payment interface (alipay, wechat pay, …), used by the
 * apps that run in `mode` platform.
 *
 * One row per payment interface and not per channel: an alipay application covers every product of
 * alipay, so the mobile web payment and the qr code payment share the very same keys. Adding a product
 * therefore never duplicates a key, and rotating a key is a single row.
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

    /**
     * The merchant credentials of the interface, stored encrypted. They are only ever written through
     * the provider admin controller - a write through the generic entity endpoint would store them in
     * plain text.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    public ?array $credentials = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $settings = null;
}
