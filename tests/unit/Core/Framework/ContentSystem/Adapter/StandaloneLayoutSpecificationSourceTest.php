<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Adapter\StandaloneLayoutSpecificationSource;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StandaloneLayoutSpecificationSource::class)]
class StandaloneLayoutSpecificationSourceTest extends TestCase
{
    public function testResolvesOnlyAnExactLayoutPath(): void
    {
        $source = new StandaloneLayoutSpecificationSource();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();
        $id = '0123456789abcdef0123456789abcdef';

        static::assertTrue($source->supports('/layout/' . $id, $request, $context));
        static::assertFalse($source->supports('/layout/not-an-id', $request, $context));
        static::assertFalse($source->supports('/layout/' . $id . '/extra', $request, $context));
        static::assertFalse($source->supports('/product/' . $id, $request, $context));
        static::assertSame($id, $source->resolveLayoutId('/layout/' . $id, $request, $context));
        static::assertSame(['content-layout-' . $id], $source->resolveCacheTags('/layout/' . $id, $request, $context));
        static::assertSame('none', $source->rootSource());
    }

    public function testProvidesOnlyScalarExplicitPlaceholderValues(): void
    {
        $source = new StandaloneLayoutSpecificationSource();
        $request = new Request(['q' => 'term', 'page' => 2, 'nested' => ['forbidden']]);
        $context = Generator::generateSalesChannelContext();
        $data = $source->resolveSpecificationData('/layout/0123456789abcdef0123456789abcdef', $request, $context);

        static::assertSame([], $data->dataRequirements);
        static::assertSame(['q' => 'term', 'page' => 2], $data->placeholderValues->all());
        static::assertNull($source->resolveTargetElementId('', $request, $context));
        static::assertFalse($source->supportsEntityType('product'));
    }
}
