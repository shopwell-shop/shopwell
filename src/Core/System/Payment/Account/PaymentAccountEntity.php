<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Account;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Protection;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;

/**
 * A merchant identity. Orders retain this identity while its credentials can rotate.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_account', since: '6.7.18.0', collectionClass: PaymentAccountCollection::class)]
class PaymentAccountEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    public string $providerTechnicalName;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false])]
    public string $name;

    #[ForeignKey(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    public ?string $ownerAppId = null;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    public bool $active;

    /**
     * @var list<string>
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    public array $enabledProducts = [];

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    public ?array $credentials = null;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    public ?array $productParams = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: false)]
    #[Protection(write: [Protection::SYSTEM_SCOPE])]
    public ?array $settings = null;

    #[ManyToOne(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAppEntity $ownerApp = null;
}
