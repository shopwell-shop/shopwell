<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Aggregate\AppMcpResource;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<AppMcpResourceEntity>
 */
#[Package('framework')]
class AppMcpResourceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppMcpResourceEntity::class;
    }
}
