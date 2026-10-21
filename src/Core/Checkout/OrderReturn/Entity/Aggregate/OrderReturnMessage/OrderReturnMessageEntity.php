<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnMessage;

use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\Log\Package;

/**
 * One entry of the conversation of an return.
 *
 * Everything that happens to an return is visible in its state and its dates, but the "why" of a
 * single decision is only in what people wrote to each other. The messages keep that, together with
 * the attachments a customer uploaded and the author, so a dispute can be answered later without
 * asking either side again.
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[Entity('order_return_message', since: '6.7.18.0', collectionClass: OrderReturnMessageCollection::class)]
class OrderReturnMessageEntity extends EntityStruct
{
    final public const ENTITY_NAME = 'order_return_message';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => true])]
    public string $id;

    #[Required]
    #[ForeignKey(entity: 'order_return', api: ['admin-api' => true, 'store-api' => false])]
    public string $orderReturnId;

    /**
     * One of {@see OrderReturnEntity::AUTHOR_CUSTOMER}, `agent` or `system`.
     */
    #[Required]
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => true], maxLength: 16)]
    public string $authorRole;

    /**
     * The customer or the administrator who wrote it, empty for a system entry and for a guest.
     */
    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false], maxLength: 64)]
    public ?string $authorId = null;

    #[Required]
    #[Field(type: FieldType::TEXT, api: ['admin-api' => true, 'store-api' => true])]
    public string $content;

    /**
     * The uploaded files, a list of media ids or urls as handed in by the storefront.
     *
     * @var list<string>|null
     */
    #[Field(type: FieldType::JSON, api: ['admin-api' => true, 'store-api' => true])]
    public ?array $attachments = null;

    /**
     * The message belongs to its return, the cascade is declared on the reverse side in
     * OrderReturnEntity, so the many to one side stays without a delete flag.
     */
    #[ManyToOne(entity: 'order_return', api: ['admin-api' => true, 'store-api' => false])]
    public ?OrderReturnEntity $orderReturn = null;
}
