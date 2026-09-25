<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Mcp\Attribute;

use Shopwell\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class McpToolGroup
{
    public function __construct(public string $group)
    {
    }
}
