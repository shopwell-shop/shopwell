<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount;

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
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;

/**
 * An explicit grant allowing an app to collect through one merchant account.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_app_account', since: '6.7.18.0', collectionClass: PaymentAppAccountCollection::class)]
class PaymentAppAccountEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'payment_app_account';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentAppId;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $enabled;

    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public int $position;

    #[Required]
    #[ForeignKey(entity: 'payment_account', api: ['admin-api' => true, 'store-api' => false])]
    public string $accountId;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $settings = null;

    /**
     * The app is deleted together with its interface authorizations, the cascade is declared on the
     * reverse side in PaymentAppEntity.
     */
    #[ManyToOne(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAppEntity $paymentApp = null;

    #[ManyToOne(entity: 'payment_account', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAccountEntity $account = null;
}
