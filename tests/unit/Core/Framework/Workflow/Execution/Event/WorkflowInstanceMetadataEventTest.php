<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowInstanceMetadataEvent;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowInstanceMetadataEvent::class)]
class WorkflowInstanceMetadataEventTest extends TestCase
{
    public function testExtensionsContributeIndependentMetadata(): void
    {
        $context = Context::createDefaultContext();
        $event = new WorkflowInstanceMetadataEvent('initiator', $context);
        $event->addMetadata('example:policy', ['revision' => 3]);
        $event->addMetadata('example:department', ['id' => null]);

        static::assertSame('initiator', $event->initiatorId);
        static::assertSame($context, $event->context);
        static::assertSame([
            'example:policy' => ['revision' => 3],
            'example:department' => ['id' => null],
        ], $event->getMetadata());
    }

    public function testDuplicateOwnerCannotOverwriteCapturedMetadata(): void
    {
        $event = new WorkflowInstanceMetadataEvent('initiator', Context::createDefaultContext());
        $event->addMetadata('example:policy', ['revision' => 3]);

        $this->expectExceptionObject(WorkflowExecutionException::duplicateRegistration('instance metadata', 'example:policy'));
        $event->addMetadata('example:policy', ['revision' => 4]);
    }
}
