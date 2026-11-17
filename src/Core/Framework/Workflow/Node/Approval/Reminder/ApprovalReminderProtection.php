<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection\ReadProtection;
use Shopwell\Core\Framework\Log\Package;

/**
 * Private receipts are read through authorized presenters, never generic CRUD routes.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderApiTest
 */
#[Package('framework')]
final class ApprovalReminderProtection extends EntityExtension
{
    public function getEntityName(): string
    {
        return ApprovalReminderEntity::ENTITY_NAME;
    }

    public function extendProtections(EntityProtectionCollection $protections): void
    {
        $protections->add(new ReadProtection(Context::USER_SCOPE, Context::SYSTEM_SCOPE));
    }
}
