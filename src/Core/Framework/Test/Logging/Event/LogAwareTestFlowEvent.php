<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\Logging\Event;

use Monolog\Level;
use Shopwell\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\LogAware;

/**
 * @internal
 */
class LogAwareTestFlowEvent extends TestFlowBusinessEvent implements LogAware, FlowEventAware
{
    final public const EVENT_NAME = 'test.flow_event.log_aware';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getLogData(): array
    {
        return ['awesomekey' => 'awesomevalue'];
    }

    public function getLogLevel(): Level
    {
        return Level::Emergency;
    }
}
