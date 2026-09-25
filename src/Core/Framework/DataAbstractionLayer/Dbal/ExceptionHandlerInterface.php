<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Dbal;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
interface ExceptionHandlerInterface
{
    public const PRIORITY_DEFAULT = 0;

    public const PRIORITY_LATE = -10;

    public const PRIORITY_EARLY = 10;

    public function getPriority(): int;

    public function matchException(\Throwable $e): ?\Throwable;
}
