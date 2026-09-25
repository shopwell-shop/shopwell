<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Storefront\Theme\fixtures\PluginWithAdditionalBundles;

use Shopwell\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopwell\Core\Framework\Plugin;
use Shopwell\Tests\Integration\Storefront\Theme\fixtures\PluginWithAdditionalBundles\SubBundle1\SubBundle1;

/**
 * @internal
 */
class PluginWithAdditionalBundles extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $additionalBundleParameters): array
    {
        return [
            new SubBundle1(),
        ];
    }
}
