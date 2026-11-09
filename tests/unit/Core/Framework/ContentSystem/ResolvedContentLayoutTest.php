<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\PlaceholderValues;
use Shopwell\Core\Framework\ContentSystem\RenderingSpecification;
use Shopwell\Core\Framework\ContentSystem\ResolvedContentLayout;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResolvedContentLayout::class)]
class ResolvedContentLayoutTest extends TestCase
{
    public function testCreateKeepsTheSelectedLayoutAndItsRenderingContextTogether(): void
    {
        $request = new Request(['page' => 2]);
        $specification = new RenderingSpecification([], PlaceholderValues::from(['productId' => 'product-id']), $request, 'target', ['product-tag'], 'product');

        $resolved = ResolvedContentLayout::create('selected-layout', $specification);

        static::assertSame('selected-layout', $resolved->layoutId);
        static::assertSame($specification, $resolved->specification);
        static::assertSame($request, $resolved->specification->request);
        static::assertSame('target', $resolved->specification->targetElementId);
    }
}
