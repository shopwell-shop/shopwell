<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal - may be changed in the future
 */
#[Package('discovery')]
abstract class AbstractCompilerConfiguration
{
    /**
     * @return array<string, mixed>
     */
    abstract public function getConfiguration(): array;

    /**
     * @return mixed
     */
    abstract public function getValue(string $key);
}
