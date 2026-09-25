<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Breadcrumb\Struct;

use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

#[Package('inventory')]
class Breadcrumb extends Struct
{
    /**
     * @param array<string, mixed> $translated
     * @param list<array<string, string>> $seoUrls
     */
    public function __construct(
        public string $name,
        public string $categoryId = '',
        public string $type = '',
        public array $translated = [],
        public string $path = '',
        public array $seoUrls = []
    ) {
    }

    public function shouldOpenInNewTab(): bool
    {
        return $this->type === CategoryDefinition::TYPE_LINK && ($this->translated['linkNewTab'] ?? false);
    }

    public function getApiAlias(): string
    {
        return 'breadcrumb';
    }
}
