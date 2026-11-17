<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstancePresentationEvent;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowInstancePresentationEvent::class)]
class WorkflowInstancePresentationEventTest extends TestCase
{
    public function testExtensionAddsItsOwnPresentationWithoutReplacingCoreFields(): void
    {
        $context = Context::createDefaultContext();
        $instance = new WorkflowInstanceEntity();
        $event = new WorkflowInstancePresentationEvent($instance, $context, ['id' => 'instance'], withDetail: true);

        $event->addData(['exampleTasks' => [['name' => 'Review']]]);

        static::assertSame($context, $event->context);
        static::assertSame(['id' => 'instance', 'exampleTasks' => [['name' => 'Review']]], $event->getData());
    }

    public function testExtensionCannotOverwriteAnAuthoritativeField(): void
    {
        $event = new WorkflowInstancePresentationEvent(new WorkflowInstanceEntity(), Context::createDefaultContext(), ['status' => 'RUNNING'], withDetail: true);

        static::expectExceptionObject(WorkflowExecutionException::duplicateRegistration('presentation field', 'status'));

        $event->addData(['status' => 'COMPLETED']);
    }

    public function testCollisionDoesNotPartiallyAddFields(): void
    {
        $event = new WorkflowInstancePresentationEvent(new WorkflowInstanceEntity(), Context::createDefaultContext(), ['result' => null], withDetail: true);

        try {
            $event->addData(['exampleDetails' => [], 'result' => 'changed']);
            static::fail('An existing null field must still be protected.');
        } catch (WorkflowExecutionException $exception) {
            static::assertEquals(WorkflowExecutionException::duplicateRegistration('presentation field', 'result'), $exception);
        }

        static::assertSame(['result' => null], $event->getData());
    }

    public function testFiltersAlsoApplyToDataAddedByLaterSubscribers(): void
    {
        $event = new WorkflowInstancePresentationEvent(new WorkflowInstanceEntity(), Context::createDefaultContext(), ['formData' => ['secret' => 'Hidden']], withDetail: true);
        $event->filterData(static function (array $data): array {
            unset($data['formData']['secret'], $data['businessSecret']);

            return $data;
        });
        $event->addData(['businessSecret' => 'Hidden', 'status' => 'RUNNING']);
        static::assertSame(['formData' => [], 'status' => 'RUNNING'], $event->getData());
        static::assertSame($event->getData(), $event->getData());
    }
}
