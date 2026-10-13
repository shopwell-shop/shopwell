<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\DataAbstractionLayer;

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

/**
 * An external app that is allowed to create payment orders through the generic payment gateway.
 *
 * The credentials are handed over as request parameters (app_id, timestamp, nonce and sign on the
 * top level of the payload), the secret is stored encrypted.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_app', since: '6.7.18.0', collectionClass: PaymentAppCollection::class)]
class PaymentAppEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

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
     * The secret the app signs its requests with, stored encrypted. It deliberately stays out of the
     * generic entity endpoints: writing it there would store it in plain text, so the app admin
     * controller is the only way in.
     */
    #[Field(type: FieldType::STRING, api: false)]
    public string $appSecret;

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
