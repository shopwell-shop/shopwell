<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\DataAbstractionLayer;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;

/**
 * A logical template the business code refers to, e.g. customer.otp.login.
 *
 * It holds the parameter contract that is validated before a message is built and - for the
 * free text mode of the international providers - the body with its placeholders. For the report
 * mode of the Chinese providers the body is owned by the provider, here only the reference is kept
 * in the sms_template_mapping.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[Entity('sms_template', since: '6.7.18.0', collectionClass: SmsTemplateCollection::class)]
class SmsTemplateEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'sms_template';

    final public const SCENE_OTP = 'otp';
    final public const SCENE_TRANSACTION = 'transaction';
    final public const SCENE_MARKETING = 'marketing';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    /**
     * The name the business code uses, e.g. customer.otp.login, unique per installation.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 128)]
    public string $technicalName;

    /**
     * Decides the queue priority and the rate limit, one of the SCENE_* constants.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public string $scene;

    /**
     * The body with `{{ param }}` placeholders, used by the free text mode only. It stays empty for
     * the providers that require a reported template.
     *
     * The body is translated, like the one of a mail template: a message goes out in the language of
     * the context it was sent in. The property stays null for a language that has no body of its own;
     * the body of the default language stays reachable as the translated value, and that is what the
     * renderer falls back to.
     */
    #[Field(type: FieldType::TEXT, translated: true, api: ['admin-api' => true, 'store-api' => false])]
    public ?string $content = null;

    /**
     * The parameter contract, a list of [{name, required, type, pattern, default}].
     *
     * @var list<array<string, mixed>>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $paramsSchema = null;

    /**
     * The upper bound of the rendered body in characters, longer messages are rejected before a
     * provider is called.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public ?int $maxLength = null;

    /**
     * The business validity of the template, e.g. the lifetime of an otp. It does not take part in
     * sending, the caller decides what to do with it.
     */
    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => false])]
    public ?int $validitySeconds = null;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public bool $enabled = true;

    /**
     * The bodies of the template per language, only the content of the template is translated - the
     * parameter contract and the scene are the same in every language.
     *
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    /**
     * @var array<string, SmsTemplateMappingEntity>|null
     */
    #[OneToMany(entity: 'sms_template_mapping', ref: 'template_id', onDelete: OnDelete::CASCADE, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $mappings = null;

    /**
     * @var array<string, SmsDeliveryEntity>|null
     */
    #[OneToMany(entity: 'sms_delivery', ref: 'template_id', onDelete: OnDelete::SET_NULL, api: ['admin-api' => true, 'store-api' => false])]
    public ?array $deliveries = null;
}
