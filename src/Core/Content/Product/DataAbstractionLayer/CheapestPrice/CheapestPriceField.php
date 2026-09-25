<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class CheapestPriceField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName,
        array $propertyMapping = []
    ) {
        parent::__construct($storageName, $propertyName, $propertyMapping);
        $this->addFlags(new WriteProtected());
    }

    protected function getSerializerClass(): string
    {
        return PHPUnserializeFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return CheapestPriceAccessorBuilder::class;
    }
}
