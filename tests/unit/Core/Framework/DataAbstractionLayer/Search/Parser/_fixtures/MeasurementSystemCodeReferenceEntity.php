<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser\_fixtures;

use Shopwell\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementSystemEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\ManyToOne;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;

/**
 * @internal
 */
#[Entity('measurement_system_code_reference')]
class MeasurementSystemCodeReferenceEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[ForeignKey(entity: 'measurement_system')]
    public ?string $measurementSystemId = null;

    #[ManyToOne(entity: 'measurement_system', ref: 'technical_name')]
    public ?MeasurementSystemEntity $measurementSystem = null;
}
