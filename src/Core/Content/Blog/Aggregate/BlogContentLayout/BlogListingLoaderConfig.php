<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Aggregate\BlogContentLayout;

use Shopwell\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final readonly class BlogListingLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string $property
     */
    public function __construct(public string $property)
    {
    }

    public function jsonSerialize(): array
    {
        return ['property' => $this->property];
    }
}
