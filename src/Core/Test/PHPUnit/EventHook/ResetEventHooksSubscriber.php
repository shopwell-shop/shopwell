<?php declare(strict_types=1);

namespace Shopwell\Core\Test\PHPUnit\EventHook;

use PHPUnit\Event\EventFacadeIsSealedException;
use PHPUnit\Event\Facade;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Integration\EventDispatcher\EventHookDispatcher;

/**
 * Clears the event hooks before each test, on PreparationStarted: Finished is not emitted for tests that skip or
 * fail in setUp(), which would leave their hooks behind. Registered by the test bootstrap.
 *
 * @internal
 */
#[Package('framework')]
class ResetEventHooksSubscriber implements PreparationStartedSubscriber
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || !class_exists(Facade::class)) {
            return;
        }

        try {
            Facade::instance()->registerSubscriber(new self());
        } catch (EventFacadeIsSealedException) {
            // bootstrap() was called from inside an already-running test process; the outer one registered it
            return;
        }

        self::$registered = true;
    }

    public function notify(PreparationStarted $event): void
    {
        EventHookDispatcher::resetAll();
    }
}
