<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @extends EntityCollection<ApprovalReminderEntity>
 */
#[Package('framework')]
class ApprovalReminderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ApprovalReminderEntity::class;
    }
}
