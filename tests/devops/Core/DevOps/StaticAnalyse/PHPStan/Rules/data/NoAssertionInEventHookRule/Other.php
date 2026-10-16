<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoAssertionInEventHookRule;

/**
 * @internal
 */
class Other
{
    public function on(string $eventName, callable $hook): void
    {
    }
}
