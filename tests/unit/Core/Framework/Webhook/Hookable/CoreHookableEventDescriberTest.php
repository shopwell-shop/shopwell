<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Hookable;
use Shopwell\Core\Framework\Webhook\Hookable\CoreHookableEventDescriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CoreHookableEventDescriber::class)]
class CoreHookableEventDescriberTest extends TestCase
{
    public function testDescribeReturnsAllStaticHookableEvents(): void
    {
        $describer = new CoreHookableEventDescriber();

        static::assertCount(\count(Hookable::HOOKABLE_EVENTS), $describer->describe());
    }
}
