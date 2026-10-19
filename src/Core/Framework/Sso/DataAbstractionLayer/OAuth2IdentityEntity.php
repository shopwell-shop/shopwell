<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * Binds one external identity, i.e. a channel plus the user id of the platform, to a customer or an
 * admin user. Exactly one of both is set.
 *
 * The channel is referenced by its technical name instead of a foreign key, so removing and
 * recreating the client configuration of a channel keeps the bindings of its users.
 *
 * The customer and the admin user are referenced by a plain foreign key instead of an association:
 * an association would need `customer` and `user` to declare the reverse side, which would make the
 * framework extend the checkout and the system layer for a relation nothing reads through the DAL -
 * the login only ever filters by `customer_id` / `user_id`. The foreign keys still cascade on the
 * database level, so deleting an account removes its bindings.
 *
 * Every field is admin api aware only, the bindings are never read through the Store API.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('oauth2_identity', since: '6.7.18.0', collectionClass: OAuth2IdentityCollection::class)]
class OAuth2IdentityEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $technicalName;

    /**
     * The user id the platform uses for this account, e.g. the `sub` of Google.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public string $providerUserId;

    #[ForeignKey(entity: 'customer', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $customerId = null;

    #[ForeignKey(entity: 'user', api: ['admin-api' => true, 'store-api' => false])]
    public ?string $userId = null;
}
