<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowPublisher;
use Shopwell\Core\Framework\Workflow\Publication\WorkflowPublisherRegistry;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowPublisherRegistry::class)]
class WorkflowPublisherRegistryTest extends TestCase
{
    public function testDiscoversARegisteredBusinessPublisher(): void
    {
        $publisher = new TestPublisher();
        $registry = new WorkflowPublisherRegistry([$publisher]);
        static::assertSame([$publisher], $registry->all());
        static::assertSame($publisher, $registry->get('test:approval'));
    }

    public function testRejectsDuplicateNamesInsteadOfSilentlyReplacingAnExecutionTarget(): void
    {
        $this->expectExceptionObject(WorkflowException::duplicatePublisher('test:approval'));
        new WorkflowPublisherRegistry([new TestPublisher(), new TestPublisher()]);
    }

    public function testDoesNotFallbackWhenTheRequiredPluginIsUnavailable(): void
    {
        $this->expectExceptionObject(WorkflowException::publisherUnavailable('test:approval'));
        (new WorkflowPublisherRegistry([]))->get('test:approval');
    }
}

/**
 * @internal
 */
class TestPublisher implements WorkflowPublisher
{
    public function name(): string
    {
        return 'test:approval';
    }

    public function labelKey(): string
    {
        return 'test.approval';
    }

    public function privileges(): array
    {
        return ['test:publish'];
    }

    public function publish(WorkflowReleaseEntity $release, Context $context): void
    {
    }
}
