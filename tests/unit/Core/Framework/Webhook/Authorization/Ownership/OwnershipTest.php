<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook\Authorization\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Webhook\Authorization\Ownership\Ownership;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Ownership::class)]
class OwnershipTest extends TestCase
{
    public function testAWebhookWithAnAppBelongsToIt(): void
    {
        $ownership = new Ownership(Uuid::randomHex(), Uuid::randomHex());

        static::assertTrue($ownership->belongsToApp());
    }

    public function testAWebhookWithoutAnAppDoesNotBelongToOne(): void
    {
        $ownership = new Ownership(Uuid::randomHex(), null);

        static::assertFalse($ownership->belongsToApp());
    }
}
