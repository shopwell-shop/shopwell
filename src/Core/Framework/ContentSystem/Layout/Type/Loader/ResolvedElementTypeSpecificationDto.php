<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopwell\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ResolvedElementTypeSpecificationDto
{
    public function __construct(
        public string $name,
        public string $source,
        public ElementTypeSpecificationDto $dto,
    ) {
    }

    public function toSpecification(): ContentSystemElementTypeSpecification
    {
        return $this->dto->toContentSystemElementTypeSpecification($this->name, $this->source);
    }
}
