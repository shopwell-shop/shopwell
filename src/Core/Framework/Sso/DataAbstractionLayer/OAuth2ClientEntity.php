<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\CustomFields;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;

/**
 * The admin managed OAuth2 client of one channel, e.g. the google or the wechat client.
 *
 * The available channels come from the OAuth2ChannelRegistry, this entity only stores which of
 * them are enabled and with which credentials, so that adding a channel that
 * oauth2-client-bundle ships does not need a migration.
 *
 * The credentials and the settings of a channel are admin api aware only, the storefront has no
 * business reading them. Only the custom fields stay on the default of the framework, which
 * whatever the platform adds there is expected to be readable through the store api.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('oauth2_client', since: '6.7.18.0', collectionClass: OAuth2ClientCollection::class)]
class OAuth2ClientEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    /**
     * The technical name of the channel, unique per shop.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $technicalName;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $enabled;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $clientId = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 255)]
    public ?string $clientSecret = null;

    /**
     * Scopes requested during authorization, the channel defaults are used when empty.
     *
     * @var list<string>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $scopes = null;

    /**
     * Further options of the channel written in the notation of the bundle configuration tree, e.g.
     * `access_type` for the google configurator.
     *
     * @var array<string, mixed>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $settings = null;

    /**
     * Not translated on purpose, the credentials of a channel are the same in every language, so
     * there is nothing to translate and no `oauth2_client_translation` table to keep in sync.
     *
     * @var array<mixed>|null
     */
    #[CustomFields]
    protected ?array $customFields = null;
}
