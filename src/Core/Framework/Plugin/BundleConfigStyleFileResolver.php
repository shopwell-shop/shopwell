<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Plugin;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface BundleConfigStyleFileResolver
{
    /**
     * @return list<string>
     */
    public function resolveStyleFiles(string $technicalName, string $basePath): array;
}
