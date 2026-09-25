<?php declare(strict_types=1);

namespace Shopwell\Core\Test\PHPUnit\CompletionGuard\Subscriber;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;

/**
 * @internal
 */
#[Package('framework')]
class MarkExecutionFinishedSubscriber implements ExecutionFinishedSubscriber
{
    public function notify(ExecutionFinished $event): void
    {
        CompletionGuard::$executionFinished = true;
    }
}
