<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle;

use Shopwell\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopwell\Core\Framework\Plugin;
use Shopwell\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\FeatureA\FeatureA;
use Shopwell\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\FeatureB\FeatureB;

/**
 * @internal
 */
class ExampleBundle extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [
            new FeatureA(),
            new FeatureB(),
        ];
    }
}
