<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ContentSystem\ContentSystemException;
use Shopwell\Core\Framework\ContentSystem\Mapping\MappingProblem;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingProblem::class)]
class MappingProblemTest extends TestCase
{
    public function testCarriesTheElementPropertyPathAndValidationFailure(): void
    {
        $exception = ContentSystemException::unknownMappingPath('product.secret', 'product');
        $problem = new MappingProblem('element-1', 'text', 'product.secret', $exception);

        static::assertSame('element-1', $problem->elementId);
        static::assertSame('text', $problem->propertyKey);
        static::assertSame('product.secret', $problem->sourceName);
        static::assertSame($exception, $problem->exception);
    }
}
