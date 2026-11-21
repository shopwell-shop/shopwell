<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Fixture;

use Shopwell\Core\Framework\ContentSystem\Binding\RootSourceConfigMap;
use Shopwell\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopwell\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;

/**
 * @internal
 */
final class ScopedDefaultBindingFixture
{
    /**
     * A core default specification for $type whose only key, `media`, scopes its `associations` by the root source
     * `product`. A layout whose root source is anything else (for example `category`) cannot resolve that key.
     */
    public static function mediaScopedToProduct(string $type, string $label): BindingSpecification
    {
        return new BindingSpecification(
            $type,
            $type,
            $label,
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId', 'associations' => [RootSourceConfigMap::MARKER => ['product' => ['thumbnails']]]])],
            [],
            'core',
        );
    }
}
