<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Node\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalMode;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalModeRegistry::class)]
class ApprovalModeRegistryTest extends TestCase
{
    public function testFindsTheSamePluginModeDuringPublicationAndExecution(): void
    {
        $mode = $this->mode('example:quorum', 'example:quorum', 2);
        $registry = new ApprovalModeRegistry([$mode]);

        static::assertSame($mode, $registry->get('example:quorum', 2));
        static::assertSame($mode, $registry->forType('example:quorum', 2));
        static::assertFalse($registry->has('example:quorum', 1));
    }

    public function testKeepsBothVersionsAvailableForExistingInstances(): void
    {
        $original = $this->mode('example:quorum', 'example:quorum', 1);
        $updated = $this->mode('example:quorum', 'example:quorum', 2);
        $registry = new ApprovalModeRegistry([$original, $updated]);

        static::assertSame($original, $registry->forType('example:quorum', 1));
        static::assertSame($updated, $registry->forType('example:quorum', 2));
    }

    public function testRejectsDuplicateModeVersions(): void
    {
        $this->expectException(WorkflowException::class);

        new ApprovalModeRegistry([
            $this->mode('example:quorum', 'example:first', 1),
            $this->mode('example:quorum', 'example:second', 1),
        ]);
    }

    public function testRejectsAmbiguousRuntimeIdentifiers(): void
    {
        $this->expectException(WorkflowException::class);

        new ApprovalModeRegistry([
            $this->mode('example:first', 'example:shared', 1),
            $this->mode('example:second', 'example:shared', 1),
        ]);
    }

    public function testMissingVersionDoesNotFallBackToAnotherImplementation(): void
    {
        $registry = new ApprovalModeRegistry([$this->mode('example:quorum', 'example:quorum', 2)]);

        $this->expectExceptionObject(WorkflowException::approvalModeUnavailable('example:quorum', 1));

        $registry->forType('example:quorum', 1);
    }

    private function mode(string $name, string $type, int $version): ApprovalMode
    {
        $mode = static::createStub(ApprovalMode::class);
        $mode->method('getName')->willReturn($name);
        $mode->method('getType')->willReturn($type);
        $mode->method('getVersion')->willReturn($version);

        return $mode;
    }
}
