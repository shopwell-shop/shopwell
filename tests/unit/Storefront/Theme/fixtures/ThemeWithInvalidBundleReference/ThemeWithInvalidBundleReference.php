<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Theme\fixtures\ThemeWithInvalidBundleReference;

use Shopwell\Core\Framework\Bundle;
use Shopwell\Storefront\Framework\ThemeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
class ThemeWithInvalidBundleReference extends Bundle implements ThemeInterface
{
    public function getThemeName(): string
    {
        return 'ThemeWithInvalidBundleReference';
    }

    public function getPath(): string
    {
        return __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
    }
}
