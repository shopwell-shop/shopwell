<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Draft\Entity;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends EntityCollection<WorkflowDraftEntity>
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\WorkflowDraftPersistenceTest
 */
#[Package('framework')]
class WorkflowDraftCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WorkflowDraftEntity::class;
    }
}
