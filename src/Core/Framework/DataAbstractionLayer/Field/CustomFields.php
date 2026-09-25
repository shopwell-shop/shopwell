<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class CustomFields extends JsonField
{
    public function __construct(
        string $storageName = 'custom_fields',
        string $propertyName = 'customFields'
    ) {
        parent::__construct($storageName, $propertyName);
    }

    /**
     * @param list<Field> $propertyMapping
     */
    public function setPropertyMapping(array $propertyMapping): void
    {
        $this->propertyMapping = $propertyMapping;
    }

    protected function getSerializerClass(): string
    {
        return CustomFieldsSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return CustomFieldsAccessorBuilder::class;
    }
}
