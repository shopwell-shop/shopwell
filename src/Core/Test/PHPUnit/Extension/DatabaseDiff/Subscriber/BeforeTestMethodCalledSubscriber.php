<?php declare(strict_types=1);

namespace Shopwell\Core\Test\PHPUnit\Extension\DatabaseDiff\Subscriber;

use PHPUnit\Event\Test\BeforeTestMethodCalled;
use PHPUnit\Event\Test\BeforeTestMethodCalledSubscriber as BeforeTestMethodCalledSubscriberInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\PHPUnit\Extension\DatabaseDiff\DbState;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class BeforeTestMethodCalledSubscriber implements BeforeTestMethodCalledSubscriberInterface
{
    public function __construct(private readonly DbState $dbState)
    {
    }

    public function notify(BeforeTestMethodCalled $event): void
    {
        $this->dbState->rememberCurrentDbState();
    }
}
