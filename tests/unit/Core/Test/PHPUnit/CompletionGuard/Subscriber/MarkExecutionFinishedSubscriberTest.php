<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Test\PHPUnit\CompletionGuard\Subscriber;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;
use Shopwell\Core\Test\PHPUnit\CompletionGuard\Subscriber\MarkExecutionFinishedSubscriber;
use Shopwell\Tests\Unit\Core\Test\PHPUnit\TelemetryInfoFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MarkExecutionFinishedSubscriber::class)]
class MarkExecutionFinishedSubscriberTest extends TestCase
{
    private bool $previousState;

    protected function setUp(): void
    {
        $this->previousState = CompletionGuard::$executionFinished;
    }

    protected function tearDown(): void
    {
        // leaving this true would disarm the guard for the very suite running this test; restore it
        CompletionGuard::$executionFinished = $this->previousState;
    }

    public function testNotifyMarksExecutionAsFinished(): void
    {
        CompletionGuard::$executionFinished = false;

        (new MarkExecutionFinishedSubscriber())->notify($this->buildEvent());

        static::assertTrue(CompletionGuard::$executionFinished);
    }

    private function buildEvent(): ExecutionFinished
    {
        return new ExecutionFinished(TelemetryInfoFactory::create());
    }
}
