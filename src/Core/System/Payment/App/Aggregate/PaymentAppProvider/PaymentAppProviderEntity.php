<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\App\Aggregate\PaymentAppProvider;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;

/**
 * Which payment interface an app may use, and whose merchant account is charged.
 *
 * This is the authorization of an app for a whole interface. The account behind it is decided by
 * `sourceAppId`:
 *
 *  - `null`: the app collects on its own merchant account, the credentials in `credentials` are used,
 *  - the id of another app: the app inherits the account of that app for this very interface, and the
 *    credentials of this row are ignored. Pointing at the tenant of the platform (an app of type
 *    `platform`) is the "the platform collects for me" case, whose credentials live in the platform
 *    level `payment_provider_config`.
 *
 * Inheritance is resolved along the chain, so an app may inherit from an app that inherits again; the
 * resolver refuses circles and stops at the tenant of the platform, see
 * {@see \Shopwell\Core\System\Payment\Provider\PaymentConfigResolver}.
 *
 * Which channels of that interface the app offers to its own users is a separate decision, see
 * {@see PaymentAppChannelEntity}.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('payment_app_provider', since: '6.7.18.0', collectionClass: PaymentAppProviderCollection::class)]
class PaymentAppProviderEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'payment_app_provider';

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

    /**
     * The app whose merchant account is charged for this interface, `null` for the app's own one.
     *
     * A deleted source app does not take the inheriting authorization with it - the column is set to
     * `null` then, which turns the inheriting app back into "collects on its own account". That row
     * has no credentials of its own, so the payment is refused with "provider not configured"
     * instead of being collected on somebody else's account.
     */
    #[ForeignKey(entity: 'payment_app', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $sourceAppId = null;

    /**
     * The merchant credentials of the app for this interface, stored encrypted. Only used when
     * `sourceAppId` is null - an inherited account ignores them. See the app secret: they are only
     * ever written through the app admin controller.
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

    /**
     * The app this authorization inherits its account from, `null` when the app brings its own one.
     *
     * The deletion of the source app only drops the inheritance - the authorization itself stays, so
     * the merchant keeps the interface and only has to fill in credentials again.
     */
    #[ManyToOne(entity: 'payment_app', onDelete: OnDelete::SET_NULL, api: ['admin-api' => true, 'store-api' => false])]
    public ?PaymentAppEntity $sourceApp = null;
}
