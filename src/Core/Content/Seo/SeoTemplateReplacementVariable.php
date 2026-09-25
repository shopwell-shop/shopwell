<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo;

use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
class SeoTemplateReplacementVariable
{
    public function __construct(
        private readonly string $mappedEntityName,
        private readonly ?string $mappedEntityFields = null
    ) {
    }

    public function hasMappedFields(): bool
    {
        return $this->mappedEntityFields !== null;
    }

    public function getMappedEntityName(): string
    {
        return $this->mappedEntityName;
    }

    public function getMappedEntityFields(): ?string
    {
        return $this->mappedEntityFields;
    }
}
