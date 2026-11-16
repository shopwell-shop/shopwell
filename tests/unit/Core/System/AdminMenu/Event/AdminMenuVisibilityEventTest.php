<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\AdminMenu\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\AdminMenu\Event\AdminMenuVisibilityEvent;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(AdminMenuVisibilityEvent::class)]
class AdminMenuVisibilityEventTest extends TestCase
{
    public function testListenersAccumulateHiddenKeys(): void
    {
        $context = Context::createDefaultContext();
        $event = new AdminMenuVisibilityEvent($context, null);
        static::assertFalse($event->isHidden('finance'));

        $event->hide('finance', 'reports');
        $event->hide('workflow', 'finance');

        static::assertTrue($event->isHidden('finance'));
        static::assertTrue($event->isHidden('reports'));
        static::assertTrue($event->isHidden('workflow'));
        static::assertFalse($event->isHidden('dashboard'));
        static::assertSame($context, $event->getContext());
        static::assertNull($event->getLocale());
    }
}
