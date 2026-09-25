<?php declare(strict_types=1);

namespace Shopwell\Core\Test\PHPUnit\CompletionGuard\Subscriber;

use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;

/**
 * @internal
 */
#[Package('framework')]
class MarkExecutionStartedSubscriber implements ExecutionStartedSubscriber
{
    public function notify(ExecutionStarted $event): void
    {
        CompletionGuard::$executionStarted = true;
    }
}
