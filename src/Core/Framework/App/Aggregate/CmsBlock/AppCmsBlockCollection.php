<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\CmsBlock;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<AppCmsBlockEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class AppCmsBlockCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppCmsBlockEntity::class;
    }
}
