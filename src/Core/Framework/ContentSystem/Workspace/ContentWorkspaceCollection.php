<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Workspace;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<ContentWorkspaceEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ContentWorkspaceCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ContentWorkspaceEntity::class;
    }
}
