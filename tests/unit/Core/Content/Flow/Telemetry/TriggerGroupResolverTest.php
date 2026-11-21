<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Flow\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Flow\Telemetry\TriggerGroupResolver;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;

/**
 * Resolver mechanics against a synthetic table; the shipped `flow_trigger_group` table is covered by
 * {@see \Shopwell\Tests\Integration\Core\Framework\Telemetry\ShippedLabelMappingsTest}.
 *
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(TriggerGroupResolver::class)]
class TriggerGroupResolverTest extends TestCase
{
    #[DataProvider('eventProvider')]
    public function testResolve(string $eventName, string $expected): void
    {
        $resolver = new TriggerGroupResolver(new LabelMappingRegistry([
            TriggerGroupResolver::MAPPING => [
                'checkout.order.*' => 'order',
                'checkout.*' => 'checkout',
            ],
        ], 'other'));

        static::assertSame($expected, $resolver->resolve($eventName));
    }

    public static function eventProvider(): \Generator
    {
        yield 'longest prefix wins' => ['checkout.order.placed', 'order'];
        yield 'shorter prefix catches the rest' => ['checkout.customer.login', 'checkout'];
        yield 'unlisted event is other' => ['some.plugin.event', 'other'];
    }
}
