<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch;

use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
readonly class ResolvedField
{
    public function __construct(
        private Field $resolvedField,
        private ?string $root = null,
    ) {
    }

    public function getResolvedField(): Field
    {
        return $this->resolvedField;
    }

    public function getRoot(): ?string
    {
        return $this->root;
    }
}
