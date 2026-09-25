<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Field;

use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopwell\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;

/**
 * General field to support custom serializes in attribute entities, should never be used directly, but only over the #[Serialized] attribute.
 * If you use EntityDefinition classes you should add your own specific field for your custom serializer instead.
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0', description: 'Never use it directly, only via the #[Serialized] attribute.')]
class SerializedField extends Field implements StorageAware
{
    /**
     * @deprecated tag:v6.8.0 - parameter $serializer will be required, as default serializer does not work
     *
     * @param class-string<FieldSerializerInterface> $serializer
     */
    public function __construct(
        private readonly string $storageName,
        string $propertyName,
        private readonly string $serializer = JsonFieldSerializer::class
    ) {
        if ($serializer === JsonFieldSerializer::class) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                '$serializer parameter in `SerializedField` will be required in v6.8.0.0, as default serializer does not work.'
            );
        }

        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    protected function getSerializerClass(): string
    {
        return $this->serializer;
    }
}
