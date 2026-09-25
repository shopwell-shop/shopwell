<?php
declare(strict_types=1);

namespace Shopwell\Storefront\Theme\ConfigLoader;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class AbstractAvailableThemeProvider
{
    abstract public function getDecorated(): AbstractAvailableThemeProvider;

    /**
     * @return array<string, string>
     */
    abstract public function load(Context $context, bool $activeOnly): array;
}
