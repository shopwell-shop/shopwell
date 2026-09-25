<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\Serializer\_fixtures;

use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToMany;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\OnDelete;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @internal
 */
class TestAttributeEntity extends Entity
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'customer')]
    public ?string $customerId = null;

    /**
     * @var array<string, ProductEntity>|null
     */
    #[ManyToMany(entity: 'product', onDelete: OnDelete::CASCADE)]
    public ?array $products = null;

    #[ManyToOne(entity: 'customer', onDelete: OnDelete::SET_NULL)]
    public ?CustomerEntity $customer;
}
