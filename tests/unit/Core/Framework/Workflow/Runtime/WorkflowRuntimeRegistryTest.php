<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Runtime;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntime;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeException;
use Shopwell\Core\Framework\Workflow\Runtime\WorkflowRuntimeRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowRuntimeRegistry::class)]
class WorkflowRuntimeRegistryTest extends TestCase
{
    public function testResolvesTheRuntimeByPublicationTarget(): void
    {
        $runtime = static::createStub(WorkflowRuntime::class);
        $runtime->method('name')->willReturn('test:approval');
        $registry = new WorkflowRuntimeRegistry([$runtime]);

        static::assertTrue($registry->has('test:approval'));
        static::assertFalse($registry->has('disabled:approval'));
        static::assertSame($runtime, $registry->get('test:approval'));
    }

    public function testMissingRuntimeFailsClosed(): void
    {
        $this->expectExceptionObject(WorkflowRuntimeException::unavailable('disabled:approval'));

        (new WorkflowRuntimeRegistry([]))->get('disabled:approval');
    }

    public function testDuplicateRuntimeCannotReplaceTheFirstProvider(): void
    {
        $runtime = static::createStub(WorkflowRuntime::class);
        $runtime->method('name')->willReturn('test:approval');
        $this->expectExceptionObject(WorkflowRuntimeException::duplicate('test:approval'));

        new WorkflowRuntimeRegistry([$runtime, $runtime]);
    }
}
