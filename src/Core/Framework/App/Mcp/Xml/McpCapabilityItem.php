<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Mcp\Xml;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface McpCapabilityItem
{
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array;
}
