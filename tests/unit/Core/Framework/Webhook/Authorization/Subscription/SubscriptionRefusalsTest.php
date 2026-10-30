<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook\Authorization\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Authorization\Subscription\SubscriptionRefusals;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SubscriptionRefusals::class)]
class SubscriptionRefusalsTest extends TestCase
{
    public function testEachMissingPrivilegeIsListedOnce(): void
    {
        $refusals = new SubscriptionRefusals(missingPrivileges: [
            'hook1: order.written' => ['order:read'],
            'hook2: order.deleted' => ['order:read', 'product:read'],
        ]);

        static::assertSame(['order:read', 'product:read'], $refusals->allMissingPrivileges);
    }
}
