<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\TestCaseBase;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class QueueTestBehaviourTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    public function testNoAssertionIsPerformedInTheTrait(): void
    {
        // Ensures runWorker() and getDispatchedMessageCount() do not perform any PHPUnit assertions,
        // and clearQueue() is implicitly verified via its #[Before]/#[After] hooks around this test.
        static::expectNotToPerformAssertions();

        $this->runWorker();
        $this->getDispatchedMessageCount(\stdClass::class);
    }
}
