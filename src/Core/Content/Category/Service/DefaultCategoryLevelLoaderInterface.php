<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Category\Service;

use Shopwell\Core\Content\Category\CategoryCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for internal use as it only loads the default category levels
 * externals should rely on the @see NavigationLoader
 */
#[Package('discovery')]
interface DefaultCategoryLevelLoaderInterface
{
    public function loadLevels(
        string $rootId,
        int $rootLevel,
        SalesChannelContext $context,
        Criteria $criteria,
        int $depth,
    ): CategoryCollection;
}
