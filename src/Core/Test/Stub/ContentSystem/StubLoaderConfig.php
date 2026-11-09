<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\ContentSystem;

use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
readonly class StubLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [];
    }
}
