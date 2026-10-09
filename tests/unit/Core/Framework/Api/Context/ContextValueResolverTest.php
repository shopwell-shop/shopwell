<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Api\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\ContextValueResolver;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextValueResolver::class)]
class ContextValueResolverTest extends TestCase
{
    public function testIgnoresArgumentsOfOtherTypes(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => Context::createDefaultContext(),
        ]);

        $resolved = (new ContextValueResolver())->resolve($request, self::argument(SalesChannelContext::class));

        static::assertSame([], iterator_to_array($resolved));
    }

    /**
     * @param array<string, Context> $attributes
     */
    #[DataProvider('contextProvider')]
    public function testResolvesTheContext(array $attributes, Context $expected): void
    {
        $resolved = (new ContextValueResolver())->resolve(
            new Request(attributes: $attributes),
            self::argument(Context::class),
        );

        static::assertSame([$expected], iterator_to_array($resolved));
    }

    public static function contextProvider(): \Generator
    {
        $sessionContext = Context::createDefaultContext();
        $orderContext = Context::createDefaultContext();

        yield 'order-based context of an opted-in route wins over the session context' => [
            [
                PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => $sessionContext,
                PlatformRequest::ATTRIBUTE_EFFECTIVE_CONTEXT_OBJECT => $orderContext,
            ],
            $orderContext,
        ];

        yield 'session context is used without an order-based context' => [
            [PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => $sessionContext],
            $sessionContext,
        ];
    }

    private static function argument(string $type): ArgumentMetadata
    {
        return new ArgumentMetadata('context', $type, isVariadic: false, hasDefaultValue: false, defaultValue: null);
    }
}
