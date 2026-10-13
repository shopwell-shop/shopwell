<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\DataAbstractionLayer;

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

/**
 * Which payment interface an app may use, and whose merchant account is charged.
 *
 * This is the authorization of an app for a whole interface - `mode` decides whether the platform
 * credentials or the app's own ones are used. Which channels of that interface the app offers to its
 * own users is a separate decision, see {@see PaymentAppChannelEntity}.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_app_provider', since: '6.7.18.0', collectionClass: PaymentAppProviderCollection::class)]
class PaymentAppProviderEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'payment_app_provider';

    /**
     * The payment is charged on the merchant account of the platform.
     */
    final public const MODE_PLATFORM = 'platform';

    /**
     * The payment is charged on the merchant account of the app, e.g. its own alipay application.
     */
    final public const MODE_OWN = 'own';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public string $paymentAppId;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $providerTechnicalName;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $enabled;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $mode = self::MODE_PLATFORM;

    /**
     * The merchant credentials of the app for this interface, stored encrypted. Only used when `mode`
     * is `own`. See the app secret: they are only ever written through the app admin controller.
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

    /**
     * The app is deleted together with its interface authorizations, the cascade is declared on the
     * reverse side in PaymentAppEntity.
     */
    #[ManyToOne(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAppEntity $paymentApp = null;
}
