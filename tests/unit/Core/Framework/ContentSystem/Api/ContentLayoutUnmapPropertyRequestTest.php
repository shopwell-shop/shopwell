<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\Api\ContentLayoutUnmapPropertyRequest;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutUnmapPropertyRequest::class)]
class ContentLayoutUnmapPropertyRequestTest extends TestCase
{
    public function testCarriesThePersistedUnmapOperationInput(): void
    {
        $request = new ContentLayoutUnmapPropertyRequest('element-1', 'text', null);

        static::assertSame('element-1', $request->elementId);
        static::assertSame('text', $request->propertyKey);
        static::assertNull($request->expectedVersion);
    }
}
