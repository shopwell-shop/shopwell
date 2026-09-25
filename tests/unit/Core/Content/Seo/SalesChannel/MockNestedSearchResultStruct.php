<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Seo\SalesChannel;

use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * Mimics a CMS slot data struct holding a search result directly in its vars.
 *
 * @internal
 */
class MockNestedSearchResultStruct extends Struct
{
    /**
     * @param EntitySearchResult<ProductCollection> $listing
     */
    public function __construct(protected EntitySearchResult $listing)
    {
    }
}
