<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventDescription;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HookableEventDescription::class)]
class HookableEventDescriptionTest extends TestCase
{
    public function testConstructorAssignsProperties(): void
    {
        $description = new HookableEventDescription('test.event', 'Test description', ['test:read']);

        static::assertSame('test.event', $description->eventName);
        static::assertSame('Test description', $description->description);
        static::assertSame(['test:read'], $description->privileges);
    }
}
