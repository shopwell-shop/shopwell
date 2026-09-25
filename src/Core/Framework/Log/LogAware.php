<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Log;

use Monolog\Level;
use Shopwell\Core\Framework\Event\IsFlowEventAware;

#[Package('framework')]
#[IsFlowEventAware]
interface LogAware
{
    /**
     * @return array<string, mixed>
     */
    public function getLogData(): array;

    public function getLogLevel(): Level;
}
