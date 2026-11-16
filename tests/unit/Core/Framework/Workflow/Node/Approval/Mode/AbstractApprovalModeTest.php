<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval\Mode;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\AbstractApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\AllApprovalMode;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractApprovalMode::class)]
class AbstractApprovalModeTest extends TestCase
{
    public function testBuiltInDefaultsAllowMultipleConcurrentReviewers(): void
    {
        $mode = new AllApprovalMode();
        $mode->validateConfiguration([]);

        static::assertSame(1, $mode->getVersion());
        static::assertTrue($mode->supportsMultipleAssignees());
        static::assertFalse($mode->isSequential());
    }

    public function testRejectsConfigurationThatTheBuiltInModeWouldIgnore(): void
    {
        $this->expectException(WorkflowException::class);

        (new AllApprovalMode())->validateConfiguration(['required' => 2]);
    }
}
