<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider\PaymentAppProviderEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Webhook\PaymentWebhookDeliveryEntity;

/**
 * A tenant that collects through the generic payment gateway.
 *
 * An external app creates its payment orders through the api: the credentials are handed over as
 * request parameters (app_id, timestamp, nonce and sign on the top level of the payload), the secret
 * is stored encrypted. The shop itself is a tenant as well, of type platform - it collects for the
 * checkout, is seeded by the migration and can never be authenticated.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_app', since: '6.7.18.0', collectionClass: PaymentAppCollection::class)]
class PaymentAppEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    /**
     * An app that calls the payment api and signs its requests.
     */
    final public const TYPE_EXTERNAL = 'external';

    /**
     * The tenant of the shop itself: it collects for the checkout and is never authenticated.
     */
    final public const TYPE_PLATFORM = 'platform';

    /**
     * The technical name of that tenant, and the single source of it: the payment migration seeds the
     * row with this name, the checkout looks it up by it. It is deliberately the same literal as
     * {@see self::TYPE_PLATFORM}, because both answer the question "which tenant is the shop itself".
     */
    final public const PLATFORM_TECHNICAL_NAME = 'platform';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    #[SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)]
    public string $technicalName;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false])]
    #[SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)]
    public string $name;

    /**
     * What kind of tenant this is. The type is the only thing that keeps the tenant of the shop out of
     * the payment api: it is checked before anything else when a caller is authenticated, and no
     * request is ever accepted for it - see {@see PaymentAppAuthenticator}.
     *
     * It deliberately has no initial value. The dal never writes the initial value of a property into
     * the insert, only what the caller passes, so a `= self::TYPE_EXTERNAL` here would look like a
     * default while every writer still had to spell the value out - and the one writer that forgot
     * would fail with a `NotBlank` instead of silently creating an app of the wrong kind. Both writers
     * (the provisioner and the migration) therefore set it explicitly.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 32)]
    public string $type;

    /**
     * The secret the app signs its requests with, stored encrypted. It deliberately stays out of the
     * generic entity endpoints: writing it there would store it in plain text, so the app admin
     * controller is the only way in.
     */
    #[Field(type: FieldType::STRING, api: false)]
    public string $appSecret;

    /**
     * The secret the app had before its last rotation, stored encrypted the same way. The platform
     * accepts it for a grace window after the rotation, see
     * {@see PaymentAppAuthenticator::SECRET_ROTATION_GRACE} - it never leaves the server either.
     */
    #[Field(type: FieldType::STRING, api: false)]
    public ?string $previousAppSecret = null;

    /**
     * The moment the secret was rotated last, which is what the grace window is counted from.
     */
    #[Field(type: FieldType::DATETIME, api: ['admin-api' => true, 'store-api' => false])]
    public ?\DateTimeInterface $secretRotatedAt = null;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $active;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 1024)]
    public ?string $notifyUrl = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 1024)]
    public ?string $returnUrl = null;

    /**
     * @var list<string>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $allowedIps = null;

    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public ?int $rateLimit = null;

    /**
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $settings = null;

    /**
     * The payment interfaces the app is authorized for, one row per interface. The row carries the mode
     * - platform or own credentials - and the credentials of the app when it brings its own ones.
     *
     * @var array<string, PaymentAppProviderEntity>|null
     */
    #[OneToMany(entity: 'payment_app_provider', ref: 'payment_app_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $providers = null;

    /**
     * The payment channels the app offers to its own users, one row per channel. A channel may only be
     * enabled for an interface the app is authorized for.
     *
     * @var array<string, PaymentAppChannelEntity>|null
     */
    #[OneToMany(entity: 'payment_app_channel', ref: 'payment_app_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $channels = null;

    /**
     * The payment orders created by the app. They are the bookkeeping of the platform and are never
     * deleted implicitly, therefore the deletion of an app is restricted as long as orders exist.
     *
     * @var array<string, PaymentOrderEntity>|null
     */
    #[OneToMany(entity: 'payment_order', ref: 'payment_app_id', onDelete: OnDelete::RESTRICT, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $paymentOrders = null;

    /**
     * @var array<string, PaymentWebhookDeliveryEntity>|null
     */
    #[OneToMany(entity: 'payment_webhook_delivery', ref: 'payment_app_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $webhookDeliveries = null;
}
