<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture;

use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @internal
 */
#[Entity('attribute_entity_agg', parent: 'attribute_entity', since: '6.6.3.0')]
// Test that autoconfigure works with attribute entities, do not add the tag in service declaration in service_test.xml
#[AutoconfigureTag('shopware.entity')]
class AttributeEntityAgg extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'attribute_entity')]
    public string $attributeEntityId;

    #[Field(type: FieldType::STRING)]
    public string $number;

    #[ManyToOne(entity: 'attribute_entity', column: 'attribute_entity_id')]
    public ?AttributeEntity $ownColumn = null;

    #[ManyToOne(entity: 'attribute_entity')]
    public ?AttributeEntity $attributeEntity = null;
}
