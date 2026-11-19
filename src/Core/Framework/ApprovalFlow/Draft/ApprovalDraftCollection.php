<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Draft;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ApprovalDraftEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ApprovalDraftCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'approval_flow_draft_collection';
    }

    protected function getExpectedClass(): string
    {
        return ApprovalDraftEntity::class;
    }
}
