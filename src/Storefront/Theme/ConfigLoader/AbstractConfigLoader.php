<?php declare(strict_types=1);

namespace Shopwell\Storefront\Theme\ConfigLoader;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

#[Package('discovery')]
abstract class AbstractConfigLoader
{
    abstract public function getDecorated(): AbstractConfigLoader;

    abstract public function load(string $themeId, Context $context): StorefrontPluginConfiguration;
}
