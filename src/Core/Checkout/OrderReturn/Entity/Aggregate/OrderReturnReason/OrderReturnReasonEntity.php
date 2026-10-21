<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Entity\Aggregate\OrderReturnReason;

use Shopwell\Core\Checkout\OrderReturn\Entity\OrderReturnEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OneToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\SearchRanking;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Translations;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayEntity;

/**
 * A reason a customer may pick when opening an return.
 *
 * The list is maintained in the settings of the administration and not shipped: which reasons a shop
 * needs depends on what it sells, so the core only brings the mechanism. A reason that is switched
 * off stays on the returns that already used it - the label is read through the association and
 * never copied into the return.
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
#[Entity('order_return_reason', since: '6.7.18.0', collectionClass: OrderReturnReasonCollection::class)]
class OrderReturnReasonEntity extends EntityStruct
{
    use EntityCustomFieldsTrait;

    final public const ENTITY_NAME = 'order_return_reason';

    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => true])]
    public string $id;

    /**
     * The label is translated, so it only exists in `order_return_reason_translation` and never in
     * the table itself - a language that has none inherits the one of its parent.
     */
    #[Field(type: FieldType::STRING, translated: true, api: ['admin-api' => true, 'store-api' => true], maxLength: 255)]
    #[SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)]
    public ?string $label = null;

    /**
     * A switched-off reason is not offered anymore, the returns that picked it keep it.
     */
    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => true])]
    public bool $active = true;

    #[Field(type: FieldType::INT, api: ['admin-api' => true, 'store-api' => true])]
    public int $position = 0;

    /**
     * The labels per language. Only the label is translated - `active` and `position` are the same
     * in every language, so the list is ordered and switched off once for the whole shop.
     *
     * @var array<string, ArrayEntity>|null
     */
    #[Translations]
    public ?array $translations = null;

    /**
     * The returns that picked this reason. A deleted reason only takes the label away, the returns
     * stay and lose their reference - exactly like the foreign key of the table does it.
     *
     * @var array<string, OrderReturnEntity>|null
     */
    #[OneToMany(entity: 'order_return', ref: 'reason_id', onDelete: OnDelete::SET_NULL)]
    public ?array $returns = null;
}
